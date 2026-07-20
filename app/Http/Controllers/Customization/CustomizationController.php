<?php

namespace App\Http\Controllers\Customization;

use App\Http\Controllers\Controller;
use App\Models\CustomProductSession;
use App\Models\Design;
use App\Models\Product;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group(name: 'Customisation', weight: 4)]
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

    /**
     * Create a customization session
     * @response 422 scenario="Product is not customizable" {"message": "This product is not customizable."}
     * @response 403 scenario="Design belongs to another user" {}
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'product_option_id' => ['nullable', 'exists:product_options,id'],
            'configuration' => ['nullable', 'array'],
            'design_id' => ['nullable', 'exists:designs,id'],
            'preview_image_path' => ['nullable', 'string'],
        ]);

        $product = Product::findOrFail($data['product_id']);
        abort_unless($product->is_customizable, 422, 'This product is not customizable.');
        $user = $request->user('sanctum');
        $guest_token = null;

        if (!$user) {
            $guest_token = $request->header('X-Guest-Cart-Token');
            abort_if(!$guest_token, 400, 'Missing guest cart identifier');
            abort_if(!empty($data['design_id']), 422, 'Design does not match the selected product.');
        }

        if (!empty($data['design_id'])) {
            $design = Design::findOrFail($data['design_id']);
            abort_unless((int)$design->user_id === (int)$user->id, 403);
            abort_unless((int)$design->product_id === (int)$product->id, 422, 'Design does not match the selected product.');
        }

        $option = !empty($data['product_option_id']) ? $product->options()->find($data['product_option_id']) : null;
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
            'message' => 'Customization session created.',
            'data' => $session->load(self::SESSION_RELATIONS),
        ], 201);
    }

    /**
     * Retrieve a customization session.
     * @response 403 scenario="Session belongs to another user" {}
     */
    public function show(Request $request, CustomProductSession $customizationSession): JsonResponse
    {
        $this->authorizeOwner($customizationSession, $request);
        return response()->json(['data' => $customizationSession->load(self::SESSION_RELATIONS),]);
    }

    /**
     * Update a customization session
     * @response 403 scenario="Session belongs to another user" {}
     */
    public function update(Request $request, CustomProductSession $customizationSession): JsonResponse
    {
        $this->authorizeOwner($customizationSession, $request);

        $data = $request->validate([
            'configuration' => ['nullable', 'array'],
            'design_id' => ['nullable', 'exists:designs,id'],
            'preview_image_path' => ['nullable', 'string'],
            'status' => ['nullable', 'in:draft,ready,added_to_cart,ordered'],
        ]);

        if (!empty($data['design_id'])) {
            abort_unless($request->user('sanctum'), 403);
            $design = Design::findOrFail($data['design_id']);
            abort_unless((int)$design->user_id === (int)$request->user('sanctum')->id, 403);
            abort_unless((int)$design->product_id === (int)$customizationSession->product_id, 422, 'Design does not match the customization session product.');
        }

        $customizationSession->update([
            'configuration' => $data['configuration'] ?? $customizationSession->configuration,
            'design_id' => $data['design_id'] ?? $customizationSession->design_id,
            'preview_image_path' => array_key_exists('preview_image_path', $data) ? $data['preview_image_path'] : $customizationSession->preview_image_path,
            'status' => $data['status'] ?? $customizationSession->status,
        ]);

        return response()->json([
            'message' => 'Customization session updated.',
            'data' => $customizationSession->fresh(self::SESSION_RELATIONS),
        ]);
    }

    // Abort with 403 unless the requester owns the session
    protected function authorizeOwner(CustomProductSession $session, Request $request): void
    {
        if ($user = $request->user('sanctum')) {
            abort_unless((int)$session->user_id === (int)$user->id, 403);
            return;
        }

        $guest_token = $request->header('X-Guest-Cart-Token');
        abort_unless($guest_token && $session->guest_token === $guest_token, 403);
    }
}
