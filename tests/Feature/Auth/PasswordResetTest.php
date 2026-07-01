<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('throttle:5,1');
    }

    /* ── Forgot password ───────────────────────────────────────── */

    public function test_user_can_request_password_reset_link(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'reset@test.fr']);

        $this->postJson('/api/forgot-password', ['email' => 'reset@test.fr'])
            ->assertOk()
            ->assertJsonPath('message', 'Si un compte existe pour cet email, un lien de réinitialisation a été envoyé.');

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_forgot_password_returns_generic_message_for_unknown_email(): void
    {
        Notification::fake();

        $this->postJson('/api/forgot-password', ['email' => 'unknown@test.fr'])
            ->assertOk()
            ->assertJsonPath('message', 'Si un compte existe pour cet email, un lien de réinitialisation a été envoyé.');

        Notification::assertNothingSent();
    }

    public function test_forgot_password_validates_email_format(): void
    {
        $this->postJson('/api/forgot-password', ['email' => 'not-an-email'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_forgot_password_is_throttled_after_5_requests(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/forgot-password', ['email' => "user{$i}@test.fr"])->assertOk();
        }

        $this->postJson('/api/forgot-password', ['email' => 'user6@test.fr'])
            ->assertStatus(429);
    }

    /* ── Reset password ────────────────────────────────────────── */

    public function test_user_can_reset_password_with_valid_token(): void
    {
        $user = User::factory()->create([
            'email' => 'reset2@test.fr',
            'password' => bcrypt('OldPassword123!'),
        ]);

        $oldToken = $user->createToken('react')->plainTextToken;
        $token = Password::createToken($user);

        $this->postJson('/api/reset-password', [
            'email' => 'reset2@test.fr',
            'token' => $token,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertOk()
            ->assertJsonPath('message', 'Mot de passe réinitialisé avec succès.');

        $this->assertTrue(Hash::check('NewPassword123!', $user->fresh()->password));

        // Ancien token révoqué
        $this->withHeader('Authorization', 'Bearer '.explode('|', $oldToken)[1])
            ->getJson('/api/me')
            ->assertUnauthorized();

        // Nouveau mot de passe permet de se reconnecter
        $this->postJson('/api/login', [
            'email' => 'reset2@test.fr',
            'password' => 'NewPassword123!',
        ])->assertOk();
    }

    public function test_reset_password_fails_with_invalid_token(): void
    {
        $user = User::factory()->create(['email' => 'reset3@test.fr']);

        $this->postJson('/api/reset-password', [
            'email' => 'reset3@test.fr',
            'token' => 'invalid-token',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertStatus(422);
    }

    public function test_reset_password_validates_password_confirmation(): void
    {
        $user = User::factory()->create(['email' => 'reset4@test.fr']);
        $token = Password::createToken($user);

        $this->postJson('/api/reset-password', [
            'email' => 'reset4@test.fr',
            'token' => $token,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'Mismatch123!',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }
}
