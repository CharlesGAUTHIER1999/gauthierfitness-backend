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

#[Group(name: 'AI - Design Generation', weight: 5)]
class AIDesignController extends Controller
{
    /**
     * Generate a design via OpenAI from a text prompt
     *
     * @throws AiServiceUnavailableException if OpenAI is unreachable or errors out (rendered as 503)
     *
     * @response 422 scenario="Product does not support AI generation" {"message": "AI generation is not allowed for this product."}
     * @response 422 scenario="Prompt rejected by moderation" {"message": "Votre demande ne respecte pas nos règles de contenu et ne peut pas être générée.", "reason": "prompt_flagged", "categories": ["violence"]}
     * @response 422 scenario="Image rejected by moderation" {"message": "L'image générée ne respecte pas nos règles de contenu et a été rejetée.", "reason": "image_flagged", "categories": ["sexual"]}
     */
    public function __invoke(GenerateDesignRequest $request, OpenAIImageService $images, OpenAIModerationService $moderation, PromptBlocklist $blocklist): JsonResponse
    {
        $product = Product::findOrFail($request->validated('product_id'));
        abort_unless($product->is_customizable, 422, 'This product is not customizable.');
        abort_unless($product->allow_ai_generation, 422, 'AI generation is not allowed for this product.');
        $prompt = $request->validated('prompt');
        $user_id = $request->user()->id;
        set_time_limit(180);

        // 1 - Brand policy blocklist
        $banned_terms = $blocklist->matches($prompt);

        if (! empty($banned_terms)) {
            PromptHistory::create([
                'user_id' => $user_id,
                'prompt' => $prompt,
                'provider' => 'openai',
                'status' => 'rejected_blocklist',
                'response_payload' => ['blocked_terms' => $banned_terms],
            ]);

            return response()->json([
                'message' => 'Votre demande contient un thème interdit et ne peut pas être générée.',
                'reason' => 'prompt_blocked',
                'categories' => $banned_terms,
            ], 422);
        }

        // 2 - Prompt moderation
        $prompt_check = $moderation->moderateText($prompt);

        if ($prompt_check['flagged']) {
            PromptHistory::create([
                'user_id' => $user_id,
                'prompt' => $prompt,
                'provider' => 'openai',
                'status' => 'rejected_prompt',
                'response_payload' => $prompt_check['payload'],
            ]);

            return response()->json([
                'message' => 'Votre demande ne respecte pas nos règles de contenu et ne peut pas être générée.',
                'reason' => 'prompt_flagged',
                'categories' => $prompt_check['categories'],
            ], 422);
        }

        // 3 - Image generation
        try {
            $generated = $images->generate($prompt);
        } catch (AiContentRejectedException $e) {
            PromptHistory::create([
                'user_id' => $user_id,
                'prompt' => $prompt,
                'provider' => 'openai',
                'status' => 'rejected_image_provider',
                'response_payload' => ['detail' => $e->getMessage()],
            ]);

            return response()->json([
                'message' => "Votre demande a été refusée par le générateur d'images (contenu non autorisé).",
                'reason' => 'image_provider_rejected',
            ], 422);
        }

        // 4 - Moderation of the generated image
        $image_check = $moderation->moderateImage($generated['b64']);

        if ($image_check['flagged']) {
            PromptHistory::create([
                'user_id' => $user_id,
                'prompt' => $prompt,
                'provider' => 'openai',
                'status' => 'rejected_image',
                'response_payload' => $image_check['payload'],
            ]);

            return response()->json([
                'message' => "L'image générée ne respecte pas nos règles de contenu et a été rejetée.",
                'reason' => 'image_flagged',
                'categories' => $image_check['categories'],
            ], 422);
        }

        // 5 - Content validated
        $stored = $images->store($generated['b64'], 'fitness_design');

        $design = Design::create([
            'user_id' => $user_id,
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
            'user_id' => $user_id,
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
