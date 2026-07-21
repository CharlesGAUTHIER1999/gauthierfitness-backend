<?php

namespace Tests\Feature\AI;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AIDesignTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function fakeOpenAIOk(): void
    {
        $png_base64 = base64_encode(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII='));

        Http::fake([
            'api.openai.com/*' => Http::response([
                'data' => [['b64_json' => $png_base64]],
            ], 200),
        ]);
    }

    public function test_authenticated_user_can_generate_design(): void
    {
        $this->fakeOpenAIOk();

        $user = User::factory()->create();
        $product = Product::factory()->create([
            'is_customizable' => true,
            'allow_ai_generation' => true,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/ai/designs/generate', [
            'product_id' => $product->id,
            'prompt' => 'Un dragon en feu sur fond noir, style japonais',
        ])->assertCreated()
            ->assertJsonStructure(['data' => ['id', 'product_id', 'prompt', 'status']]);

        $this->assertDatabaseHas('designs', [
            'user_id' => $user->id,
            'product_id' => $product->id,
            'provider' => 'openai',
            'status' => 'generated',
        ]);

        $this->assertDatabaseHas('design_assets', [
            'type' => 'generated',
            'is_primary' => true,
        ]);

        $this->assertDatabaseHas('prompt_histories', [
            'user_id' => $user->id,
            'provider' => 'openai',
            'status' => 'success',
        ]);
    }

    public function test_generate_fails_for_non_customizable_product(): void
    {
        $this->fakeOpenAIOk();

        $user = User::factory()->create();
        $product = Product::factory()->create([
            'is_customizable' => false,
            'allow_ai_generation' => true,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/ai/designs/generate', [
            'product_id' => $product->id,
            'prompt' => 'Some prompt of more than 10 characters',
        ])->assertStatus(422)
            ->assertJsonPath('message', 'This product is not customizable.');
    }

    public function test_generate_fails_when_ai_not_allowed(): void
    {
        $this->fakeOpenAIOk();

        $user = User::factory()->create();
        $product = Product::factory()->create([
            'is_customizable' => true,
            'allow_ai_generation' => false,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/ai/designs/generate', [
            'product_id' => $product->id,
            'prompt' => 'A nice design for my shirt',
        ])->assertStatus(422)
            ->assertJsonPath('message', 'AI generation is not allowed for this product.');
    }

    public function test_generate_validates_prompt_length(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'is_customizable' => true,
            'allow_ai_generation' => true,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/ai/designs/generate', [
            'product_id' => $product->id,
            'prompt' => 'short', // < 10 chars
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['prompt']);
    }

    public function test_generate_requires_authentication(): void
    {
        $product = Product::factory()->create([
            'is_customizable' => true,
            'allow_ai_generation' => true,
        ]);

        $this->postJson('/api/ai/designs/generate', [
            'product_id' => $product->id,
            'prompt' => 'Some long enough prompt',
        ])->assertUnauthorized();
    }
}
