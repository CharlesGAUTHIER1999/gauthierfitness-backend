<?php

namespace Tests\Feature\AI;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GenerateDesignTest extends TestCase
{
    use RefreshDatabase;

    private const string ENDPOINT = '/api/ai/designs/generate';

    private const string VALID_PROMPT = 'Un motif graphique sportif et dynamique pour un t-shirt de fitness';

    private function aiProduct(): Product
    {
        return Product::factory()->create([
            'is_customizable' => true,
            'allow_ai_generation' => true,
        ]);
    }

    /** OpenAI moderation response */
    private function moderationResponse(bool $flagged = false, array $categories = []): array
    {
        return ['results' => [[
            'flagged' => $flagged,
            'categories' => $categories,
        ]]];
    }

    /** OpenAI image generation response. */
    private function imageResponse(): array
    {
        return ['data' => [['b64_json' => base64_encode('fake-image-bytes')]]];
    }

    // Auth
    public function test_unauthenticated_user_cannot_generate_design(): void
    {
        $this->postJson(self::ENDPOINT, [
            'product_id' => $this->aiProduct()->id,
            'prompt' => self::VALID_PROMPT,
        ])->assertUnauthorized();
    }

    // Product guardrails
    public function test_rejects_product_that_does_not_allow_ai(): void
    {
        $product = Product::factory()->create([
            'is_customizable' => true,
            'allow_ai_generation' => false,
        ]);

        Sanctum::actingAs(User::factory()->create());

        $this->postJson(self::ENDPOINT, [
            'product_id' => $product->id,
            'prompt' => self::VALID_PROMPT,
        ])->assertStatus(422)
            ->assertJsonPath('message', "La génération IA n'est pas autorisée pour ce produit.");

        Http::assertNothingSent();
    }

    public function test_rejects_non_customizable_product(): void
    {
        $product = Product::factory()->create([
            'is_customizable' => false,
            'allow_ai_generation' => true,
        ]);

        Sanctum::actingAs(User::factory()->create());

        $this->postJson(self::ENDPOINT, [
            'product_id' => $product->id,
            'prompt' => self::VALID_PROMPT,
        ])->assertStatus(422)
            ->assertJsonPath('message', "Ce produit n'est pas personnalisable.");

        Http::assertNothingSent();
    }

