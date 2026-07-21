<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Shipment;
use App\Models\StockLot;
use App\Models\StockMovement;
use App\Models\WebhookEvent;
use App\Notifications\OrderConfirmed;
use App\Services\Pricing\CartPricingCalculator;
use App\Services\Pricing\ShippingCalculator;
use App\Services\Stock\StockAllocator;
use Dedoc\Scramble\Attributes\Group;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Stripe\StripeClient;
use Stripe\Webhook;
use Throwable;

#[Group(name: 'Payment', weight: 7)]
class StripeController extends Controller
{
    /**
     * Create a Stripe PaymentIntent for the current cart
     *
     * @response 200 scenario="PaymentIntent created" { "order_id": 42, "payment_intent_id": "pi_3xxxxxxxxxxxxxxxxxxxxxxx", "client_secret": "pi_3xxxxxxxxxxxxxxxxxxxxxxx_secret_yyyyyyyyyyyyyyyyyyyyyyyy","amount": 89.90, "shipping_cost": 4.90, "currency": "EUR" }
     * @response 400 scenario="Empty cart" {"message": "Panier vide"}
     * @response 400 scenario="Missing guest cart identifier" {"message": "Missing guest cart identifier"}
     * @response 422 scenario="Invalid shipping data" {"message": "The shipping.zip field is required."}
     *
     * @throws Throwable
     */
    public function createPaymentIntent(Request $request): JsonResponse
    {
        $user = $request->user('sanctum');
        $guest_token = null;

        if (! $user) {
            $guest_token = $request->header('X-Guest-Cart-Token');
            abort_if(! $guest_token, 400, 'Missing guest cart identifier');
        }

        $data = $request->validate([
            'email' => ['required', 'email'],
            'shipping_method' => ['required', 'in:'.implode(',', ShippingCalculator::METHODS)],
            'shipping' => ['required', 'array'],
            'shipping.firstname' => ['required', 'string'],
            'shipping.lastname' => ['required', 'string'],
            'shipping.address' => ['required', 'string'],
            'shipping.zip' => ['required', 'string'],
            'shipping.city' => ['required', 'string'],
            'shipping.country' => ['required', 'string'],
            'shipping.phone' => ['nullable', 'string'],
        ]);

        $cart = $user ? $user->cart()->firstOrCreate(['user_id' => $user->id]) : Cart::firstOrCreate(['guest_token' => $guest_token]);
        $cart_items = $cart->items()->with(['product', 'option', 'customProductSession'])->get();
        if ($cart_items->isEmpty()) {
            return response()->json(['message' => 'Panier vide'], 400);
        }
        $stripe = app(StripeClient::class);

        return DB::transaction(function () use ($user, $guest_token, $cart_items, $data, $stripe) {
            $total_ttc = 0.0;
            $total_ht = 0.0;
            $priced_items = [];

            foreach ($cart_items as $ci) {
                $product = $ci->product;
                if (! $product) {
                    abort(422, 'Produit introuvable dans le panier.');
                }

                $unit_ttc = CartPricingCalculator::unitPrice($ci->customProductSession?->unit_price_snapshot, $ci->option?->price_ttc, (float) $product->price_ttc);
                $unit_ht = CartPricingCalculator::unitPrice(null, $ci->option?->price_ht, (float) $product->price_ht);
                $quantity = (int) $ci->quantity;
                $total_ttc += CartPricingCalculator::lineTotal($unit_ttc, $quantity);
                $total_ht += CartPricingCalculator::lineTotal($unit_ht, $quantity);
                $priced_items[] = ['cartItem' => $ci, 'product' => $product, 'unitTtc' => $unit_ttc, 'qty' => $quantity];
            }

            $total_ttc = CartPricingCalculator::round($total_ttc);
            $total_ht = CartPricingCalculator::round($total_ht);

            // Shipping cost is priced here
            $shipping_method = $data['shipping_method'];
            $shipping_cost = CartPricingCalculator::round(ShippingCalculator::cost($shipping_method, $total_ttc));
            $total_ttc = CartPricingCalculator::round($total_ttc + $shipping_cost);

            // Order create
            $order = Order::create([
                'user_id' => $user?->id,
                'guest_token' => $guest_token,
                'email' => $data['email'],
                'total_ht' => $total_ht,
                'total_ttc' => $total_ttc,
                'payment_status' => 'pending',
                'order_status' => 'new',
            ]);

            // Shipment create
            Shipment::create([
                'order_id' => $order->id,
                'firstname' => $data['shipping']['firstname'],
                'lastname' => $data['shipping']['lastname'],
                'address' => $data['shipping']['address'],
                'zip' => $data['shipping']['zip'],
                'city' => $data['shipping']['city'],
                'country' => $data['shipping']['country'],
                'phone' => $data['shipping']['phone'] ?? null,
                'method' => $shipping_method,
                'cost' => $shipping_cost,
                'status' => 'pending',
            ]);

            // Items
            foreach ($priced_items as $priced) {
                $ci = $priced['cartItem'];
                $product = $priced['product'];
                $unit_ttc = $priced['unitTtc'];
                $quantity = $priced['qty'];
                $custom_session = $ci->customProductSession;

                // OrderItem create
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'product_option_id' => $ci->product_option_id,
                    'custom_product_session_id' => $custom_session?->id,
                    'lot_id' => null,
                    'unit_price' => round((float) $unit_ttc, 2),
                    'quantity' => $quantity,
                    'total' => round(((float) $unit_ttc) * $quantity, 2),
                    'customization_snapshot' => $custom_session?->configuration,
                    'customization_preview_path' => $custom_session?->preview_image_path,
                ]);
            }

            // Payment create
            $payment = Payment::create([
                'order_id' => $order->id,
                'provider' => 'stripe',
                'provider_payment_id' => null,
                'amount' => $total_ttc,
                'status' => 'pending',
                'raw_payload' => null,
            ]);

            // Stripe PaymentIntent create
            $intent = $stripe->paymentIntents->create([
                'amount' => (int) round($total_ttc * 100),
                'currency' => 'eur',
                'payment_method_types' => ['card'],
                'description' => "Commande #$order->id",
                'metadata' => ['order_id' => (string) $order->id, 'user_id' => $user ? (string) $user->id : '', 'payment_id' => (string) $payment->id],
            ]);

            $payment->provider_payment_id = $intent->id;
            $payment->raw_payload = $intent->toArray();
            $payment->save();

            return response()->json([
                'order_id' => $order->id,
                'payment_intent_id' => $intent->id,
                'client_secret' => $intent->client_secret,
                'amount' => $total_ttc,
                'shipping_cost' => $shipping_cost,
                'currency' => 'EUR',
            ]);
        });
    }

    /**
     * Stripe webhook - Payment events
     * Endpoint called by Stripe
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
        $endpoint_secret = config('services.stripe.webhook_secret', env('STRIPE_WEBHOOK_SECRET'));
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');

        try {
            $event = Webhook::constructEvent($payload, $signature, $endpoint_secret);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }

        $provider = 'stripe';
        $provider_event_id = $event->id;

        // idempotency
        try {
            $webhook_event = WebhookEvent::firstOrCreate(
                ['provider' => $provider, 'provider_event_id' => $provider_event_id],
                ['event_type' => $event->type, 'payload' => $event->toArray(), 'processed_at' => null]
            );
        } catch (QueryException $e) {
            $webhook_event = WebhookEvent::where('provider', $provider)->where('provider_event_id', $provider_event_id)->first();
        }

        if ($webhook_event && $webhook_event->processed_at) {
            return response()->json(['status' => 'already_processed']);
        }

        if ($webhook_event && empty($webhook_event->payload)) {
            $webhook_event->payload = $event->toArray();
            $webhook_event->save();
        }

        try {
            if ($event->type === 'payment_intent.succeeded') {
                $payment_intent = $event->data->object;
                $order_id = $payment_intent->metadata->order_id ?? null;
                $payment_id = $payment_intent->metadata->payment_id ?? null;

                DB::transaction(function () use ($payment_intent, $order_id, $payment_id) {
                    if ($payment_id && ($payment = Payment::find($payment_id))) {
                        $payment->status = 'success';
                        $payment->raw_payload = $payment_intent->toArray();
                        $payment->save();
                    }

                    if ($order_id && ($order = Order::with(['user', 'items.product'])->find($order_id))) {
                        $order->payment_status = 'paid';
                        $order->order_status = 'processing';
                        $order->save();

                        // clear DB cart
                        if ($order->user) {
                            $order->user->cart?->items()->delete();
                        } elseif ($order->guest_token) {
                            Cart::where('guest_token', $order->guest_token)->first()?->items()->delete();
                        }

                        // confirmation email (idempotent)
                        if (! $order->paid_email_sent_at && ($order->user || $order->email)) {
                            if ($order->user) {
                                $order->user->notify(new OrderConfirmed($order));
                            } else {
                                Notification::route('mail', $order->email)->notify(new OrderConfirmed($order));
                            }
                            $order->paid_email_sent_at = now();
                            $order->save();
                        }

                        // Decrement stock — FIFO
                        foreach ($order->items as $item) {
                            $lot = StockLot::where('product_id', $item->product_id)
                                ->when($item->product_option_id, fn ($q) => $q->where('product_option_id', $item->product_option_id), fn ($q) => $q->whereNull('product_option_id'))
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
                                    'reason' => "Vente — Commande #$order->id",
                                ]);

                                $item->lot_id = $lot->id;
                                $item->save();
                            }
                        }
                    }
                });
            }

            if ($event->type === 'payment_intent.payment_failed') {
                $payment_intent = $event->data->object;
                $order_id = $payment_intent->metadata->order_id ?? null;
                $payment_id = $payment_intent->metadata->payment_id ?? null;

                DB::transaction(function () use ($payment_intent, $order_id, $payment_id) {
                    if ($payment_id && ($payment = Payment::find($payment_id))) {
                        $payment->status = 'failed';
                        $payment->raw_payload = $payment_intent->toArray();
                        $payment->save();
                    }

                    if ($order_id && ($order = Order::find($order_id))) {
                        $order->payment_status = 'failed';
                        $order->order_status = 'new';
                        $order->save();
                    }
                });
            }

            $webhook_event->processed_at = now();
            $webhook_event->save();

            return response()->json(['status' => 'success']);
        } catch (Throwable $e) {
            if ($webhook_event) {
                $webhook_event->failures()->create([
                    'error_message' => $e->getMessage(),
                    'retry_count' => 0,
                ]);
            }

            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
