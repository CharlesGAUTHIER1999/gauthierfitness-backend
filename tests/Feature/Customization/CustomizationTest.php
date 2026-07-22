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

    /* Store */
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

    // Sanctum::actingAs() bypasses real guard resolution
    public function test_authenticated_user_with_real_bearer_token_can_create_session(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['is_customizable' => true]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/customization/sessions', [
                'product_id' => $product->id,
                'configuration' => ['color' => 'red'],
            ])->assertCreated();

        $this->assertDatabaseHas('custom_product_sessions', [
            'user_id' => $user->id,
            'guest_token' => null,
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
            ->assertJsonPath('message', "Ce produit n'est pas personnalisable.");
    }

    public function test_create_session_requires_authentication_or_guest_token(): void
    {
        $product = Product::factory()->create(['is_customizable' => true]);

        $this->postJson('/api/customization/sessions', [
            'product_id' => $product->id,
        ])->assertStatus(400);
    }

    public function test_guest_can_create_customization_session_with_guest_token(): void
    {
        $product = Product::factory()->create(['is_customizable' => true]);

        $this->withHeader('X-Guest-Cart-Token', 'guest-abc')
            ->postJson('/api/customization/sessions', [
                'product_id' => $product->id,
                'configuration' => ['color' => 'red'],
            ])->assertCreated()
            ->assertJsonPath('data.product_id', $product->id);

        $this->assertDatabaseHas('custom_product_sessions', [
            'guest_token' => 'guest-abc',
            'product_id' => $product->id,
            'user_id' => null,
        ]);
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

    /* Show */
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

        $this->getJson("/api/customization/sessions/$session->id")
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
        $this->getJson("/api/customization/sessions/$session->id")->assertForbidden();
    }

    public function test_guest_can_show_own_customization_session(): void
    {
        $product = Product::factory()->create(['is_customizable' => true]);

        $session = CustomProductSession::create([
            'guest_token' => 'guest-abc',
            'product_id' => $product->id,
            'status' => 'draft',
            'configuration' => [],
        ]);

        $this->withHeader('X-Guest-Cart-Token', 'guest-abc')
            ->getJson("/api/customization/sessions/$session->id")
            ->assertOk()
            ->assertJsonPath('data.id', $session->id);
    }

    public function test_guest_cannot_show_another_guests_or_a_users_session(): void
    {
        $product = Product::factory()->create(['is_customizable' => true]);

        $guest_session = CustomProductSession::create([
            'guest_token' => 'guest-a',
            'product_id' => $product->id,
            'status' => 'draft',
            'configuration' => [],
        ]);

        $this->withHeader('X-Guest-Cart-Token', 'guest-b')
            ->getJson("/api/customization/sessions/$guest_session->id")
            ->assertForbidden();

        $user = User::factory()->create();
        $user_session = CustomProductSession::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'status' => 'draft',
            'configuration' => [],
        ]);

        $this->withHeader('X-Guest-Cart-Token', 'guest-b')
            ->getJson("/api/customization/sessions/$user_session->id")
            ->assertForbidden();
    }

    /* Update */
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

        $this->patchJson("/api/customization/sessions/$session->id", [
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

        $this->patchJson("/api/customization/sessions/$session->id", [
            'status' => 'invalid_status',
        ])->assertStatus(422);
    }

    public function test_guest_can_update_own_session_configuration(): void
    {
        $product = Product::factory()->create(['is_customizable' => true]);

        $session = CustomProductSession::create([
            'guest_token' => 'guest-abc',
            'product_id' => $product->id,
            'status' => 'draft',
            'configuration' => ['color' => 'red'],
        ]);

        $this->withHeader('X-Guest-Cart-Token', 'guest-abc')
            ->patchJson("/api/customization/sessions/$session->id", [
                'configuration' => ['color' => 'blue'],
                'status' => 'ready',
            ])->assertOk();

        $session->refresh();
        $this->assertEquals('blue', $session->configuration['color']);
        $this->assertEquals('ready', $session->status);
    }

    public function test_guest_cannot_attach_a_design_to_a_session(): void
    {
        $owner = User::factory()->create();
        $product = Product::factory()->create(['is_customizable' => true]);

        $design = Design::create([
            'user_id' => $owner->id,
            'product_id' => $product->id,
            'name' => 'Some design',
            'status' => 'generated',
        ]);

        $session = CustomProductSession::create([
            'guest_token' => 'guest-abc',
            'product_id' => $product->id,
            'status' => 'draft',
            'configuration' => [],
        ]);

        $this->withHeader('X-Guest-Cart-Token', 'guest-abc')
            ->patchJson("/api/customization/sessions/$session->id", [
                'design_id' => $design->id,
            ])->assertForbidden();
    }

    /* Free-text field validation */

    public function test_create_session_rejects_non_numeric_player_number(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['is_customizable' => true]);
        Sanctum::actingAs($user);

        $this->postJson('/api/customization/sessions', [
            'product_id' => $product->id,
            'configuration' => ['player_number' => ['value' => 'AB']],
        ])->assertStatus(422);
    }

    public function test_create_session_accepts_numeric_player_number(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['is_customizable' => true]);
        Sanctum::actingAs($user);

        $this->postJson('/api/customization/sessions', [
            'product_id' => $product->id,
            'configuration' => ['player_number' => ['value' => '23'], 'color' => 'red'],
        ])->assertCreated();
    }

    public function test_create_session_rejects_blocked_term_in_player_name(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['is_customizable' => true]);
        Sanctum::actingAs($user);

        $this->postJson('/api/customization/sessions', [
            'product_id' => $product->id,
            'configuration' => ['player_name' => ['value' => 'nazi']],
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Votre texte contient un terme interdit et ne peut pas être utilisé.');
    }

    public function test_create_session_rejects_blocked_term_in_text_layer(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['is_customizable' => true]);
        Sanctum::actingAs($user);

        $this->postJson('/api/customization/sessions', [
            'product_id' => $product->id,
            'configuration' => ['text_layers' => [['id' => 'txt-1', 'text' => 'hamas']]],
        ])->assertStatus(422);
    }

    public function test_update_session_rejects_blocked_term_and_preserves_other_configuration_keys(): void
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

        $this->patchJson("/api/customization/sessions/$session->id", [
            'configuration' => ['color' => 'blue', 'player_name' => ['value' => 'nazi']],
        ])->assertStatus(422);

        $session->refresh();
        $this->assertEquals('red', $session->configuration['color']);
    }

    public function test_update_session_with_clean_text_preserves_all_configuration_keys(): void
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

        $this->patchJson("/api/customization/sessions/$session->id", [
            'configuration' => ['color' => 'blue', 'player_name' => ['value' => 'Alice'], 'player_number' => ['value' => '10']],
        ])->assertOk();

        $session->refresh();
        $this->assertEquals('blue', $session->configuration['color']);
        $this->assertEquals('Alice', $session->configuration['player_name']['value']);
        $this->assertEquals('10', $session->configuration['player_number']['value']);
    }
}
