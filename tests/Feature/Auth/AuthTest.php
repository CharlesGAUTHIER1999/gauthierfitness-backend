<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    /* Register */
    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/register', [
            'firstname' => 'Charles',
            'lastname' => 'Gauthier',
            'email' => 'charles@gauthierfitness.fr',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertCreated()->assertJsonStructure(['token', 'user' => ['id', 'email']]);
        $this->assertDatabaseHas('users', ['email' => 'charles@gauthierfitness.fr']);
    }

    public function test_register_fails_with_duplicate_email(): void
    {
        User::factory()->create(['email' => 'dup@test.fr']);

        $this->postJson('/api/register', [
            'firstname' => 'Test',
            'lastname' => 'User',
            'email' => 'dup@test.fr',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertUnprocessable();
    }

    /* Login */
    public function test_user_can_login(): void
    {
        $user = User::factory()->create([
            'email' => 'login@test.fr',
            'password' => bcrypt('Password123!'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'login@test.fr',
            'password' => 'Password123!',
        ]);

        $response->assertOk()->assertJsonStructure(['token', 'user']);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create([
            'email' => 'wrong@test.fr',
            'password' => bcrypt('correct'),
        ]);

        $this->postJson('/api/login', [
            'email' => 'wrong@test.fr',
            'password' => 'wrong',
        ])->assertUnauthorized();
    }

    /* Me and Logout */
    public function test_authenticated_user_can_access_me(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('email', $user->email);
    }

    public function test_unauthenticated_user_cannot_access_me(): void
    {
        $this->getJson('/api/me')->assertUnauthorized();
    }
}
