<?php

namespace Tests\Feature\Order;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderClientTest extends TestCase
{
    use RefreshDatabase;

    /* ── Index ─────────────────────────────────────────────────── */

    public function test_user_can_list_own_orders(): void
    {
        $user = User::factory()->create();

        Order::factory()->count(3)->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $this->getJson('/api/orders')
            ->assertOk()
            ->assertJsonCount(3);
    }

    public function test_user_only_sees_own_orders_not_others(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Order::factory()->count(2)->create(['user_id' => $user->id]);
        Order::factory()->count(5)->create(['user_id' => $other->id]);

        Sanctum::actingAs($user);

        $this->getJson('/api/orders')
            ->assertOk()
            ->assertJsonCount(2);
    }

    public function test_unauthenticated_user_cannot_list_orders(): void
    {
        $this->getJson('/api/orders')->assertUnauthorized();
    }

    /* ── Show ──────────────────────────────────────────────────── */

    public function test_user_can_view_own_order_detail(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $this->getJson("/api/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('id', $order->id);
    }

    public function test_user_cannot_view_order_of_another_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $other->id]);

        Sanctum::actingAs($user);

        $this->getJson("/api/orders/{$order->id}")->assertNotFound();
    }

    /* ── Store (deprecated 405) ────────────────────────────────── */

    public function test_store_endpoint_returns_405(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/orders', [])
            ->assertStatus(405)
            ->assertJsonPath('message', 'Use POST /payment/intent to create an order.');
    }
}
