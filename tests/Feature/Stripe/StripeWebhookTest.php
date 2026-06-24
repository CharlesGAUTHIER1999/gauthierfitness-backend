<?php

namespace Tests\Feature\Stripe;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\StockLot;
use App\Models\User;
use App\Notifications\OrderConfirmed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    private string $secret = 'whsec_test_GFTest';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.stripe.webhook_secret' => $this->secret]);

        Notification::fake();
    }

    /**
     * Forge la signature Stripe attendue (algo HMAC officiel).
     */
    private function signedHeaders(string $payload): array
    {
        $timestamp = time();
        $signedPayload = $timestamp.'.'.$payload;
        $signature = hash_hmac('sha256', $signedPayload, $this->secret);

        return [
            'Stripe-Signature' => "t={$timestamp},v1={$signature}",
            'Content-Type' => 'application/json',
        ];
    }

    private function buildSucceededPayload(string $eventId, int $orderId, int $paymentId): string
    {
        return json_encode([
            'id' => $eventId,
            'object' => 'event',
            'api_version' => '2024-04-10',
            'created' => time(),
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_'.uniqid(),
                    'object' => 'payment_intent',
                    'amount' => 5000,
                    'currency' => 'eur',
                    'metadata' => [
                        'order_id' => (string) $orderId,
                        'payment_id' => (string) $paymentId,
                    ],
                ],
            ],
        ]);
    }

    private function buildFailedPayload(string $eventId, int $orderId, int $paymentId): string
    {
        return json_encode([
            'id' => $eventId,
            'object' => 'event',
            'api_version' => '2024-04-10',
            'created' => time(),
            'type' => 'payment_intent.payment_failed',
            'data' => [
                'object' => [
                    'id' => 'pi_'.uniqid(),
                    'object' => 'payment_intent',
                    'metadata' => [
                        'order_id' => (string) $orderId,
                        'payment_id' => (string) $paymentId,
                    ],
                ],
            ],
        ]);
    }

    /* ── Signature ─────────────────────────────────────────────── */

    public function test_webhook_rejects_invalid_signature(): void
    {
        $payload = json_encode(['id' => 'evt_test_invalid', 'type' => 'payment_intent.succeeded']);

        $this->call(
            'POST',
            '/api/stripe/webhook',
            [],
            [],
            [],
            ['HTTP_STRIPE_SIGNATURE' => 't=123,v1=invalidsignature', 'CONTENT_TYPE' => 'application/json'],
            $payload
        )->assertStatus(400);
    }

    /* ── Succès paiement ───────────────────────────────────────── */

    public function test_payment_intent_succeeded_marks_order_as_paid(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'payment_status' => 'pending',
            'order_status' => 'new',
        ]);
        $payment = Payment::create([
            'order_id' => $order->id,
            'provider' => 'stripe',
            'amount' => 50.00,
            'status' => 'pending',
        ]);

        $payload = $this->buildSucceededPayload('evt_succ_1', $order->id, $payment->id);

        $this->call(
            'POST',
            '/api/stripe/webhook',
            [], [], [],
            array_merge(
                ['CONTENT_TYPE' => 'application/json'],
                $this->convertHeadersForCall($this->signedHeaders($payload))
            ),
            $payload
        )->assertOk()
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'payment_status' => 'paid',
            'order_status' => 'processing',
        ]);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'success',
        ]);
    }

    public function test_payment_intent_succeeded_sends_confirmation_email_once(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'payment_status' => 'pending',
            'order_status' => 'new',
        ]);
        $payment = Payment::create([
            'order_id' => $order->id,
            'provider' => 'stripe',
            'amount' => 50.00,
            'status' => 'pending',
        ]);

        $payload = $this->buildSucceededPayload('evt_email_1', $order->id, $payment->id);

        $this->call(
            'POST', '/api/stripe/webhook', [], [], [],
            array_merge(
                ['CONTENT_TYPE' => 'application/json'],
                $this->convertHeadersForCall($this->signedHeaders($payload))
            ),
            $payload
        )->assertOk();

        Notification::assertSentTo($user, OrderConfirmed::class);

        $this->assertNotNull($order->fresh()->paid_email_sent_at);
    }

    public function test_payment_intent_succeeded_decrements_stock_fifo(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        StockLot::factory()->create([
            'product_id' => $product->id,
            'lot_number' => 'LOT-FAR',
            'quantity' => 50,
            'expiration_date' => now()->addYear(),
        ]);
        $earlyLot = StockLot::factory()->create([
            'product_id' => $product->id,
            'lot_number' => 'LOT-CLOSE',
            'quantity' => 50,
            'expiration_date' => now()->addMonth(),
        ]);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'payment_status' => 'pending',
            'order_status' => 'new',
        ]);
        $payment = Payment::create([
            'order_id' => $order->id,
            'provider' => 'stripe',
            'amount' => 30,
            'status' => 'pending',
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'unit_price' => 10,
            'quantity' => 3,
            'total' => 30,
        ]);

        $payload = $this->buildSucceededPayload('evt_stock_1', $order->id, $payment->id);

        $this->call(
            'POST', '/api/stripe/webhook', [], [], [],
            array_merge(
                ['CONTENT_TYPE' => 'application/json'],
                $this->convertHeadersForCall($this->signedHeaders($payload))
            ),
            $payload
        )->assertOk();

        $this->assertDatabaseHas('stock_lots', [
            'id' => $earlyLot->id,
            'quantity' => 47, // 50 - 3
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'lot_id' => $earlyLot->id,
            'type' => 'out',
            'quantity' => 3,
        ]);
    }

    /* ── Idempotence ───────────────────────────────────────────── */

    public function test_duplicate_event_returns_already_processed(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);
        $payment = Payment::create([
            'order_id' => $order->id,
            'provider' => 'stripe',
            'amount' => 50,
            'status' => 'pending',
        ]);

        $payload = $this->buildSucceededPayload('evt_dup_42', $order->id, $payment->id);

        // 1er appel : traite (timestamp t1)
        $headers1 = array_merge(
            ['CONTENT_TYPE' => 'application/json'],
            $this->convertHeadersForCall($this->signedHeaders($payload))
        );
        $this->call('POST', '/api/stripe/webhook', [], [], [], $headers1, $payload)
            ->assertOk()
            ->assertJsonPath('status', 'success');

        // Vérifie qu'un WebhookEvent a bien été créé et marqué comme processed
        $this->assertDatabaseHas('webhook_events', [
            'provider' => 'stripe',
            'provider_event_id' => 'evt_dup_42',
        ]);
        $we = \App\Models\WebhookEvent::where('provider_event_id', 'evt_dup_42')->first();
        $this->assertNotNull($we->processed_at, 'processed_at devrait être set après le 1er appel');

        // 2e appel : nouvelle signature (timestamp différent) mais MÊME event_id Stripe
        sleep(1);
        $headers2 = array_merge(
            ['CONTENT_TYPE' => 'application/json'],
            $this->convertHeadersForCall($this->signedHeaders($payload))
        );
        $this->call('POST', '/api/stripe/webhook', [], [], [], $headers2, $payload)
            ->assertOk()
            ->assertJsonPath('status', 'already_processed');

        // Un seul WebhookEvent en base
        $this->assertDatabaseCount('webhook_events', 1);
    }

    /* ── Échec paiement ────────────────────────────────────────── */

    public function test_payment_intent_failed_marks_payment_and_order_failed(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'payment_status' => 'pending',
            'order_status' => 'new',
        ]);
        $payment = Payment::create([
            'order_id' => $order->id,
            'provider' => 'stripe',
            'amount' => 50,
            'status' => 'pending',
        ]);

        $payload = $this->buildFailedPayload('evt_fail_1', $order->id, $payment->id);

        $this->call(
            'POST', '/api/stripe/webhook', [], [], [],
            array_merge(
                ['CONTENT_TYPE' => 'application/json'],
                $this->convertHeadersForCall($this->signedHeaders($payload))
            ),
            $payload
        )->assertOk();

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'failed',
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'payment_status' => 'failed',
        ]);
    }

    /* ── Helpers ───────────────────────────────────────────────── */

    private function convertHeadersForCall(array $headers): array
    {
        $out = [];
        foreach ($headers as $k => $v) {
            $out['HTTP_'.str_replace('-', '_', strtoupper($k))] = $v;
        }

        return $out;
    }
}
