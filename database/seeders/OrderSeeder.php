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
    public function run(): void
    {
        $users = User::all();

        // ✅ éviter crash si aucun produit
        $productsCount = Product::count();
        if ($productsCount === 0) {
            $this->command?->warn('OrderSeeder: aucun produit trouvé, skip.');
            return;
        }

        foreach ($users as $user) {

            $product = Product::inRandomOrder()->first();
            if (!$product) continue;

            $unit = (float) ($product->price_ttc ?? 0);
            $qty  = 1;

            $order = Order::create([
                'user_id' => $user->id,
                'total_ht' => 50,
                'total_ttc' => $unit * $qty,
                'payment_status' => 'paid',
                'order_status' => 'delivered',
            ]);

            // ✅ lot safe (pas de ->id sur null)
            $lotId = $product->lots()->inRandomOrder()->value('id');

            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'lot_id' => $lotId,
                'unit_price' => $unit,
                'quantity' => $qty,
                'total' => $unit * $qty,
            ]);

            Payment::create([
                'order_id' => $order->id,
                'provider' => 'stripe',
                'provider_payment_id' => 'pi_fake_' . rand(1000, 9999),
                'amount' => $unit * $qty,
                'status' => 'success',
            ]);

            // ✅ address safe
            $address = $user->address
                ? trim($user->address . ', ' . ($user->zip ?? '') . ' ' . ($user->city ?? ''))
                : '10 Rue de la Paix, 75002 Paris';

            Shipment::create([
                'order_id' => $order->id,
                'address' => $address,
                'carrier' => 'UPS',
                'tracking_url' => 'https://tracking.fake/' . rand(10000, 99999),
                'status' => 'delivered',
            ]);
        }
    }
}
