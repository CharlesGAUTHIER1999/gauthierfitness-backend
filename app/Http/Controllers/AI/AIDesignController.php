<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateDesignRequest;
use App\Models\Design;
use App\Models\DesignAsset;
use App\Models\Product;
use App\Models\PromptHistory;
use App\Services\AI\OpenAIImageService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;

#[Group(name: 'IA — Génération de designs', weight: 5)]
class AIDesignController extends Controller
{
    /**
     * Générer un design via OpenAI à partir d'un prompt texte.
     *
     * Délègue à `OpenAIImageService` la génération d'une image, persiste le `Design`, son `DesignAsset`
     * primaire et trace l'appel dans `prompt_histories`. Le produit cible doit être customisable
     * (`is_customizable`) et autoriser l'IA (`allow_ai_generation`).
     *
     * @throws ConnectionException
     *
     * @response 422 scenario="Produit ne supporte pas l'IA" {"message": "AI generation is not allowed for this product."}
     */
    public function __invoke(
        GenerateDesignRequest $request,
        OpenAIImageService    $openAIImageService
    ): JsonResponse
    {

        $product = Product::findOrFail($request->validated('product_id'));
        abort_unless($product->is_customizable, 422, 'This product is not customizable.');
        abort_unless($product->allow_ai_generation, 422, 'AI generation is not allowed for this product.');

        $result = $openAIImageService->generateImage(
            $request->validated('prompt'),
            'fitness_design'
        );

        $design = Design::create([
            'user_id' => $request->user()->id,
            'product_id' => $request->validated('product_id'),
            'product_option_id' => $request->validated('product_option_id'),
            'name' => $request->validated('name') ?? 'Generated design',
            'prompt' => $request->validated('prompt'),
            'status' => 'generated',
            'image_path' => $result['path'],
            'preview_url' => $result['url'],
            'provider' => 'openai',
            'metadata' => $result['payload'],
            'configuration' => $request->validated('configuration') ?? null,
        ]);

        DesignAsset::create([
            'design_id' => $design->id,
            'type' => 'generated',
            'path' => $result['path'],
            'mime_type' => 'image/png',
            'size' => null,
            'is_primary' => true,
        ]);

        PromptHistory::create([
            'user_id' => $request->user()->id,
            'design_id' => $design->id,
            'prompt' => $request->validated('prompt'),
            'provider' => 'openai',
            'status' => 'success',
            'response_payload' => $result['payload'],
        ]);

        return response()->json([
            'message' => 'Design generated successfully.',
            'data' => $design->load('assets'),
        ], 201);
    }
}
