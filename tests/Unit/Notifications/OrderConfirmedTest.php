<?php

namespace Tests\Unit\Notifications;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\User;
use App\Notifications\OrderConfirmed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Tests\TestCase;

class OrderConfirmedTest extends TestCase
{
    use RefreshDatabase;

    public function test_greeting_uses_the_account_firstname_for_a_logged_in_user(): void
    {
        $user = User::factory()->create(['firstname' => 'Alice']);
        $order = Order::factory()->create(['user_id' => $user->id]);

        $mail = (new OrderConfirmed($order))->toMail($user);

        $this->assertStringContainsString('Bonjour Alice,', $mail->greeting);
    }

    public function test_greeting_falls_back_to_the_shipping_firstname_for_a_guest_order(): void
    {
        $order = Order::factory()->create(['user_id' => null, 'guest_token' => 'guest-1']);
        Shipment::create([
            'order_id' => $order->id,
            'firstname' => 'Bob',
            'address' => '1 rue de Paris',
            'method' => 'standard',
            'cost' => 4.90,
        ]);

        // Guest orders are notified via Laravel's on-demand ("anonymous") notifiable,
        // which has no `firstname` property — this must never be accessed directly.
        $mail = (new OrderConfirmed($order->fresh()))->toMail(new AnonymousNotifiable);

        $this->assertStringContainsString('Bonjour Bob,', $mail->greeting);
    }

    public function test_greeting_falls_back_to_a_generic_name_when_nothing_is_known(): void
    {
        $order = Order::factory()->create(['user_id' => null, 'guest_token' => 'guest-2']);

        $mail = (new OrderConfirmed($order))->toMail(new AnonymousNotifiable);

        $this->assertStringContainsString('Bonjour client,', $mail->greeting);
    }

    public function test_mail_includes_the_shipping_method_and_cost(): void
    {
        $order = Order::factory()->create(['user_id' => null, 'guest_token' => 'guest-3']);
        Shipment::create([
            'order_id' => $order->id,
            'firstname' => 'Bob',
            'address' => '1 rue de Paris',
            'method' => 'express',
            'cost' => 9.90,
        ]);

        $mail = (new OrderConfirmed($order->fresh()))->toMail(new AnonymousNotifiable);

        $this->assertTrue(collect($mail->introLines)->contains('Livraison (Express) : 9,90 €'));
    }

    public function test_mail_lists_order_items(): void
    {
        $product = Product::factory()->create(['name' => 'Tee-shirt personnalisé']);
        $order = Order::factory()->create(['user_id' => null, 'guest_token' => 'guest-4']);
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'unit_price' => 20,
            'quantity' => 2,
            'total' => 40,
        ]);

        $mail = (new OrderConfirmed($order->fresh()))->toMail(new AnonymousNotifiable);

        $this->assertTrue(
            collect($mail->introLines)->contains('• Tee-shirt personnalisé ×2 — 40,00 €')
        );
    }
}
