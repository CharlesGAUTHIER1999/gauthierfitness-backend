<?php

namespace Tests\Feature\Customization;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomizationAssetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    /* ── Upload Logo ───────────────────────────────────────────── */

    public function test_user_can_upload_logo(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $file = UploadedFile::fake()->image('logo.png', 200, 200);

        $response = $this->postJson('/api/customization/assets/logo', [
            'file' => $file,
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['data' => ['path', 'url', 'mime_type', 'size']]);

        $path = $response->json('data.path');
        Storage::disk('public')->assertExists($path);
    }

    public function test_logo_upload_rejects_wrong_mime_type(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $file = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');

        $this->postJson('/api/customization/assets/logo', ['file' => $file])
            ->assertStatus(422);
    }

    public function test_logo_upload_rejects_file_too_large(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Max 3072 KB
        $file = UploadedFile::fake()->image('big.png')->size(4000);

        $this->postJson('/api/customization/assets/logo', ['file' => $file])
            ->assertStatus(422);
    }

    public function test_logo_upload_requires_authentication_or_guest_token(): void
    {
        $file = UploadedFile::fake()->image('logo.png');

        $this->postJson('/api/customization/assets/logo', ['file' => $file])
            ->assertStatus(400);
    }

    public function test_guest_can_upload_logo_with_guest_token(): void
    {
        $file = UploadedFile::fake()->image('logo.png', 200, 200);

        $response = $this->withHeader('X-Guest-Cart-Token', 'guest-abc')
            ->postJson('/api/customization/assets/logo', ['file' => $file]);

        $response->assertCreated();

        $path = $response->json('data.path');
        Storage::disk('public')->assertExists($path);
        $this->assertStringContainsString('customization/logos/guest-guest-abc/', $path);
    }

    /* ── Upload Image ──────────────────────────────────────────── */

    public function test_user_can_upload_image(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $file = UploadedFile::fake()->image('photo.jpg', 800, 600);

        $response = $this->postJson('/api/customization/assets/image', [
            'file' => $file,
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['data' => ['path', 'url']]);

        Storage::disk('public')->assertExists($response->json('data.path'));
    }

    public function test_image_upload_stores_in_user_specific_directory(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $file = UploadedFile::fake()->image('photo.png');

        $response = $this->postJson('/api/customization/assets/image', ['file' => $file]);

        $path = $response->json('data.path');
        $this->assertStringContainsString("customization/images/{$user->id}/", $path);
    }

    public function test_guest_can_upload_image_with_guest_token(): void
    {
        $file = UploadedFile::fake()->image('photo.jpg', 800, 600);

        $response = $this->withHeader('X-Guest-Cart-Token', 'guest-xyz')
            ->postJson('/api/customization/assets/image', ['file' => $file]);

        $response->assertCreated();

        $path = $response->json('data.path');
        Storage::disk('public')->assertExists($path);
        $this->assertStringContainsString('customization/images/guest-guest-xyz/', $path);
    }
}
