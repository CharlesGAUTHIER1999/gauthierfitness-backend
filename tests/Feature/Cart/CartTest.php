<?php

namespace Tests\Feature\Cart;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\CustomProductSession;
use App\Models\Product;
use App\Models\StockLot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    // Show

    public function test_authenticated_user_can_view_empty_cart(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/cart')
            ->assertOk()
            ->assertJsonStructure(['items', 'count', 'subtotal', 'currency'])
            ->assertJsonPath('count', 0)
            ->assertJsonPath('subtotal', 0);
    }

    public function test_unauthenticated_user_cannot_view_cart(): void
    {
        $this->getJson('/api/cart')->assertUnauthorized();
    }

    public function test_show_creates_cart_if_missing(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $this->assertDatabaseMissing('carts', ['user_id' => $user->id]);
        $this->getJson('/api/cart')->assertOk();
        $this->assertDatabaseHas('carts', ['user_id' => $user->id]);
    }

    // Add

    public function test_authenticated_user_can_add_product_to_cart(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        StockLot::factory()->create([
            'product_id' => $product->id,
            'quantity' => 50,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/cart/items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ])->assertOk()
            ->assertJsonPath('count', 2);

        $this->assertDatabaseHas('cart_items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ]);
    }

    public function test_add_to_cart_rejects_quantity_above_stock(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        StockLot::factory()->create([
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/cart/items', [
            'product_id' => $product->id,
            'quantity' => 10,
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Stock insuffisant');
    }

    public function test_add_to_cart_increments_existing_line(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        StockLot::factory()->create(['product_id' => $product->id, 'quantity' => 100]);
        Sanctum::actingAs($user);
        $this->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 1]);
        $this->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 2]);
        $this->assertDatabaseCount('cart_items', 1);
        $this->assertDatabaseHas('cart_items', [
            'product_id' => $product->id,
            'quantity' => 3,
        ]);
    }

    public function test_add_to_cart_with_customization_session_owned(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['is_customizable' => true]);
        StockLot::factory()->create(['product_id' => $product->id, 'quantity' => 10]);

        $session = CustomProductSession::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'status' => 'ready',
            'configuration' => [],
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
            'custom_product_session_id' => $session->id,
        ])->assertOk();

        $this->assertDatabaseHas('cart_items', [
            'product_id' => $product->id,
            'custom_product_session_id' => $session->id,
        ]);

        // The session transitions to added_to_cart
        $this->assertDatabaseHas('custom_product_sessions', [
            'id' => $session->id,
            'status' => 'added_to_cart',
        ]);
    }

    public function test_add_to_cart_with_other_user_session_fails(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $product = Product::factory()->create(['is_customizable' => true]);
        StockLot::factory()->create(['product_id' => $product->id, 'quantity' => 10]);

        $session = CustomProductSession::create([
            'user_id' => $other->id,
            'product_id' => $product->id,
            'status' => 'ready',
            'configuration' => [],
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
            'custom_product_session_id' => $session->id,
        ])->assertStatus(422);
    }

    // Update

    public function test_user_can_update_cart_item_quantity(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        StockLot::factory()->create(['product_id' => $product->id, 'quantity' => 100]);

        $cart = Cart::create(['user_id' => $user->id]);
        $item = CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        Sanctum::actingAs($user);
        $this->patchJson("/api/cart/items/{$item->id}", ['quantity' => 5])->assertOk();
        $this->assertDatabaseHas('cart_items', ['id' => $item->id, 'quantity' => 5]);
    }

    public function test_user_cannot_update_cart_item_of_another_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $product = Product::factory()->create();

        $cart = Cart::create(['user_id' => $other->id]);
        $item = CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        Sanctum::actingAs($user);
        $this->patchJson("/api/cart/items/{$item->id}", ['quantity' => 5])->assertNotFound();
    }

    // Destroy

    public function test_user_can_remove_cart_item(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        $cart = Cart::create(['user_id' => $user->id]);
        $item = CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        Sanctum::actingAs($user);
        $this->deleteJson("/api/cart/items/{$item->id}")->assertOk();
        $this->assertDatabaseMissing('cart_items', ['id' => $item->id]);
    }

    public function test_removing_cart_item_with_customization_releases_session(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['is_customizable' => true]);

        $session = CustomProductSession::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'status' => 'added_to_cart',
            'configuration' => [],
        ]);

        $cart = Cart::create(['user_id' => $user->id]);
        $item = CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'custom_product_session_id' => $session->id,
            'quantity' => 1,
        ]);

        Sanctum::actingAs($user);
        $this->deleteJson("/api/cart/items/{$item->id}")->assertOk();

        $this->assertDatabaseHas('custom_product_sessions', [
            'id' => $session->id,
            'status' => 'ready',
        ]);
    }
}
