<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/logout')
            ->assertOk()
            ->assertJsonPath('message', 'ok');
    }

    public function test_unauthenticated_user_cannot_logout(): void
    {
        $this->postJson('/api/logout')->assertUnauthorized();
    }

    public function test_logout_deletes_the_token_from_database(): void
    {
        $user = User::factory()->create();
        $user->createToken('test');

        $this->assertDatabaseCount('personal_access_tokens', 1);

        Sanctum::actingAs($user, ['*']);

        // Reload user with current token marked
        $user->withAccessToken(PersonalAccessToken::first());

        $this->postJson('/api/logout')->assertOk();

        // Token deleted from the DB
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
