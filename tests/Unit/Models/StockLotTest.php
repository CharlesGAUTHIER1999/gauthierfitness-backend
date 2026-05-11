<?php

namespace Tests\Unit\Models;

use App\Models\Product;
use App\Models\StockLot;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockLotTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_lot_belongs_to_product(): void
    {
        $product = Product::factory()->create();
        $lot     = StockLot::factory()->create(['product_id' => $product->id]);

        $this->assertEquals($product->id, $lot->product->id);
    }

    public function test_expiration_date_is_cast_to_date(): void
    {
        $product = Product::factory()->create();
        $lot     = StockLot::factory()->create([
            'product_id'      => $product->id,
            'expiration_date' => '2027-12-31',
        ]);

        $this->assertInstanceOf(Carbon::class, $lot->expiration_date);
    }

    public function test_quantity_can_be_decremented(): void
    {
        $product = Product::factory()->create();
        $lot     = StockLot::factory()->create([
            'product_id' => $product->id,
            'quantity'   => 50,
        ]);

        $lot->decrement('quantity', 10);

        $this->assertEquals(40, $lot->fresh()->quantity);
    }

    public function test_lot_has_movements_relationship(): void
    {
        $product = Product::factory()->create();
        $lot     = StockLot::factory()->create(['product_id' => $product->id]);

        $this->assertInstanceOf(
            HasMany::class,
            $lot->movements()
        );
    }

    public function test_fillable_fields_include_required_columns(): void
    {
        $lot = new StockLot();

        $this->assertContains('product_id', $lot->getFillable());
        $this->assertContains('lot_number', $lot->getFillable());
        $this->assertContains('quantity', $lot->getFillable());
    }
}
