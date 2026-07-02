<?php

namespace Tests\Feature\Contact;

use App\Mail\ContactMessageMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class ContactTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        RateLimiter::clear('throttle:5,1');
    }

    public function test_guest_can_send_contact_message(): void
    {
        $this->postJson('/api/contact', [
            'name' => 'Charles Gauthier',
            'email' => 'charles@example.com',
            'subject' => 'Question',
            'message' => 'Bonjour, j\'ai une question sur vos produits.',
        ])->assertOk()
            ->assertJsonPath('message', 'Message envoyé. Nous te répondrons rapidement.');

        Mail::assertSent(ContactMessageMail::class);
    }

    public function test_contact_validates_required_fields(): void
    {
        $this->postJson('/api/contact', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'message']);

        Mail::assertNothingSent();
    }

    public function test_contact_validates_email_format(): void
    {
        $this->postJson('/api/contact', [
            'name' => 'Test',
            'email' => 'not-an-email',
            'message' => 'Hello',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_contact_is_throttled_after_5_requests(): void
    {
        $payload = [
            'name' => 'Spammer',
            'email' => 'spam@example.com',
            'message' => 'Spam content.',
        ];

        // 5 requests OK
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/contact', $payload)->assertOk();
        }

        // The 6th one gets throttled
        $this->postJson('/api/contact', $payload)->assertStatus(429);
    }
}
