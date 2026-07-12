<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Shipment;
use App\Models\StockLot;
use App\Models\StockMovement;
use App\Models\WebhookEvent;
use App\Notifications\OrderConfirmed;
use App\Services\Pricing\CartPricingCalculator;
use App\Services\Stock\StockAllocator;
use Dedoc\Scramble\Attributes\Group;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Stripe\StripeClient;
use Stripe\Webhook;
use Throwable;

#[Group(name: 'Paiement', weight: 7)]
class StripeController extends Controller
{
    /**
     * Create a Stripe PaymentIntent for the current cart
     *
     * @response 200 scenario="PaymentIntent created" { "order_id": 42, "payment_intent_id": "pi_3xxxxxxxxxxxxxxxxxxxxxxx", "client_secret": "pi_3xxxxxxxxxxxxxxxxxxxxxxx_secret_yyyyyyyyyyyyyyyyyyyyyyyy","amount": 89.90, "currency": "EUR" }
     * @response 400 scenario="Empty cart" {"message": "Panier vide"}
     * @response 401 scenario="Unauthenticated" {"message": "Unauthenticated"}
     * @response 422 scenario="Invalid shipping data" {"message": "The shipping.zip field is required."}
     *
     * @throws Throwable
     */
    public function createPaymentIntent(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $data = $request->validate([
            'shipping' => ['required', 'array'],
            'shipping.firstname' => ['required', 'string'],
            'shipping.lastname' => ['required', 'string'],
            'shipping.address' => ['required', 'string'],
            'shipping.zip' => ['required', 'string'],
            'shipping.city' => ['required', 'string'],
            'shipping.country' => ['required', 'string'],
            'shipping.phone' => ['nullable', 'string'],
        ]);

        $cart = $user->cart()->firstOrCreate(['user_id' => $user->id]);

        $cartItems = $cart->items()
            ->with(['product', 'option', 'customProductSession'])
            ->get();

        if ($cartItems->isEmpty()) {
            return response()->json(['message' => 'Panier vide'], 400);
        }

        $stripe = app(StripeClient::class);

        return DB::transaction(function () use ($user, $cartItems, $data, $stripe) {
            $totalTtc = 0.0;
            $totalHt = 0.0;
            $pricedItems = [];

            foreach ($cartItems as $ci) {
                $product = $ci->product;

                if (! $product) {
                    abort(422, 'Produit introuvable dans le panier.');
                }

                $unitTtc = CartPricingCalculator::unitPrice(
                    $ci->customProductSession?->unit_price_snapshot,
                    $ci->option?->price_ttc,
                    (float) $product->price_ttc
                );
                $unitHt = CartPricingCalculator::unitPrice(null, $ci->option?->price_ht, (float) $product->price_ht);
                $qty = (int) $ci->quantity;

                $totalTtc += CartPricingCalculator::lineTotal($unitTtc, $qty);
                $totalHt += CartPricingCalculator::lineTotal($unitHt, $qty);

                $pricedItems[] = ['cartItem' => $ci, 'product' => $product, 'unitTtc' => $unitTtc, 'qty' => $qty];
            }

            $totalTtc = CartPricingCalculator::round($totalTtc);
            $totalHt = CartPricingCalculator::round($totalHt);

            // 2) Order
            $order = Order::create([
                'user_id' => $user->id,
                'total_ht' => $totalHt,
                'total_ttc' => $totalTtc,
                'payment_status' => 'pending',
                'order_status' => 'new',
            ]);

            // 3) Shipment (structured)
            Shipment::create([
                'order_id' => $order->id,
                'firstname' => $data['shipping']['firstname'],
                'lastname' => $data['shipping']['lastname'],
                'address' => $data['shipping']['address'],
                'zip' => $data['shipping']['zip'],
                'city' => $data['shipping']['city'],
                'country' => $data['shipping']['country'],
                'phone' => $data['shipping']['phone'] ?? null,
                'status' => 'pending',
            ]);

            // 4) Items
            foreach ($pricedItems as $priced) {
                $ci = $priced['cartItem'];
                $product = $priced['product'];
                $unitTtc = $priced['unitTtc'];
                $qty = $priced['qty'];

                $customSession = $ci->customProductSession;

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'product_option_id' => $ci->product_option_id,
                    'custom_product_session_id' => $customSession?->id,
                    'lot_id' => null,
                    'unit_price' => round((float) $unitTtc, 2),
                    'quantity' => $qty,
                    'total' => round(((float) $unitTtc) * $qty, 2),
                    'customization_snapshot' => $customSession?->configuration,
                    'customization_preview_path' => $customSession?->preview_image_path,
                ]);
            }

            // 5) Payment row
            $payment = Payment::create([
                'order_id' => $order->id,
                'provider' => 'stripe',
                'provider_payment_id' => null,
                'amount' => $totalTtc,
                'status' => 'pending',
                'raw_payload' => null,
            ]);

            // 6) Stripe PaymentIntent
            $intent = $stripe->paymentIntents->create([
                'amount' => (int) round($totalTtc * 100),
                'currency' => 'eur',
                'payment_method_types' => ['card'],
                'description' => "Commande #$order->id",
                'metadata' => [
                    'order_id' => (string) $order->id,
                    'user_id' => (string) $user->id,
                    'payment_id' => (string) $payment->id,
                ],
            ]);

            $payment->provider_payment_id = $intent->id;
            $payment->raw_payload = $intent->toArray();
            $payment->save();

            return response()->json([
                'order_id' => $order->id,
                'payment_intent_id' => $intent->id,
                'client_secret' => $intent->client_secret,
                'amount' => $totalTtc,
                'currency' => 'EUR',
            ]);
        });
    }

    /**
     * Stripe webhook - payment events
     * Endpoint called by Stripe (not by the frontend).
     * Verifies the `Stripe-Signature` header against `STRIPE_WEBHOOK_SECRET`
     *
     * @unauthenticated
     *
     * @response 200 scenario="Event processed" {"status": "success"}
     * @response 200 scenario="Event already processed (idempotency)" {"status": "already_processed"}
     * @response 400 scenario="Invalid signature" {"error": "Invalid signature"}
     * @response 500 scenario="Processing error (Stripe may retry)" {"error": "..."}
     */
    public function webhook(Request $request): JsonResponse
    {
        $endpointSecret = config('services.stripe.webhook_secret', env('STRIPE_WEBHOOK_SECRET'));
        $payload = $request->getContent();
        $sig = $request->header('Stripe-Signature');

        try {
            $event = Webhook::constructEvent($payload, $sig, $endpointSecret);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }

        $provider = 'stripe';
        $providerEventId = (string) $event->id;

        // idempotency
        try {
            $we = WebhookEvent::firstOrCreate(
                ['provider' => $provider, 'provider_event_id' => $providerEventId],
                ['event_type' => $event->type, 'payload' => $event->toArray(), 'processed_at' => null]
            );
        } catch (QueryException $e) {
            $we = WebhookEvent::where('provider', $provider)
                ->where('provider_event_id', $providerEventId)
                ->first();
        }

        if ($we && $we->processed_at) {
            return response()->json(['status' => 'already_processed']);
        }

        if ($we && empty($we->payload)) {
            $we->payload = $event->toArray();
            $we->save();
        }

        try {
            if ($event->type === 'payment_intent.succeeded') {
                $pi = $event->data->object;
                $orderId = $pi->metadata->order_id ?? null;
                $paymentId = $pi->metadata->payment_id ?? null;

                DB::transaction(function () use ($pi, $orderId, $paymentId) {
                    if ($paymentId && ($payment = Payment::find($paymentId))) {
                        $payment->status = 'success';
                        $payment->raw_payload = $pi->toArray();
                        $payment->save();
                    }

                    if ($orderId && ($order = Order::with(['user', 'items.product'])->find($orderId))) {
                        $order->payment_status = 'paid';
                        $order->order_status = 'processing';
                        $order->save();

                        // clear DB cart (source: cart->items)
                        if ($order->user) {
                            $order->user->cart?->items()->delete();
                        }

                        // confirmation email (idempotent)
                        if ($order->user && ! $order->paid_email_sent_at) {
                            $order->user->notify(new OrderConfirmed($order));
                            $order->paid_email_sent_at = now();
                            $order->save();
                        }

                        // Decrement stock — FIFO (earliest-expiring lot first)
                        foreach ($order->items as $item) {
                            $lot = StockLot::where('product_id', $item->product_id)
                                ->when(
                                    $item->product_option_id,
                                    fn ($q) => $q->where('product_option_id', $item->product_option_id),
                                    fn ($q) => $q->whereNull('product_option_id')
                                )
                                ->where('quantity', '>', 0)
                                ->orderByRaw('expiration_date IS NULL, expiration_date ASC')
                                ->orderBy('id')
                                ->first();

                            if ($lot) {
                                $deducted = StockAllocator::deduction($lot->quantity, (int) $item->quantity);
                                $lot->decrement('quantity', $deducted);

                                StockMovement::create([
                                    'lot_id' => $lot->id,
                                    'product_id' => $item->product_id,
                                    'quantity' => $deducted,
                                    'type' => 'out',
                                    'reason' => "Vente — Commande #{$order->id}",
                                ]);

                                $item->lot_id = $lot->id;
                                $item->save();
                            }
                        }
                    }
                });
            }

            if ($event->type === 'payment_intent.payment_failed') {
                $pi = $event->data->object;
                $orderId = $pi->metadata->order_id ?? null;
                $paymentId = $pi->metadata->payment_id ?? null;

                DB::transaction(function () use ($pi, $orderId, $paymentId) {
                    if ($paymentId && ($payment = Payment::find($paymentId))) {
                        $payment->status = 'failed';
                        $payment->raw_payload = $pi->toArray();
                        $payment->save();
                    }

                    if ($orderId && ($order = Order::find($orderId))) {
                        $order->payment_status = 'failed';
                        $order->order_status = 'new';
                        $order->save();
                    }
                });
            }

            $we->processed_at = now();
            $we->save();

            return response()->json(['status' => 'success']);
        } catch (Throwable $e) {
            if ($we) {
                $we->failures()->create([
                    'error_message' => $e->getMessage(),
                    'retry_count' => 0,
                ]);
            }

            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
