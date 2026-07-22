<?php

namespace Tests\Feature\Customization;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
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

    /** OpenAI moderation responses (categories + vision check), faked as "clean" by default */
    private function fakeModeration(bool $flagged = false, array $categories = [], bool $visual_flagged = false, ?string $visual_reason = null): void
    {
        Http::fake([
            'api.openai.com/v1/moderations' => Http::response([
                'results' => [['flagged' => $flagged, 'categories' => $categories]],
            ]),
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => json_encode([
                    'prohibited' => $visual_flagged,
                    'reason' => $visual_reason,
                ])]]],
            ]),
        ]);
    }

    /* Upload Logo */
    public function test_user_can_upload_logo(): void
    {
        $this->fakeModeration();
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $file = UploadedFile::fake()->image('logo.png', 200, 200);

        $response = $this->postJson('/api/customization/assets/logo', [
            'file' => $file,
        ]);

        $response->assertCreated()->assertJsonStructure(['data' => ['path', 'url', 'mime_type', 'size']]);
        $path = $response->json('data.path');
        Storage::disk('public')->assertExists($path);
    }

    public function test_logo_upload_rejects_wrong_mime_type(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $file = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');
        $this->postJson('/api/customization/assets/logo', ['file' => $file])->assertStatus(422);
    }

    public function test_logo_upload_rejects_file_too_large(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Max 3072 KB
        $file = UploadedFile::fake()->image('big.png')->size(4000);
        $this->postJson('/api/customization/assets/logo', ['file' => $file])->assertStatus(422);
    }

    public function test_logo_upload_requires_authentication_or_guest_token(): void
    {
        $this->fakeModeration();
        $file = UploadedFile::fake()->image('logo.png');
        $this->postJson('/api/customization/assets/logo', ['file' => $file])->assertStatus(400);
    }

    public function test_guest_can_upload_logo_with_guest_token(): void
    {
        $this->fakeModeration();
        $file = UploadedFile::fake()->image('logo.png', 200, 200);
        $response = $this->withHeader('X-Guest-Cart-Token', 'guest-abc')->postJson('/api/customization/assets/logo', ['file' => $file]);
        $response->assertCreated();
        $path = $response->json('data.path');
        Storage::disk('public')->assertExists($path);
        $this->assertStringContainsString('customization/logos/guest-guest-abc/', $path);
    }

    /* Upload Image */

    public function test_user_can_upload_image(): void
    {
        $this->fakeModeration();
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $file = UploadedFile::fake()->image('photo.jpg', 800, 600);

        $response = $this->postJson('/api/customization/assets/image', [
            'file' => $file,
        ]);

        $response->assertCreated()->assertJsonStructure(['data' => ['path', 'url']]);
        Storage::disk('public')->assertExists($response->json('data.path'));
    }

    public function test_image_upload_stores_in_user_specific_directory(): void
    {
        $this->fakeModeration();
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $file = UploadedFile::fake()->image('photo.png');
        $response = $this->postJson('/api/customization/assets/image', ['file' => $file]);
        $path = $response->json('data.path');
        $this->assertStringContainsString("customization/images/$user->id/", $path);
    }

    public function test_guest_can_upload_image_with_guest_token(): void
    {
        $this->fakeModeration();
        $file = UploadedFile::fake()->image('photo.jpg', 800, 600);
        $response = $this->withHeader('X-Guest-Cart-Token', 'guest-xyz')->postJson('/api/customization/assets/image', ['file' => $file]);
        $response->assertCreated();
        $path = $response->json('data.path');
        Storage::disk('public')->assertExists($path);
        $this->assertStringContainsString('customization/images/guest-guest-xyz/', $path);
    }

    /* Content moderation */

    public function test_logo_upload_rejects_flagged_content(): void
    {
        $this->fakeModeration(flagged: true, categories: ['violence' => true]);

        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $file = UploadedFile::fake()->image('logo.png', 200, 200);

        $this->postJson('/api/customization/assets/logo', ['file' => $file])
            ->assertStatus(422)
            ->assertJsonPath('reason', 'image_flagged')
            ->assertJsonPath('categories', ['violence']);

        Storage::disk('public')->assertDirectoryEmpty('customization/logos');
    }

    public function test_image_upload_rejects_flagged_content(): void
    {
        $this->fakeModeration(flagged: true, categories: ['sexual' => true]);

        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $file = UploadedFile::fake()->image('photo.jpg', 800, 600);

        $this->postJson('/api/customization/assets/image', ['file' => $file])
            ->assertStatus(422)
            ->assertJsonPath('reason', 'image_flagged');

        Storage::disk('public')->assertDirectoryEmpty('customization/images');
    }

    public function test_logo_upload_rejects_prohibited_visual_content(): void
    {
        $this->fakeModeration(visual_flagged: true, visual_reason: 'Depicts a firearm');

        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $file = UploadedFile::fake()->image('logo.png', 200, 200);

        $this->postJson('/api/customization/assets/logo', ['file' => $file])
            ->assertStatus(422)
            ->assertJsonPath('reason', 'visual_content_flagged')
            ->assertJsonPath('details', 'Depicts a firearm');

        Storage::disk('public')->assertDirectoryEmpty('customization/logos');
    }

    public function test_image_upload_rejects_prohibited_visual_content(): void
    {
        $this->fakeModeration(visual_flagged: true, visual_reason: 'Depicts a firearm');

        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $file = UploadedFile::fake()->image('photo.jpg', 800, 600);

        $this->postJson('/api/customization/assets/image', ['file' => $file])
            ->assertStatus(422)
            ->assertJsonPath('reason', 'visual_content_flagged');

        Storage::disk('public')->assertDirectoryEmpty('customization/images');
    }
}
