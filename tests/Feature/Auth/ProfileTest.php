<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_update_their_address(): void
    {
        $user = User::factory()->create([
            'address' => null,
            'zip' => null,
            'city' => null,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson('/api/me', [
                'address' => '12 rue de la Paix',
                'zip' => '75002',
                'city' => 'Paris',
            ]);

        $response->assertOk()
            ->assertJsonPath('address', '12 rue de la Paix')
            ->assertJsonPath('zip', '75002')
            ->assertJsonPath('city', 'Paris');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'address' => '12 rue de la Paix',
            'zip' => '75002',
            'city' => 'Paris',
        ]);
    }

    public function test_partial_update_does_not_erase_other_fields(): void
    {
        $user = User::factory()->create([
            'firstname' => 'Alice',
            'address' => '1 rue Ancienne',
            'zip' => '69000',
            'city' => 'Lyon',
        ]);

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/me', ['city' => 'Villeurbanne'])
            ->assertOk()
            ->assertJsonPath('city', 'Villeurbanne')
            ->assertJsonPath('address', '1 rue Ancienne')
            ->assertJsonPath('firstname', 'Alice');
    }

    public function test_update_fails_with_invalid_zip(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/me', ['zip' => str_repeat('9', 20)])
            ->assertUnprocessable();
    }

    public function test_unauthenticated_user_cannot_update_profile(): void
    {
        $this->patchJson('/api/me', ['city' => 'Paris'])->assertUnauthorized();
    }
}
