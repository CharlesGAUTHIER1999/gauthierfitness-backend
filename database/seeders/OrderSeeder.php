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
    /** Create a sample order (with item, payment, and shipment) for each existing user. */
    public function run(): void
    {
        $users = User::all();

        // Avoid crashing if no product exists
        $products_count = Product::count();
        if ($products_count === 0) {
            $this->command?->warn('OrderSeeder: no product found, skipping.');

            return;
        }

        foreach ($users as $user) {

            $product = Product::inRandomOrder()->first();
            if (! $product) {
                continue;
            }
            $unit = (float) ($product->price_ttc ?? 0);
            $quantity = 1;

            $order = Order::create([
                'user_id' => $user->id,
                'total_ht' => 50,
                'total_ttc' => $unit * $quantity,
                'payment_status' => 'paid',
                'order_status' => 'delivered',
            ]);

            // Safe lot lookup
            $lot_id = $product->lots()->inRandomOrder()->value('id');

            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'lot_id' => $lot_id,
                'unit_price' => $unit,
                'quantity' => $quantity,
                'total' => $unit * $quantity,
            ]);

            Payment::create([
                'order_id' => $order->id,
                'provider' => 'stripe',
                'provider_payment_id' => 'pi_fake_'.rand(100000, 999999),
                'amount' => $unit * $quantity,
                'status' => 'success',
            ]);

            // Safe address fallback
            $address = $user->address ? trim($user->address.', '.($user->zip ?? '').' '.($user->city ?? '')) : '10 Rue de la Paix, 75002 Paris';

            Shipment::create([
                'order_id' => $order->id,
                'address' => $address,
                'carrier' => 'UPS',
                'tracking_url' => 'https://tracking.fake/'.rand(10000, 99999),
                'status' => 'delivered',
            ]);
        }
    }
}
