<?php

namespace App\Http\Controllers\Orders;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group(name: 'Orders', weight: 6)]
class OrderController extends Controller
{
    // Order history for the user
    public function index(Request $request): JsonResponse
    {
        $orders = $request->user()
            ->orders()
            ->with([
                'items.product:id,name,slug',
                'items.option:id,label,type',
                'payment:id,order_id,provider,status,amount,created_at',
                'shipment:id,order_id,firstname,lastname,address,zip,city,country,phone,carrier,tracking_url,status',
            ])
            ->latest()
            ->get();

        return response()->json($orders);
    }

    /**
     * Order detail
     *
     * @response 404 scenario="Order not found or belongs to another user" {}
     */
    public function show(Request $request, Order $order): JsonResponse
    {
        abort_unless($order->user_id === $request->user()->id, 404);

        $order->load([
            'items.product:id,name,slug',
            'items.option:id,label,type',
            'payment:id,order_id,provider,status,amount,created_at,provider_payment_id',
            'shipment:id,order_id,firstname,lastname,address,zip,city,country,phone,carrier,tracking_url,status',
        ]);

        return response()->json($order);
    }

    /**
     * Create an order (deprecated).
     *
     * @deprecated This endpoint always returns 405
     *
     * @response 405 {"message": "Use POST /payment/intent to create an order."}
     */
    public function store(): JsonResponse
    {
        return response()->json(['message' => 'Use POST /payment/intent to create an order.'], 405);
    }
}
