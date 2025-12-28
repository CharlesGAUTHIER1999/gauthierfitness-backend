<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\User;

use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    public function run():void {
        $users = User::all();

        foreach ($users as $user) {
            $order = Order::create([
                'user_id' => $user->id,
                'total_ht' => 50,
                'total_ttc' => 60,
                'payment_status' => 'paid',
                'order_status' => 'delivered',
            ]);

            $product = Product::inRandomOrder()->first();

            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'lot_id' => $product->lots()->inRandomOrder()->first()->id ?? null,
                'unit_price' => $product->price_ttc,
                'quantity' => 1,
                'total' => $product->price_ttc,
            ]);

            Payment::create([
                'order_id' => $order->id,
                'provider' => 'stripe',
                'provider_payment_id' => 'pi_fake_' . rand(1000,9999),
                'amount' => $product->price_ttc,
                'status' => 'success',
            ]);

            Shipment::create([
                'order_id' => $order->id,
                'address' => $user->address,
                'carrier' => 'UPS',
                'tracking_url' => 'https://tracking.fake/' . rand(10000,99999),
                'status' => 'delivered',
            ]);
        }
    }
}