    // Validation
    public function test_validates_prompt_length(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson(self::ENDPOINT, [
            'product_id' => $this->aiProduct()->id,
            'prompt' => 'short', // < 10 chars
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['prompt']);

        Http::assertNothingSent();
    }

    // Happy path
    public function test_generates_design_when_content_is_clean(): void
    {
        Storage::fake('public');

        Http::fake([
            'api.openai.com/v1/moderations' => Http::response($this->moderationResponse(flagged: false)),
            'api.openai.com/v1/images/generations' => Http::response($this->imageResponse()),
        ]);

        $user = User::factory()->create();
        $product = $this->aiProduct();
        Sanctum::actingAs($user);

        $response = $this->postJson(self::ENDPOINT, [
            'product_id' => $product->id,
            'prompt' => self::VALID_PROMPT,
            'name' => 'Mon design fitness',
        ])->assertCreated()
            ->assertJsonPath('message', 'Design generated successfully.')
            ->assertJsonPath('data.status', 'generated');

        $this->assertDatabaseHas('designs', [
            'user_id' => $user->id,
            'product_id' => $product->id,
            'name' => 'Mon design fitness',
            'status' => 'generated',
            'provider' => 'openai',
        ]);

        $this->assertDatabaseHas('design_assets', [
            'design_id' => $response->json('data.id'),
            'type' => 'generated',
            'is_primary' => true,
        ]);

        $this->assertDatabaseHas('prompt_histories', [
            'user_id' => $user->id,
            'status' => 'success',
        ]);

        $this->assertNotEmpty(Storage::disk('public')->allFiles('designs'));
    }

    // Prompt moderation
    public function test_rejects_flagged_prompt_before_generating_image(): void
    {
        Http::fake([
            'api.openai.com/v1/moderations' => Http::response(
                $this->moderationResponse(flagged: true, categories: ['violence' => true, 'hate' => false])
            ),
            'api.openai.com/v1/images/generations' => Http::response($this->imageResponse()),
        ]);

        $user = User::factory()->create();
        $product = $this->aiProduct();
        Sanctum::actingAs($user);

        $this->postJson(self::ENDPOINT, [
            'product_id' => $product->id,
            'prompt' => self::VALID_PROMPT,
        ])->assertStatus(422)
            ->assertJsonPath('reason', 'prompt_flagged')
            ->assertJsonPath('categories', ['violence']);

        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/images/generations'));

        $this->assertDatabaseHas('prompt_histories', [
            'user_id' => $user->id,
            'status' => 'rejected_prompt',
        ]);
        $this->assertDatabaseEmpty('designs');
    }

    // Generated image moderation
    public function test_rejects_flagged_generated_image(): void
    {
        Storage::fake('public');

        Http::fake([
            'api.openai.com/v1/moderations' => Http::sequence()->push($this->moderationResponse(flagged: false))->push($this->moderationResponse(flagged: true, categories: ['sexual' => true])),
            'api.openai.com/v1/images/generations' => Http::response($this->imageResponse()),
        ]);

        $user = User::factory()->create();
        $product = $this->aiProduct();
        Sanctum::actingAs($user);

        $this->postJson(self::ENDPOINT, [
            'product_id' => $product->id,
            'prompt' => self::VALID_PROMPT,
        ])->assertStatus(422)
            ->assertJsonPath('reason', 'image_flagged')
            ->assertJsonPath('categories', ['sexual']);

        $this->assertDatabaseHas('prompt_histories', [
            'user_id' => $user->id,
            'status' => 'rejected_image',
        ]);
        $this->assertDatabaseEmpty('designs');
        $this->assertEmpty(Storage::disk('public')->allFiles('designs'));
    }

    // AI provider unavailability
    public function test_returns_503_when_ai_provider_fails(): void
    {
        // OpenAI responds with 429 (quota/rate-limit)
        Http::fake([
            'api.openai.com/v1/moderations' => Http::response(
                ['error' => ['message' => 'Too Many Requests']],
                429
            ),
        ]);

        $user = User::factory()->create();
        $product = $this->aiProduct();
        Sanctum::actingAs($user);

        $this->postJson(self::ENDPOINT, [
            'product_id' => $product->id,
            'prompt' => self::VALID_PROMPT,
        ])->assertStatus(503);

        $this->assertDatabaseEmpty('designs');
    }

    // Brand blocklist
    public function test_rejects_prompt_matching_brand_blocklist(): void
    {
        Http::fake();

        $user = User::factory()->create();
        $product = $this->aiProduct();
        Sanctum::actingAs($user);

        $this->postJson(self::ENDPOINT, [
            'product_id' => $product->id,
            'prompt' => 'une arme de destruction massive sur le t-shirt',
        ])->assertStatus(422)
            ->assertJsonPath('reason', 'prompt_blocked')
            ->assertJsonPath('categories', ['arme']);

        Http::assertNothingSent();

        $this->assertDatabaseHas('prompt_histories', [
            'user_id' => $user->id,
            'status' => 'rejected_blocklist',
        ]);
        $this->assertDatabaseEmpty('designs');
    }

    // Custom score threshold
    public function test_flags_prompt_when_score_exceeds_custom_threshold(): void
    {
        Http::fake([
            'api.openai.com/v1/moderations' => Http::response(['results' => [[
                'flagged' => false,
                'categories' => ['violence' => false],
                'category_scores' => ['violence' => 0.42],
            ]]]),
            'api.openai.com/v1/images/generations' => Http::response($this->imageResponse()),
        ]);

        $user = User::factory()->create();
        $product = $this->aiProduct();
        Sanctum::actingAs($user);

        $this->postJson(self::ENDPOINT, [
            'product_id' => $product->id,
            'prompt' => self::VALID_PROMPT,
        ])->assertStatus(422)
            ->assertJsonPath('reason', 'prompt_flagged')
            ->assertJsonPath('categories', ['violence']);

        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/images/generations'));
        $this->assertDatabaseEmpty('designs');
    }

    // Image generator refusal (gpt-image-1, 400)
    public function test_rejects_when_image_provider_refuses_prompt(): void
    {
        Http::fake([
            'api.openai.com/v1/moderations' => Http::response($this->moderationResponse(flagged: false)),
            'api.openai.com/v1/images/generations' => Http::response(
                ['error' => ['code' => 'moderation_blocked', 'message' => 'Your request was rejected by the safety system.']],
                400
            ),
        ]);

        $user = User::factory()->create();
        $product = $this->aiProduct();
        Sanctum::actingAs($user);

        $this->postJson(self::ENDPOINT, [
            'product_id' => $product->id,
            'prompt' => self::VALID_PROMPT,
        ])->assertStatus(422)
            ->assertJsonPath('reason', 'image_provider_rejected');

        $this->assertDatabaseHas('prompt_histories', [
            'user_id' => $user->id,
            'status' => 'rejected_image_provider',
        ]);
        $this->assertDatabaseEmpty('designs');
    }
}
