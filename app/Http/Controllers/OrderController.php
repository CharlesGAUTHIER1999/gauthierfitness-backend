<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(
            $request->user()->orders()->with('items.product')->get()
        );
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $cart = $user->cartItems()->with('product')->get();

        if ($cart->count() === 0) {
            return response()->json(['message' => 'Panier vide'], 400);
        }

        $order = Order::create([
            'user_id' => $user->id,
            'total_ht' => $cart->sum(fn($i) => $i->product->price_ht * $i->quantity),
            'total_ttc' => $cart->sum(fn($i) => $i->product->price_ttc * $i->quantity),
        ]);

        foreach ($cart as $item) {
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'unit_price' => $item->product->price_ttc,
                'total' => $item->product->price_ttc * $item->quantity,
            ]);
        }

        $user->cartItems()->delete();
        return response()->json($order->load('items.product'));
    }
}

