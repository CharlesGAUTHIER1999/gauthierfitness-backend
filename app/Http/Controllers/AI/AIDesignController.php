<?php

namespace App\Http\Controllers\AI;

use App\Exceptions\AiContentRejectedException;
use App\Exceptions\AiServiceUnavailableException;
use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateDesignRequest;
use App\Models\Design;
use App\Models\DesignAsset;
use App\Models\Product;
use App\Models\PromptHistory;
use App\Services\AI\OpenAIImageService;
use App\Services\AI\OpenAIModerationService;
use App\Services\AI\PromptBlocklist;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

#[Group(name: 'IA - Génération de designs', weight: 5)]
class AIDesignController extends Controller
{
    /**
     * Generate a design via OpenAI from a text prompt.
     *
     * @throws AiServiceUnavailableException if OpenAI is unreachable or errors out (rendered as 503)
     *
     * @response 422 scenario="Produit ne supporte pas l'IA" {"message": "AI generation is not allowed for this product."}
     * @response 422 scenario="Prompt rejeté par la modération" {"message": "Votre demande ne respecte pas nos règles de contenu et ne peut pas être générée.", "reason": "prompt_flagged", "categories": ["violence"]}
     * @response 422 scenario="Image rejetée par la modération" {"message": "L'image générée ne respecte pas nos règles de contenu et a été rejetée.", "reason": "image_flagged", "categories": ["sexual"]}
     */
    public function __invoke(
        GenerateDesignRequest $request,
        OpenAIImageService $images,
        OpenAIModerationService $moderation,
        PromptBlocklist $blocklist
    ): JsonResponse {

        $product = Product::findOrFail($request->validated('product_id'));
        abort_unless($product->is_customizable, 422, 'This product is not customizable.');
        abort_unless($product->allow_ai_generation, 422, 'AI generation is not allowed for this product.');
        $prompt = $request->validated('prompt');
        $userId = $request->user()->id;
        set_time_limit(180);

        // 1 - "Brand policy" blocklist, before any API call.
        $bannedTerms = $blocklist->matches($prompt);

        if (! empty($bannedTerms)) {
            PromptHistory::create([
                'user_id' => $userId,
                'prompt' => $prompt,
                'provider' => 'openai',
                'status' => 'rejected_blocklist',
                'response_payload' => ['blocked_terms' => $bannedTerms],
            ]);

            return response()->json([
                'message' => 'Votre demande contient un thème interdit et ne peut pas être générée.',
                'reason' => 'prompt_blocked',
                'categories' => $bannedTerms,
            ], 422);
        }

        // 2 - Prompt moderation, before any image generation.
        $promptCheck = $moderation->moderateText($prompt);

        if ($promptCheck['flagged']) {
            PromptHistory::create([
                'user_id' => $userId,
                'prompt' => $prompt,
                'provider' => 'openai',
                'status' => 'rejected_prompt',
                'response_payload' => $promptCheck['payload'],
            ]);

            return response()->json([
                'message' => 'Votre demande ne respecte pas nos règles de contenu et ne peut pas être générée.',
                'reason' => 'prompt_flagged',
                'categories' => $promptCheck['categories'],
            ], 422);
        }

        // 3 - Image generation (not stored at this stage)
        try {
            $generated = $images->generate($prompt);
        } catch (AiContentRejectedException) {
            PromptHistory::create([
                'user_id' => $userId,
                'prompt' => $prompt,
                'provider' => 'openai',
                'status' => 'rejected_image_provider',
            ]);

            return response()->json([
                'message' => "Votre demande a été refusée par le générateur d'images (contenu non autorisé).",
                'reason' => 'image_provider_rejected',
            ], 422);
        }

        // 4 - Moderation of the generated image, before any storage
        $imageCheck = $moderation->moderateImage($generated['b64']);

        if ($imageCheck['flagged']) {
            PromptHistory::create([
                'user_id' => $userId,
                'prompt' => $prompt,
                'provider' => 'openai',
                'status' => 'rejected_image',
                'response_payload' => $imageCheck['payload'],
            ]);

            return response()->json([
                'message' => "L'image générée ne respecte pas nos règles de contenu et a été rejetée.",
                'reason' => 'image_flagged',
                'categories' => $imageCheck['categories'],
            ], 422);
        }

        // 5 - Content validated
        $stored = $images->store($generated['b64'], 'fitness_design');

        $design = Design::create([
            'user_id' => $userId,
            'product_id' => $request->validated('product_id'),
            'product_option_id' => $request->validated('product_option_id'),
            'name' => $request->validated('name') ?? 'Generated design',
            'prompt' => $prompt,
            'status' => 'generated',
            'image_path' => $stored['path'],
            'preview_url' => $stored['url'],
            'provider' => 'openai',
            'metadata' => $generated['payload'],
            'configuration' => $request->validated('configuration') ?? null,
        ]);

        DesignAsset::create([
            'design_id' => $design->id,
            'type' => 'generated',
            'path' => $stored['path'],
            'mime_type' => 'image/png',
            'size' => null,
            'is_primary' => true,
        ]);

        PromptHistory::create([
            'user_id' => $userId,
            'design_id' => $design->id,
            'prompt' => $prompt,
            'provider' => 'openai',
            'status' => 'success',
            'response_payload' => $generated['payload'],
        ]);

        return response()->json([
            'message' => 'Design generated successfully.',
            'data' => $design->load('assets'),
        ], 201);
    }
}
