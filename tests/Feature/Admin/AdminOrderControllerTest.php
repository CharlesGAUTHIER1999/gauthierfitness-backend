<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminOrderControllerTest extends TestCase
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

    /* ── Stats ──────────────────────────────────────────────────── */

    public function test_admin_can_view_stats(): void
    {
        Sanctum::actingAs($this->admin);

        $this->getJson('/api/admin/stats')
            ->assertOk()
            ->assertJsonStructure([
                'products' => ['total', 'active', 'customizable'],
                'orders' => ['total', 'by_status', 'revenue', 'revenue_month', 'this_week'],
                'stock' => ['out_of_stock', 'low_stock'],
            ]);
    }

    public function test_non_admin_cannot_view_stats(): void
    {
        Sanctum::actingAs($this->customer);

        $this->getJson('/api/admin/stats')->assertForbidden();
    }

    /* ── Index ──────────────────────────────────────────────────── */

    public function test_admin_can_list_orders(): void
    {
        Sanctum::actingAs($this->admin);

        Order::factory()->count(3)->create(['user_id' => $this->customer->id]);

        $this->getJson('/api/admin/orders')
            ->assertOk()
            ->assertJsonStructure(['data', 'total']);
    }

    public function test_admin_can_filter_orders_by_status(): void
    {
        Sanctum::actingAs($this->admin);

        Order::factory()->create(['user_id' => $this->customer->id, 'order_status' => 'new']);
        Order::factory()->create(['user_id' => $this->customer->id, 'order_status' => 'shipped']);

        $response = $this->getJson('/api/admin/orders?status=new');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    /* ── Show ───────────────────────────────────────────────────── */

    public function test_admin_can_view_order_detail(): void
    {
        Sanctum::actingAs($this->admin);

        $order = Order::factory()->create(['user_id' => $this->customer->id]);

        $this->getJson("/api/admin/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('id', $order->id);
    }

    /* ── Update status ──────────────────────────────────────────── */

    public function test_admin_can_update_order_status(): void
    {
        Sanctum::actingAs($this->admin);

        $order = Order::factory()->create([
            'user_id' => $this->customer->id,
            'order_status' => 'new',
        ]);

        $this->patchJson("/api/admin/orders/{$order->id}/status", [
            'order_status' => 'processing',
        ])->assertOk()
            ->assertJsonPath('order.order_status', 'processing');
    }

    public function test_update_status_rejects_invalid_value(): void
    {
        Sanctum::actingAs($this->admin);

        $order = Order::factory()->create([
            'user_id' => $this->customer->id,
            'order_status' => 'new',
        ]);

        $this->patchJson("/api/admin/orders/{$order->id}/status", [
            'order_status' => 'invalid_status',
        ])->assertUnprocessable();
    }

    public function test_same_status_returns_unchanged(): void
    {
        Sanctum::actingAs($this->admin);

        $order = Order::factory()->create([
            'user_id' => $this->customer->id,
            'order_status' => 'new',
        ]);

        $this->patchJson("/api/admin/orders/{$order->id}/status", [
            'order_status' => 'new',
        ])->assertOk()
            ->assertJsonPath('message', 'Status unchanged');
    }
}
