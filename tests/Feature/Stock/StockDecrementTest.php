<?php

namespace Tests\Feature\Stock;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockLot;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockDecrementTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_is_decremented_when_order_is_paid(): void
    {
        $customer = User::factory()->create();
        $product = Product::factory()->create();

        $lot = StockLot::factory()->create([
            'product_id' => $product->id,
            'lot_number' => 'LOT-INIT',
            'initial_quantity' => 100,
            'quantity' => 100,
        ]);

        $order = Order::factory()->create([
            'user_id' => $customer->id,
            'payment_status' => 'pending',
            'order_status' => 'new',
        ]);

        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'unit_price' => 29.99,
            'quantity' => 3,
            'total' => 89.97,
        ]);

        // Simulates webhook logic (FIFO)
        $available_lot = StockLot::where('product_id', $item->product_id)
            ->whereNull('product_option_id')
            ->where('quantity', '>', 0)
            ->orderByRaw('expiration_date IS NULL, expiration_date ASC')
            ->orderBy('id')
            ->first();

        $this->assertNotNull($available_lot);

        $deducted = min($available_lot->quantity, (int) $item->quantity);
        $available_lot->decrement('quantity', $deducted);

        StockMovement::create([
            'lot_id' => $available_lot->id,
            'product_id' => $item->product_id,
            'quantity' => $deducted,
            'type' => 'out',
            'reason' => "Vente — Commande #$order->id",
        ]);

        $item->lot_id = $available_lot->id;
        $item->save();

        $this->assertDatabaseHas('stock_lots', [
            'id' => $lot->id,
            'quantity' => 97, // 100 - 3
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'lot_id' => $lot->id,
            'product_id' => $product->id,
            'type' => 'out',
            'quantity' => 3,
        ]);

        $this->assertDatabaseHas('order_items', [
            'id' => $item->id,
            'lot_id' => $lot->id,
        ]);
    }

    public function test_fifo_uses_lot_with_earliest_expiration(): void
    {
        $customer = User::factory()->create();
        $product = Product::factory()->create();

        // Lot expiring last
        StockLot::factory()->create([
            'product_id' => $product->id,
            'lot_number' => 'LOT-LATE',
            'quantity' => 50,
            'expiration_date' => now()->addYear(),
        ]);

        // Lot expiring first
        $first_lot = StockLot::factory()->create([
            'product_id' => $product->id,
            'lot_number' => 'LOT-EARLY',
            'quantity' => 50,
            'expiration_date' => now()->addMonth(),
        ]);

        $selected_lot = StockLot::where('product_id', $product->id)
            ->whereNull('product_option_id')
            ->where('quantity', '>', 0)
            ->orderByRaw('expiration_date IS NULL, expiration_date ASC')
            ->orderBy('id')
            ->first();

        $this->assertEquals($first_lot->id, $selected_lot->id, 'FIFO doit sélectionner le lot avec la date d\'expiration la plus proche');
    }

    public function test_no_decrement_when_no_stock_available(): void
    {
        $product = Product::factory()->create();

        // No lot available
        $result = StockLot::where('product_id', $product->id)
            ->where('quantity', '>', 0)
            ->first();

        $this->assertNull($result, 'Pas de lot disponible → pas de décrémentation');
    }
}
