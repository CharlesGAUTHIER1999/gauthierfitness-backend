<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\WebhookEvent;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Stripe\StripeClient;
use Stripe\Webhook;
use Throwable;

class StripeController extends Controller
{
    public function createPaymentIntent(Request $request): JsonResponse
    {
        $user = $request->user();

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

        $cartItems = $user->cartItems()
            ->with(['product', 'option'])
            ->get();

        if ($cartItems->isEmpty()) {
            return response()->json(['message' => 'Panier vide'], 400);
        }

        $stripe = new StripeClient(config('services.stripe.secret', env('STRIPE_SECRET')));

        return DB::transaction(function () use ($user, $cartItems, $data, $stripe) {
            // 1) Calcul total depuis DB (source de vérité)
            $totalTtc = 0.0;
            $totalHt = 0.0;

            foreach ($cartItems as $ci) {
                $product = $ci->product;

                if (!$product) {
                    abort(422, 'Produit introuvable dans le panier.');
                }

                $unitTtc = $ci->option?->price_ttc ?? $product->price_ttc;
                $unitHt  = $product->price_ht;
                $qty = (int) $ci->quantity;

                $totalTtc += ((float) $unitTtc) * $qty;
                $totalHt  += ((float) $unitHt) * $qty;
            }

            $totalTtc = round($totalTtc, 2);
            $totalHt  = round($totalHt, 2);

            // 2) Crée la commande + items
            $order = Order::create([
                'user_id' => $user->id,
                'total_ht' => $totalHt,
                'total_ttc' => $totalTtc,
                'payment_status' => 'pending',
                'order_status' => 'new',
            ]);

            foreach ($cartItems as $ci) {
                $product = $ci->product;

                if (!$product) {
                    abort(422, 'Produit introuvable dans le panier.');
                }

                $unitTtc = $ci->option?->price_ttc ?? $product->price_ttc;
                $qty = (int) $ci->quantity;

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'product_option_id' => $ci->product_option_id,
                    'lot_id' => null,
                    'unit_price' => round((float) $unitTtc, 2),
                    'quantity' => $qty,
                    'total' => round(((float) $unitTtc) * $qty, 2),
                ]);
            }

            // 3) Crée la ligne payment
            $payment = Payment::create([
                'order_id' => $order->id,
                'provider' => 'stripe',
                'provider_payment_id' => null,
                'amount' => $totalTtc,
                'status' => 'pending',
                'raw_payload' => null,
            ]);

            // 4) Stripe PaymentIntent
            /** @noinspection PhpArrayKeyDoesNotMatchArrayShapeInspection */
            $intent = $stripe->paymentIntents->create([
                'amount' => (int) round($totalTtc * 100),
                'currency' => 'eur',
                'automatic_payment_methods' => [
                    'enabled' => true,
                ],
                'metadata' => [
                    'order_id'   => (string) $order->id,
                    'user_id'    => (string) $user->id,
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

    public function webhook(Request $request): JsonResponse
    {
        $endpointSecret = env('STRIPE_WEBHOOK_SECRET');
        $payload = $request->getContent();
        $sig = $request->header('Stripe-Signature');

        try {
            $event = Webhook::constructEvent($payload, $sig, $endpointSecret);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }

        // (Optionnel) Anti double-traitement simple (à terme: mieux avec colonne event_id unique)
        $existing = WebhookEvent::where('provider', 'stripe')
            ->where('event_type', $event->type)
            ->where('payload->id', $event->id)
            ->first();

        if ($existing && $existing->processed_at) {
            return response()->json(['status' => 'already_processed']);
        }

        $we = WebhookEvent::create([
            'provider' => 'stripe',
            'event_type' => $event->type,
            'payload' => $event->toArray(),
            'processed_at' => null,
        ]);

        try {
            if ($event->type === 'payment_intent.succeeded') {
                $pi = $event->data->object;

                $orderId = $pi->metadata->order_id ?? null;
                $paymentId = $pi->metadata->payment_id ?? null;

                DB::transaction(function () use ($pi, $orderId, $paymentId) {
                    if ($paymentId) {
                        $payment = Payment::find($paymentId);
                        if ($payment) {
                            $payment->status = 'success';
                            $payment->raw_payload = $pi->toArray();
                            $payment->save();
                        }
                    }

                    if ($orderId) {
                        $order = Order::find($orderId);
                        if ($order) {
                            $order->payment_status = 'paid';
                            $order->order_status = 'processing';
                            $order->save();

                            // Vide le panier de l'user après paiement confirmé
                            $order->user?->cartItems()?->delete();
                        }
                    }
                });
            }

            if ($event->type === 'payment_intent.payment_failed') {
                $pi = $event->data->object;

                $orderId = $pi->metadata->order_id ?? null;
                $paymentId = $pi->metadata->payment_id ?? null;

                DB::transaction(function () use ($pi, $orderId, $paymentId) {
                    if ($paymentId) {
                        $payment = Payment::find($paymentId);
                        if ($payment) {
                            $payment->status = 'failed';
                            $payment->raw_payload = $pi->toArray();
                            $payment->save();
                        }
                    }

                    if ($orderId) {
                        $order = Order::find($orderId);
                        if ($order) {
                            $order->payment_status = 'failed';
                            $order->order_status = 'new';
                            $order->save();
                        }
                    }
                });
            }

            $we->processed_at = now();
            $we->save();

            return response()->json(['status' => 'success']);
        } catch (Throwable $e) {
            // Tu peux aussi loguer ici: logger()->error(...)
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
