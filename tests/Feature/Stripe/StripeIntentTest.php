<?php

namespace Tests\Feature\Stripe;

use App\Models\Cart;
use App\Models\CartItem;
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

    private function shippingPayload(): array
    {
        return [
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

    private function mockStripeReturning(string $intentId, string $clientSecret): void
    {
        // Real PaymentIntent so toArray() and dynamic props work natively.
        $intent = PaymentIntent::constructFrom([
            'id' => $intentId,
            'object' => 'payment_intent',
            'client_secret' => $clientSecret,
            'amount' => 0,
            'currency' => 'eur',
            'status' => 'requires_payment_method',
        ]);

        $paymentIntents = Mockery::mock(PaymentIntentService::class);
        $paymentIntents->shouldReceive('create')->andReturn($intent);

        $client = Mockery::mock(StripeClient::class);
        $client->paymentIntents = $paymentIntents;

        $this->app->instance(StripeClient::class, $client);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /* ── Auth ──────────────────────────────────────────────────── */

    public function test_unauthenticated_user_cannot_create_payment_intent(): void
    {
        $this->postJson('/api/payment/intent', $this->shippingPayload())
            ->assertUnauthorized();
    }

    /* ── Empty cart ────────────────────────────────────────────── */

    public function test_empty_cart_returns_400(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/payment/intent', $this->shippingPayload())
            ->assertStatus(400)
            ->assertJsonPath('message', 'Panier vide');
    }

    /* ── Shipping validation ───────────────────────────────────── */

    public function test_shipping_validation_rejects_missing_fields(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/payment/intent', [
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

    /* ── Happy path ────────────────────────────────────────────── */

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

        $this->assertEquals(50.0, $response->json('amount'));

        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'total_ttc' => 50.00,
            'payment_status' => 'pending',
        ]);

        $this->assertDatabaseHas('shipments', [
            'firstname' => 'Alice',
            'city' => 'Paris',
            'zip' => '75002',
        ]);

        $this->assertDatabaseHas('payments', [
            'provider' => 'stripe',
            'provider_payment_id' => 'pi_test_123',
            'status' => 'pending',
            'amount' => 50.00,
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

        $this->assertEquals(30.0, $response->json('amount')); // 10 * 3
    }
}
