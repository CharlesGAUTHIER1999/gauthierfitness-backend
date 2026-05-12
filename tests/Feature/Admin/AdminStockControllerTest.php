<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\ProductOption;
use App\Models\Role;
use App\Models\StockLot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminStockControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $role        = Role::firstOrCreate(['name' => 'admin'], ['label' => 'Administrateur']);
        $this->admin = User::factory()->create();
        $this->admin->roles()->attach($role->id);
    }

    /* ── List (vue globale) ─────────────────────────────────────── */

    public function test_admin_can_view_stock_list(): void
    {
        Sanctum::actingAs($this->admin);
        Product::factory()->count(2)->create();

        $this->getJson('/api/admin/stock')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'name']]]);
    }

    /* ── Index (par produit) ────────────────────────────────────── */

    public function test_admin_can_view_product_stock_detail(): void
    {
        Sanctum::actingAs($this->admin);
        $product = Product::factory()->create();

        $this->getJson("/api/admin/products/{$product->id}/stock")
            ->assertOk()
            ->assertJsonStructure([
                'product_id',
                'product_name',
                'global_stock' => ['total_qty', 'lots'],
                'option_stocks',
            ]);
    }

    /* ── Store (réappro) ────────────────────────────────────────── */

    public function test_admin_can_add_stock(): void
    {
        Sanctum::actingAs($this->admin);
        $product = Product::factory()->create();

        $response = $this->postJson("/api/admin/products/{$product->id}/stock", [
            'lot_number' => 'LOT-TEST-001',
            'quantity'   => 50,
        ]);

        $response->assertCreated()
            ->assertJsonPath('lot_number', 'LOT-TEST-001')
            ->assertJsonPath('quantity', 50);

        $this->assertDatabaseHas('stock_lots', [
            'product_id' => $product->id,
            'quantity'   => 50,
            'lot_number' => 'LOT-TEST-001',
        ]);

        // Vérifier le mouvement 'in' créé automatiquement
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type'       => 'in',
            'quantity'   => 50,
        ]);
    }

    public function test_add_stock_validates_required_fields(): void
    {
        Sanctum::actingAs($this->admin);
        $product = Product::factory()->create();

        $this->postJson("/api/admin/products/{$product->id}/stock", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['lot_number', 'quantity']);
    }

    public function test_add_stock_rejects_zero_quantity(): void
    {
        Sanctum::actingAs($this->admin);
        $product = Product::factory()->create();

        $this->postJson("/api/admin/products/{$product->id}/stock", [
            'lot_number' => 'LOT-ZERO',
            'quantity'   => 0,
        ])->assertUnprocessable();
    }

    /* ── Adjust (correction) ────────────────────────────────────── */

    public function test_admin_can_adjust_lot(): void
    {
        Sanctum::actingAs($this->admin);
        $product = Product::factory()->create();

        $lot = StockLot::factory()->create([
            'product_id' => $product->id,
            'quantity'   => 20,
        ]);

        $this->patchJson("/api/admin/stock/lots/{$lot->id}/adjust", [
            'quantity' => 15,
            'reason'   => 'Inventaire mensuel',
        ])->assertOk()
            ->assertJsonPath('quantity', 15);

        // Mouvement de correction avec delta -5
        $this->assertDatabaseHas('stock_movements', [
            'lot_id'   => $lot->id,
            'type'     => 'correction',
            'quantity' => -5,
        ]);
    }

    public function test_adjust_requires_reason(): void
    {
        Sanctum::actingAs($this->admin);
        $product = Product::factory()->create();
        $lot     = StockLot::factory()->create(['product_id' => $product->id, 'quantity' => 10]);

        $this->patchJson("/api/admin/stock/lots/{$lot->id}/adjust", [
            'quantity' => 5,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['reason']);
    }

    /* ── Movements history ──────────────────────────────────────── */

    public function test_admin_can_view_movement_history(): void
    {
        Sanctum::actingAs($this->admin);
        $product = Product::factory()->create();

        $this->getJson("/api/admin/products/{$product->id}/stock/movements")
            ->assertOk()
            ->assertJsonStructure(['data', 'total', 'per_page']);
    }

    /* ── Stock par option ───────────────────────────────────────── */

    public function test_admin_can_add_stock_for_specific_option(): void
    {
        Sanctum::actingAs($this->admin);
        $product = Product::factory()->create();
        $option  = ProductOption::create([
            'product_id' => $product->id,
            'type'       => 'size',
            'code'       => 'L',
            'label'      => 'Large',
        ]);

        $this->postJson("/api/admin/products/{$product->id}/stock", [
            'product_option_id' => $option->id,
            'lot_number'        => 'LOT-L-001',
            'quantity'          => 30,
        ])->assertCreated();

        $this->assertDatabaseHas('stock_lots', [
            'product_id'        => $product->id,
            'product_option_id' => $option->id,
            'quantity'          => 30,
        ]);
    }
}
