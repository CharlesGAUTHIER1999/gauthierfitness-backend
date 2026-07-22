<?php

namespace App\Http\Controllers\Customization;

use App\Http\Controllers\Controller;
use App\Models\CustomProductSession;
use App\Models\Design;
use App\Models\Product;
use App\Services\AI\PromptBlocklist;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group(name: 'Customization', weight: 4)]
class CustomizationController extends Controller
{
    private const array SESSION_RELATIONS = [
        'product.group',
        'product.categories',
        'product.images',
        'product.mainImage',
        'product.hoverImage',
        'productOption',
        'design',
    ];

    private const array CONFIGURATION_VALIDATION_MESSAGES = [
        'configuration.player_number.value.regex' => 'Le numéro doit être composé uniquement de chiffres (3 maximum).',
        'configuration.player_name.value.max' => 'Le nom du joueur ne peut pas dépasser 20 caractères.',
        'configuration.text_layers.*.text.max' => 'Le texte ne peut pas dépasser 30 caractères.',
    ];

    /**
     * Create a customization session
     *
     * @response 422 scenario="Product is not customizable" {"message": "Ce produit n'est pas personnalisable."}
     * @response 403 scenario="Design belongs to another user" {}
     */
    public function store(Request $request, PromptBlocklist $blocklist): JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'product_option_id' => ['nullable', 'exists:product_options,id'],
            'configuration' => ['nullable', 'array'],
            'design_id' => ['nullable', 'exists:designs,id'],
            'preview_image_path' => ['nullable', 'string'],
        ]);

        // Separate pass : enforced for format only
        $request->validate([
            'configuration.player_number.value' => ['nullable', 'string', 'regex:/^[0-9]{0,3}$/'],
            'configuration.player_name.value' => ['nullable', 'string', 'max:20'],
            'configuration.text_layers.*.text' => ['nullable', 'string', 'max:30'],
        ], self::CONFIGURATION_VALIDATION_MESSAGES);

        $this->rejectBlockedText($data['configuration'] ?? [], $blocklist);

        $product = Product::findOrFail($data['product_id']);
        abort_unless($product->is_customizable, 422, "Ce produit n'est pas personnalisable.");
        $user = $request->user('sanctum');
        $guest_token = null;

        if (! $user) {
            $guest_token = $request->header('X-Guest-Cart-Token');
            abort_if(! $guest_token, 400, 'Identifiant de panier invité manquant');
            abort_if(! empty($data['design_id']), 422, 'Le design ne correspond pas au produit sélectionné.');
        }

        if (! empty($data['design_id'])) {
            $design = Design::findOrFail($data['design_id']);
            abort_unless((int) $design->user_id === (int) $user->id, 403);
            abort_unless((int) $design->product_id === (int) $product->id, 422, 'Le design ne correspond pas au produit sélectionné.');
        }

        $option = ! empty($data['product_option_id']) ? $product->options()->find($data['product_option_id']) : null;
        $unit_price = $option?->price_ttc ?? $product->price_ttc;

        $session = CustomProductSession::create([
            'user_id' => $user?->id,
            'guest_token' => $guest_token,
            'product_id' => $data['product_id'],
            'product_option_id' => $data['product_option_id'] ?? null,
            'status' => 'draft',
            'configuration' => $data['configuration'] ?? [],
            'design_id' => $data['design_id'] ?? null,
            'preview_image_path' => $data['preview_image_path'] ?? null,
            'unit_price_snapshot' => $unit_price,
        ]);

        return response()->json([
            'message' => 'Session de personnalisation créée.',
            'data' => $session->load(self::SESSION_RELATIONS),
        ], 201);
    }

    /**
     * Retrieve a customization session.
     *
     * @response 403 scenario="Session belongs to another user" {}
     */
    public function show(Request $request, CustomProductSession $customization_session): JsonResponse
    {
        $this->authorizeOwner($customization_session, $request);

        return response()->json(['data' => $customization_session->load(self::SESSION_RELATIONS)]);
    }

    /**
     * Update a customization session
     *
     * @response 403 scenario="Session belongs to another user" {}
     */
    public function update(Request $request, CustomProductSession $customization_session, PromptBlocklist $blocklist): JsonResponse
    {
        $this->authorizeOwner($customization_session, $request);

        $data = $request->validate([
            'configuration' => ['nullable', 'array'],
            'design_id' => ['nullable', 'exists:designs,id'],
            'preview_image_path' => ['nullable', 'string'],
            'status' => ['nullable', 'in:draft,ready,added_to_cart,ordered'],
        ]);

        // Separate pass: see the comment in store() above.
        $request->validate([
            'configuration.player_number.value' => ['nullable', 'string', 'regex:/^[0-9]{0,3}$/'],
            'configuration.player_name.value' => ['nullable', 'string', 'max:20'],
            'configuration.text_layers.*.text' => ['nullable', 'string', 'max:30'],
        ], self::CONFIGURATION_VALIDATION_MESSAGES);

        $this->rejectBlockedText($data['configuration'] ?? [], $blocklist);

        if (! empty($data['design_id'])) {
            abort_unless($request->user('sanctum'), 403);
            $design = Design::findOrFail($data['design_id']);
            abort_unless((int) $design->user_id === (int) $request->user('sanctum')->id, 403);
            abort_unless((int) $design->product_id === (int) $customization_session->product_id, 422, 'Le design ne correspond pas au produit de la session de personnalisation.');
        }

        $customization_session->update([
            'configuration' => $data['configuration'] ?? $customization_session->configuration,
            'design_id' => $data['design_id'] ?? $customization_session->design_id,
            'preview_image_path' => array_key_exists('preview_image_path', $data) ? $data['preview_image_path'] : $customization_session->preview_image_path,
            'status' => $data['status'] ?? $customization_session->status,
        ]);

        return response()->json([
            'message' => 'Session de personnalisation mise à jour.',
            'data' => $customization_session->fresh(self::SESSION_RELATIONS),
        ]);
    }

    // Abort with 422 if any free-text field (player name, text layers) contains a blocked term
    protected function rejectBlockedText(array $configuration, PromptBlocklist $blocklist): void
    {
        $texts = [];

        if (! empty($configuration['player_name']['value'])) {
            $texts[] = $configuration['player_name']['value'];
        }

        foreach ($configuration['text_layers'] ?? [] as $layer) {
            if (! empty($layer['text'])) {
                $texts[] = $layer['text'];
            }
        }

        foreach ($texts as $text) {
            abort_if(! empty($blocklist->matches($text)), 422, 'Votre texte contient un terme interdit et ne peut pas être utilisé.');
        }
    }

    // Abort with 403 unless requester owns the session
    protected function authorizeOwner(CustomProductSession $session, Request $request): void
    {
        if ($user = $request->user('sanctum')) {
            abort_unless((int) $session->user_id === (int) $user->id, 403);

            return;
        }

        $guest_token = $request->header('X-Guest-Cart-Token');
        abort_unless($guest_token && $session->guest_token === $guest_token, 403);
    }
}
