<?php

namespace Tests\Feature\Customization;

use App\Models\CustomProductSession;
use App\Models\Design;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomizationTest extends TestCase
{
    use RefreshDatabase;

    /* ── Store ─────────────────────────────────────────────────── */

    public function test_user_can_create_customization_session_for_customizable_product(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['is_customizable' => true]);

        Sanctum::actingAs($user);

        $this->postJson('/api/customization/sessions', [
            'product_id' => $product->id,
            'configuration' => ['color' => 'red'],
        ])->assertCreated()
            ->assertJsonPath('data.product_id', $product->id)
            ->assertJsonPath('data.status', 'draft');

        $this->assertDatabaseHas('custom_product_sessions', [
            'user_id' => $user->id,
            'product_id' => $product->id,
        ]);
    }

    public function test_create_session_fails_for_non_customizable_product(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['is_customizable' => false]);

        Sanctum::actingAs($user);

        $this->postJson('/api/customization/sessions', [
            'product_id' => $product->id,
        ])->assertStatus(422)
            ->assertJsonPath('message', 'This product is not customizable.');
    }

    public function test_create_session_requires_authentication(): void
    {
        $product = Product::factory()->create(['is_customizable' => true]);

        $this->postJson('/api/customization/sessions', [
            'product_id' => $product->id,
        ])->assertUnauthorized();
    }

    public function test_create_session_validates_product_id(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/customization/sessions', [
            'product_id' => 999999,
        ])->assertStatus(422);
    }

    public function test_create_session_with_design_of_another_user_fails(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $product = Product::factory()->create(['is_customizable' => true]);

        $design = Design::create([
            'user_id' => $other->id,
            'product_id' => $product->id,
            'name' => 'Other user design',
            'status' => 'generated',
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/customization/sessions', [
            'product_id' => $product->id,
            'design_id' => $design->id,
        ])->assertForbidden();
    }

    /* ── Show ──────────────────────────────────────────────────── */

    public function test_user_can_show_own_customization_session(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['is_customizable' => true]);

        $session = CustomProductSession::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'status' => 'draft',
            'configuration' => [],
        ]);

        Sanctum::actingAs($user);

        $this->getJson("/api/customization/sessions/{$session->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $session->id);
    }

    public function test_user_cannot_show_session_of_another_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $product = Product::factory()->create(['is_customizable' => true]);

        $session = CustomProductSession::create([
            'user_id' => $other->id,
            'product_id' => $product->id,
            'status' => 'draft',
            'configuration' => [],
        ]);

        Sanctum::actingAs($user);

        $this->getJson("/api/customization/sessions/{$session->id}")
            ->assertForbidden();
    }

    /* ── Update ────────────────────────────────────────────────── */

    public function test_user_can_update_own_session_configuration(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['is_customizable' => true]);

        $session = CustomProductSession::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'status' => 'draft',
            'configuration' => ['color' => 'red'],
        ]);

        Sanctum::actingAs($user);

        $this->patchJson("/api/customization/sessions/{$session->id}", [
            'configuration' => ['color' => 'blue', 'size' => 'M'],
            'status' => 'ready',
        ])->assertOk();

        $session->refresh();
        $this->assertEquals('blue', $session->configuration['color']);
        $this->assertEquals('ready', $session->status);
    }

    public function test_update_rejects_invalid_status(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['is_customizable' => true]);

        $session = CustomProductSession::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'status' => 'draft',
            'configuration' => [],
        ]);

        Sanctum::actingAs($user);

        $this->patchJson("/api/customization/sessions/{$session->id}", [
            'status' => 'invalid_status',
        ])->assertStatus(422);
    }
}
