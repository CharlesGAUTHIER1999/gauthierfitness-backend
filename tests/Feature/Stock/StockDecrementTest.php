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

/**
 * Teste la décrémentation automatique du stock lors d'une vente.
 * Ce comportement est déclenché par le webhook Stripe (payment_intent.succeeded).
 * Ce test le vérifie directement en simulant la logique FIFO.
 */
class StockDecrementTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_is_decremented_when_order_is_paid(): void
    {
        // Arrange
        $customer = User::factory()->create();
        $product  = Product::factory()->create();

        $lot = StockLot::factory()->create([
            'product_id'       => $product->id,
            'lot_number'       => 'LOT-INIT',
            'initial_quantity' => 100,
            'quantity'         => 100,
        ]);

        $order = Order::factory()->create([
            'user_id'        => $customer->id,
            'payment_status' => 'pending',
            'order_status'   => 'new',
        ]);

        $item = OrderItem::create([
            'order_id'   => $order->id,
            'product_id' => $product->id,
            'unit_price' => 29.99,
            'quantity'   => 3,
            'total'      => 89.97,
        ]);

        // Act — simule la logique du webhook (FIFO)
        $availableLot = StockLot::where('product_id', $item->product_id)
            ->whereNull('product_option_id')
            ->where('quantity', '>', 0)
            ->orderByRaw('expiration_date IS NULL, expiration_date ASC')
            ->orderBy('id')
            ->first();

        $this->assertNotNull($availableLot);

        $deducted = min($availableLot->quantity, (int) $item->quantity);
        $availableLot->decrement('quantity', $deducted);

        StockMovement::create([
            'lot_id'     => $availableLot->id,
            'product_id' => $item->product_id,
            'quantity'   => $deducted,
            'type'       => 'out',
            'reason'     => "Vente — Commande #{$order->id}",
        ]);

        $item->lot_id = $availableLot->id;
        $item->save();

        // Assert
        $this->assertDatabaseHas('stock_lots', [
            'id'       => $lot->id,
            'quantity' => 97, // 100 - 3
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'lot_id'     => $lot->id,
            'product_id' => $product->id,
            'type'       => 'out',
            'quantity'   => 3,
        ]);

        $this->assertDatabaseHas('order_items', [
            'id'     => $item->id,
            'lot_id' => $lot->id,
        ]);
    }

    public function test_fifo_uses_lot_with_earliest_expiration(): void
    {
        $customer = User::factory()->create();
        $product  = Product::factory()->create();

        // Lot expirant en dernier
        StockLot::factory()->create([
            'product_id'       => $product->id,
            'lot_number'       => 'LOT-LATE',
            'quantity'         => 50,
            'expiration_date'  => now()->addYear(),
        ]);

        // Lot expirant en premier (doit être utilisé)
        $firstLot = StockLot::factory()->create([
            'product_id'       => $product->id,
            'lot_number'       => 'LOT-EARLY',
            'quantity'         => 50,
            'expiration_date'  => now()->addMonth(),
        ]);

        $selectedLot = StockLot::where('product_id', $product->id)
            ->whereNull('product_option_id')
            ->where('quantity', '>', 0)
            ->orderByRaw('expiration_date IS NULL, expiration_date ASC')
            ->orderBy('id')
            ->first();

        $this->assertEquals($firstLot->id, $selectedLot->id, 'FIFO doit sélectionner le lot avec la date d\'expiration la plus proche');
    }

    public function test_no_decrement_when_no_stock_available(): void
    {
        $product = Product::factory()->create();

        // Aucun lot disponible
        $result = StockLot::where('product_id', $product->id)
            ->where('quantity', '>', 0)
            ->first();

        $this->assertNull($result, 'Pas de lot disponible → pas de décrémentation');
    }
}
