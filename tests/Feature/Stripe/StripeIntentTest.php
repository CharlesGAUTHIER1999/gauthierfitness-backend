<?php

namespace Tests\Feature\Stripe;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\CustomProductSession;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Stripe\PaymentIntent;
use Stripe\Service\PaymentIntentService;
use Stripe\StripeClient;
use Tests\TestCase;

class StripeIntentTest extends TestCase
{
    use RefreshDatabase;

    private function shippingPayload(string $method = 'standard'): array
    {
        return [
            'email' => 'alice@example.com',
            'shipping_method' => $method,
            'shipping' => [
                'firstname' => 'Alice',
                'lastname' => 'Dupont',
                'address' => '12 rue de la Paix',
                'zip' => '75002',
                'city' => 'Paris',
                'country' => 'FR',
                'phone' => '+33 6 12 34 56 78',
            ],
        ];
    }

    private function mockStripeReturning(string $intent_id, string $client_secret): void
    {
        // Real PaymentIntent
        $intent = PaymentIntent::constructFrom([
            'id' => $intent_id,
            'object' => 'payment_intent',
            'client_secret' => $client_secret,
            'amount' => 0,
            'currency' => 'eur',
            'status' => 'requires_payment_method',
        ]);

        $payment_intents = Mockery::mock(PaymentIntentService::class);
        $payment_intents->shouldReceive('create')->andReturn($intent);
        $client = Mockery::mock(StripeClient::class);
        $client->paymentIntents = $payment_intents;
        $this->app->instance(StripeClient::class, $client);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /* Guest checkout */
    public function test_request_without_auth_or_guest_token_is_rejected(): void
    {
        $this->postJson('/api/payment/intent', $this->shippingPayload())
            ->assertStatus(400)
            ->assertJsonPath('message', 'Missing guest cart identifier');
    }

    public function test_guest_can_create_payment_intent_with_guest_token(): void
    {
        $product = Product::factory()->create(['price_ttc' => 25.00, 'price_ht' => 20.00]);
        $cart = Cart::create(['guest_token' => 'guest-checkout-1']);
        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $this->mockStripeReturning('pi_guest_1', 'sec_guest_1');

        $this->withHeader('X-Guest-Cart-Token', 'guest-checkout-1')
            ->postJson('/api/payment/intent', $this->shippingPayload())
            ->assertOk()
            ->assertJsonPath('payment_intent_id', 'pi_guest_1');

        $this->assertDatabaseHas('orders', [
            'user_id' => null,
            'guest_token' => 'guest-checkout-1',
            'email' => 'alice@example.com',
            'total_ttc' => 29.90,
        ]);
    }

    /* Empty cart */
    public function test_empty_cart_returns_400(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/payment/intent', $this->shippingPayload())
            ->assertStatus(400)
            ->assertJsonPath('message', 'Panier vide');
    }

    /* Shipping validation */
    public function test_shipping_validation_rejects_missing_fields(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/payment/intent', [
            'email' => 'alice@example.com',
            'shipping' => ['firstname' => 'Alice'],
        ])->assertStatus(422)
            ->assertJsonValidationErrors([
                'shipping.lastname',
                'shipping.address',
                'shipping.zip',
                'shipping.city',
                'shipping.country',
            ]);
    }

    public function test_email_is_required(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $payload = $this->shippingPayload();
        unset($payload['email']);

        $this->postJson('/api/payment/intent', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /* Happy path */
    public function test_creates_order_shipment_payment_and_returns_client_secret(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price_ttc' => 25.00, 'price_ht' => 20.00]);

        $cart = Cart::create(['user_id' => $user->id]);
        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $this->mockStripeReturning('pi_test_123', 'pi_test_123_secret_abc');
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/payment/intent', $this->shippingPayload())
            ->assertOk()
            ->assertJsonPath('payment_intent_id', 'pi_test_123')
            ->assertJsonPath('client_secret', 'pi_test_123_secret_abc')
            ->assertJsonPath('currency', 'EUR');

        $this->assertEquals(54.90, $response->json('amount'));
        $this->assertEquals(4.90, $response->json('shipping_cost'));

        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'total_ttc' => 54.90,
            'payment_status' => 'pending',
        ]);

        $this->assertDatabaseHas('shipments', [
            'firstname' => 'Alice',
            'city' => 'Paris',
            'zip' => '75002',
            'method' => 'standard',
            'cost' => 4.90,
        ]);

        $this->assertDatabaseHas('payments', [
            'provider' => 'stripe',
            'provider_payment_id' => 'pi_test_123',
            'status' => 'pending',
            'amount' => 54.90,
        ]);

        $this->assertDatabaseHas('order_items', [
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 25.00,
            'total' => 50.00,
        ]);
    }

    public function test_payment_amount_uses_price_snapshot_from_cart(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price_ttc' => 10.00, 'price_ht' => 8.00]);

        $cart = Cart::create(['user_id' => $user->id]);
        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 3,
        ]);

        $this->mockStripeReturning('pi_snap_1', 'sec_snap_1');
        Sanctum::actingAs($user);
        $response = $this->postJson('/api/payment/intent', $this->shippingPayload())->assertOk();
        $this->assertEquals(34.90, $response->json('amount')); // 10 * 3 + 4.90 standard shipping
    }

    public function test_order_item_price_matches_the_amount_charged_for_customized_products(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price_ttc' => 20.00, 'price_ht' => 16.00]);

        $session = CustomProductSession::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'status' => 'ready',
            'configuration' => [],
            'unit_price_snapshot' => 35.00,
        ]);

        $cart = Cart::create(['user_id' => $user->id]);
        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'custom_product_session_id' => $session->id,
            'quantity' => 1,
        ]);

        $this->mockStripeReturning('pi_custom_1', 'sec_custom_1');
        Sanctum::actingAs($user);
        $response = $this->postJson('/api/payment/intent', $this->shippingPayload())->assertOk();

        // amount charged by Stripe
        $this->assertEquals(39.90, $response->json('amount'));

        $this->assertDatabaseHas('order_items', [
            'product_id' => $product->id,
            'custom_product_session_id' => $session->id,
            'unit_price' => 35.00,
            'total' => 35.00,
        ]);
    }

    /* Shipping cost */
    public function test_shipping_method_is_required(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $payload = $this->shippingPayload();
        unset($payload['shipping_method']);

        $this->postJson('/api/payment/intent', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['shipping_method']);
    }

    public function test_shipping_method_rejects_unknown_value(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/payment/intent', $this->shippingPayload('overnight'))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['shipping_method']);
    }

    public function test_standard_shipping_is_free_above_the_threshold(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price_ttc' => 75.00, 'price_ht' => 60.00]);
        $cart = Cart::create(['user_id' => $user->id]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1]);
        $this->mockStripeReturning('pi_free_ship', 'sec_free_ship');
        Sanctum::actingAs($user);
        $response = $this->postJson('/api/payment/intent', $this->shippingPayload('standard'))->assertOk();
        $this->assertEquals(75.0, $response->json('amount'));
        $this->assertEquals(0.0, $response->json('shipping_cost'));
        $this->assertDatabaseHas('shipments', ['method' => 'standard', 'cost' => 0]);
    }

    public function test_express_shipping_is_never_free(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price_ttc' => 75.00, 'price_ht' => 60.00]);
        $cart = Cart::create(['user_id' => $user->id]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1]);
        $this->mockStripeReturning('pi_express', 'sec_express');
        Sanctum::actingAs($user);
        $response = $this->postJson('/api/payment/intent', $this->shippingPayload('express'))->assertOk();

        // Above 70€ threshold
        $this->assertEquals(84.90, $response->json('amount'));
        $this->assertEquals(9.90, $response->json('shipping_cost'));
        $this->assertDatabaseHas('shipments', ['method' => 'express', 'cost' => 9.90]);
    }
}
