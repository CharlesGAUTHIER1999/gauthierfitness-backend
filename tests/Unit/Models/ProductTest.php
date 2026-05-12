<?php

namespace Tests\Unit\Models;

use App\Models\Product;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_scope_filters_inactive_products(): void
    {
        Product::factory()->create(['is_active' => true]);
        Product::factory()->create(['is_active' => false]);

        $this->assertCount(1, Product::active()->get());
    }

    public function test_slug_is_set_on_creation(): void
    {
        $product = Product::factory()->create(['name' => 'T-shirt Oversize']);

        $this->assertNotEmpty($product->slug);
    }

    public function test_price_is_cast_to_float(): void
    {
        $product = Product::factory()->create([
            'price_ttc' => '29.99',
            'price_ht' => '24.99',
        ]);

        $this->assertIsFloat($product->price_ttc);
        $this->assertIsFloat($product->price_ht);
    }

    public function test_is_active_is_cast_to_bool(): void
    {
        $product = Product::factory()->create(['is_active' => 1]);

        $this->assertIsBool($product->is_active);
        $this->assertTrue($product->is_active);
    }

    public function test_is_customizable_is_cast_to_bool(): void
    {
        $product = Product::factory()->create(['is_customizable' => false]);

        $this->assertIsBool($product->is_customizable);
        $this->assertFalse($product->is_customizable);
    }

    public function test_product_has_lots_relationship(): void
    {
        $product = Product::factory()->create();

        $this->assertInstanceOf(
            HasMany::class,
            $product->lots()
        );
    }

    public function test_product_has_options_relationship(): void
    {
        $product = Product::factory()->create();

        $this->assertInstanceOf(
            HasMany::class,
            $product->options()
        );
    }
}
