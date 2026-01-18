<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Stripe\Checkout\Session as CheckoutSession;
use Stripe\Exception\ApiErrorException;
use Stripe\Stripe;
use Stripe\Webhook;

class StripeController extends Controller
{
    /**
     * @throws ApiErrorException
     */
    public function createCheckoutSession(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => 'required|array',
        ]);

        Stripe::setApiKey(env('STRIPE_SECRET'));

        $lineItems = [];
        foreach ($validated['items'] as $item) {
            $lineItems[] = [
                'price_data' => [
                    'currency' => 'eur',
                    'product_data' => [
                        'name' => $item['name'],
                    ],
                    'unit_amount' => intval($item['price_ttc'] * 100),
                ],
                'quantity' => $item['quantity'],
            ];
        }

        $session = CheckoutSession::create([
            'payment_method_types' => ['card'],
            'line_items' => $lineItems,
            'mode' => 'payment',
            'success_url' => env('FRONTEND_URL') . '/payment-success?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => env('FRONTEND_URL') . '/payment-cancel',
        ]);

        return response()->json([
            'id' => $session->id,
            'url' => $session->url,
        ]);
    }

    public function webhook(Request $request): JsonResponse
    {
        $endpointSecret = env('STRIPE_WEBHOOK_SECRET');
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');

        try {
            $event = Webhook::constructEvent($payload, $signature, $endpointSecret);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }

        if ($event->type === 'checkout.session.completed') {
            $session = $event->data->object;

            DB::transaction(function () use ($session) {
                $order = Order::create([
                    'user_id' => $session->client_reference_id ?? 1,
                    'total_ht' => 0,
                    'total_ttc' => $session->amount_total / 100,
                    'payment_status' => 'paid',
                    'order_status' => 'processing',
                ]);

                $lineItems = CheckoutSession::retrieve(
                    $session->id,
                    ['expand' => ['line_items']]
                )->line_items->data;

                foreach ($lineItems as $item) {
                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => null,
                        'lot_id' => null,
                        'unit_price' => $item->amount_total / $item->quantity / 100,
                        'quantity' => $item->quantity,
                        'total' => $item->amount_total / 100,
                    ]);
                }
            });
        }
        return response()->json(['status' => 'success']);
    }
}