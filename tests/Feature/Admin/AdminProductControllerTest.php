<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminProductControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(['name' => 'admin'], ['label' => 'Administrateur']);
        $this->admin = User::factory()->create();
        $this->admin->roles()->attach($role->id);

        $this->customer = User::factory()->create();
    }

    /* ── Access control ─────────────────────────────────────────── */

    public function test_non_admin_cannot_list_products(): void
    {
        Sanctum::actingAs($this->customer);

        $this->getJson('/api/admin/products')->assertForbidden();
    }

    public function test_guest_cannot_list_products(): void
    {
        $this->getJson('/api/admin/products')->assertUnauthorized();
    }

    /* ── Index ──────────────────────────────────────────────────── */

    public function test_admin_can_list_products(): void
    {
        Sanctum::actingAs($this->admin);
        Product::factory()->count(3)->create();

        $this->getJson('/api/admin/products')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta' => ['total', 'per_page']]);
    }

    public function test_admin_can_search_products(): void
    {
        Sanctum::actingAs($this->admin);
        Product::factory()->create(['name' => 'T-shirt Oversize']);
        Product::factory()->create(['name' => 'Legging Sport']);

        $response = $this->getJson('/api/admin/products?search=oversize');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    /* ── Show ───────────────────────────────────────────────────── */

    public function test_admin_can_view_product(): void
    {
        Sanctum::actingAs($this->admin);
        $product = Product::factory()->create();

        $this->getJson("/api/admin/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $product->id);
    }

    /* ── Store ──────────────────────────────────────────────────── */

    public function test_admin_can_create_product(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/admin/products', [
            'name' => 'Nouveau produit test',
            'sku' => 'SKU-TEST-001',
            'price_ht' => 19.99,
            'price_ttc' => 23.99,
            'vat' => 20.0,
            'is_active' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('name', 'Nouveau produit test');

        $this->assertDatabaseHas('products', ['name' => 'Nouveau produit test']);
    }

    public function test_create_product_validates_required_fields(): void
    {
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/admin/products', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'price_ht', 'price_ttc', 'vat']);
    }

    public function test_create_product_rejects_a_sku_already_in_use(): void
    {
        Sanctum::actingAs($this->admin);
        Product::factory()->create(['sku' => 'SKU-DUP-001']);

        $this->postJson('/api/admin/products', [
            'name' => 'Autre produit',
            'sku' => 'SKU-DUP-001',
            'price_ht' => 10.0,
            'price_ttc' => 12.0,
            'vat' => 20.0,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sku']);
    }

    /* ── Update ─────────────────────────────────────────────────── */

    public function test_admin_can_update_product(): void
    {
        Sanctum::actingAs($this->admin);
        $product = Product::factory()->create(['name' => 'Old name']);

        $this->patchJson("/api/admin/products/{$product->id}", ['name' => 'New name'])
            ->assertOk()
            ->assertJsonPath('name', 'New name');
    }

    /* ── Toggle active ──────────────────────────────────────────── */

    public function test_admin_can_toggle_product_active(): void
    {
        Sanctum::actingAs($this->admin);
        $product = Product::factory()->create(['is_active' => true]);

        $this->patchJson("/api/admin/products/{$product->id}/toggle-active")
            ->assertOk()
            ->assertJsonPath('is_active', false);

        $this->assertDatabaseHas('products', ['id' => $product->id, 'is_active' => 0]);
    }

    /* ── Destroy ────────────────────────────────────────────────── */

    public function test_admin_can_delete_product(): void
    {
        Sanctum::actingAs($this->admin);
        $product = Product::factory()->create();

        $this->deleteJson("/api/admin/products/{$product->id}")
            ->assertOk();

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }
}
