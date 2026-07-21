<?php

namespace Tests\Feature\Cart;

use App\Models\Cart;
use App\Models\CustomProductSession;
use App\Models\Product;
use App\Models\StockLot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class GuestCartTest extends TestCase
{
    use RefreshDatabase;

    private function product(int $stock = 50): Product
    {
        $product = Product::factory()->create();
        StockLot::factory()->create(['product_id' => $product->id, 'quantity' => $stock]);

        return $product;
    }

    public function test_guest_can_add_and_view_cart_with_guest_token(): void
    {
        $product = $this->product();

        $this->withHeader('X-Guest-Cart-Token', 'guest-abc')
            ->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 2])
            ->assertOk()
            ->assertJsonPath('count', 2);

        $this->assertDatabaseHas('carts', ['guest_token' => 'guest-abc']);

        $this->withHeader('X-Guest-Cart-Token', 'guest-abc')
            ->getJson('/api/cart')
            ->assertOk()
            ->assertJsonPath('count', 2);
    }

    public function test_two_guest_tokens_do_not_share_a_cart(): void
    {
        $product = $this->product();
        $this->withHeader('X-Guest-Cart-Token', 'guest-a')->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 1]);

        $this->withHeader('X-Guest-Cart-Token', 'guest-b')
            ->getJson('/api/cart')
            ->assertOk()
            ->assertJsonPath('count', 0);
    }

    public function test_guest_cannot_update_or_delete_another_guests_cart_item(): void
    {
        $product = $this->product();
        $this->withHeader('X-Guest-Cart-Token', 'guest-a')->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 1]);

        $item_id = Cart::where('guest_token', 'guest-a')->first()->items()->first()->id;

        $this->withHeader('X-Guest-Cart-Token', 'guest-b')
            ->patchJson("/api/cart/items/$item_id", ['quantity' => 5])
            ->assertNotFound();

        $this->withHeader('X-Guest-Cart-Token', 'guest-b')
            ->deleteJson("/api/cart/items/$item_id")
            ->assertNotFound();
    }

    public function test_guest_cannot_attach_another_guests_or_a_users_customization_session(): void
    {
        $product = Product::factory()->create(['is_customizable' => true]);
        StockLot::factory()->create(['product_id' => $product->id, 'quantity' => 10]);

        $owner = User::factory()->create();
        $user_session = CustomProductSession::create([
            'user_id' => $owner->id,
            'product_id' => $product->id,
            'status' => 'ready',
            'configuration' => [],
        ]);

        $this->withHeader('X-Guest-Cart-Token', 'guest-a')
            ->postJson('/api/cart/items', [
                'product_id' => $product->id,
                'quantity' => 1,
                'custom_product_session_id' => $user_session->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['custom_product_session_id']);

        $other_guest_session = CustomProductSession::create([
            'guest_token' => 'guest-b',
            'product_id' => $product->id,
            'status' => 'ready',
            'configuration' => [],
        ]);

        $this->withHeader('X-Guest-Cart-Token', 'guest-a')
            ->postJson('/api/cart/items', [
                'product_id' => $product->id,
                'quantity' => 1,
                'custom_product_session_id' => $other_guest_session->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['custom_product_session_id']);
    }

    public function test_guest_can_attach_own_customization_session(): void
    {
        $product = Product::factory()->create(['is_customizable' => true]);
        StockLot::factory()->create(['product_id' => $product->id, 'quantity' => 10]);

        $session = CustomProductSession::create([
            'guest_token' => 'guest-a',
            'product_id' => $product->id,
            'status' => 'ready',
            'configuration' => [],
        ]);

        $this->withHeader('X-Guest-Cart-Token', 'guest-a')
            ->postJson('/api/cart/items', [
                'product_id' => $product->id,
                'quantity' => 1,
                'custom_product_session_id' => $session->id,
            ])
            ->assertOk()
            ->assertJsonPath('count', 1);

        $this->assertDatabaseHas('cart_items', [
            'custom_product_session_id' => $session->id,
        ]);
        $session->refresh();
        $this->assertEquals('added_to_cart', $session->status);
    }

    public function test_login_merges_guest_cart_into_user_cart(): void
    {
        $product = $this->product();
        $user = User::factory()->create([
            'email' => 'merge@test.fr',
            'password' => Hash::make('Password123!'),
        ]);

        // Existing item in user's own cart
        $user_cart = Cart::create(['user_id' => $user->id]);
        $user_cart->items()->create(['product_id' => $product->id, 'quantity' => 1]);

        // Same product added as a guest
        $other_product = $this->product();
        $this->withHeader('X-Guest-Cart-Token', 'guest-merge')->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 2]);
        $this->withHeader('X-Guest-Cart-Token', 'guest-merge')->postJson('/api/cart/items', ['product_id' => $other_product->id, 'quantity' => 1]);

        $this->postJson('/api/login', [
            'email' => 'merge@test.fr',
            'password' => 'Password123!',
            'guest_cart_token' => 'guest-merge',
        ])->assertOk();

        $this->assertDatabaseMissing('carts', ['guest_token' => 'guest-merge']);
        $user_cart->refresh();
        $this->assertEquals(3, $user_cart->items()->where('product_id', $product->id)->value('quantity'));
        $this->assertEquals(1, $user_cart->items()->where('product_id', $other_product->id)->value('quantity'));
    }

    public function test_register_merges_guest_cart_into_new_user_cart(): void
    {
        $product = $this->product();
        $this->withHeader('X-Guest-Cart-Token', 'guest-register')->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 3]);

        $this->postJson('/api/register', [
            'firstname' => 'Alice',
            'lastname' => 'Dupont',
            'email' => 'alice-guest@test.fr',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'guest_cart_token' => 'guest-register',
        ])->assertCreated();

        $this->assertDatabaseMissing('carts', ['guest_token' => 'guest-register']);
        $user = User::where('email', 'alice-guest@test.fr')->firstOrFail();
        $cart = Cart::where('user_id', $user->id)->firstOrFail();
        $this->assertEquals(3, $cart->items()->where('product_id', $product->id)->value('quantity'));
    }

    public function test_purge_command_deletes_carts_inactive_for_7_days(): void
    {
        $product = $this->product();
        $stale_guest_cart = Cart::create(['guest_token' => 'stale-guest']);
        $stale_guest_cart->items()->create(['product_id' => $product->id, 'quantity' => 1]);
        $stale_guest_cart->timestamps = false;
        $stale_guest_cart->updated_at = now()->subDays(8);
        $stale_guest_cart->save();
        $user = User::factory()->create();
        $stale_user_cart = Cart::create(['user_id' => $user->id]);
        $stale_user_cart->items()->create(['product_id' => $product->id, 'quantity' => 1]);
        $stale_user_cart->timestamps = false;
        $stale_user_cart->updated_at = now()->subDays(8);
        $stale_user_cart->save();
        $fresh_guest_cart = Cart::create(['guest_token' => 'fresh-guest']);
        $fresh_guest_cart->items()->create(['product_id' => $product->id, 'quantity' => 1]);
        $this->artisan('carts:purge-abandoned')->assertSuccessful();
        $this->assertDatabaseMissing('carts', ['id' => $stale_guest_cart->id]);
        $this->assertDatabaseMissing('carts', ['id' => $stale_user_cart->id]);
        $this->assertDatabaseMissing('cart_items', ['cart_id' => $stale_guest_cart->id]);
        $this->assertDatabaseHas('carts', ['id' => $fresh_guest_cart->id]);
    }
}
