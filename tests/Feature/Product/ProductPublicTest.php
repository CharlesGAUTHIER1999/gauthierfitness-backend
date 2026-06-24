<?php

namespace Tests\Feature\Product;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductPublicTest extends TestCase
{
    use RefreshDatabase;

    /* ── Index ─────────────────────────────────────────────────── */

    public function test_guest_can_list_active_products(): void
    {
        Product::factory()->count(3)->create(['is_active' => true]);

        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_inactive_products_are_hidden_from_listing(): void
    {
        Product::factory()->count(2)->create(['is_active' => true]);
        Product::factory()->count(3)->create(['is_active' => false]);

        $response = $this->getJson('/api/products')->assertOk();

        $this->assertCount(2, $response->json('data'));
    }

    public function test_listing_respects_per_page_query_param(): void
    {
        Product::factory()->count(15)->create(['is_active' => true]);

        $this->getJson('/api/products?per_page=5')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 5);
    }

    public function test_listing_clamps_per_page_to_maximum(): void
    {
        Product::factory()->count(5)->create(['is_active' => true]);

        // per_page > 60 doit être clampé à 60
        $this->getJson('/api/products?per_page=999')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 60);
    }

    public function test_listing_filters_by_category(): void
    {
        $cat = new Category(['name' => 'T-shirts']);
        $cat->slug = 't-shirts';
        $cat->type = 'clothing';
        $cat->save();

        $matchingProduct = Product::factory()->create(['is_active' => true]);
        $matchingProduct->categories()->attach($cat->id);

        Product::factory()->create(['is_active' => true]);

        $response = $this->getJson('/api/products?category=t-shirts')->assertOk();

        $this->assertCount(1, $response->json('data'));
    }

    /* ── Show ──────────────────────────────────────────────────── */

    public function test_guest_can_view_product_by_slug(): void
    {
        $product = Product::factory()->create([
            'slug' => 'tshirt-noir',
            'is_active' => true,
        ]);

        $this->getJson("/api/products/{$product->slug}")
            ->assertOk()
            ->assertJsonPath('data.id', $product->id);
    }

    public function test_product_show_returns_404_for_unknown_slug(): void
    {
        $this->getJson('/api/products/inexistant-xyz')->assertNotFound();
    }
}
