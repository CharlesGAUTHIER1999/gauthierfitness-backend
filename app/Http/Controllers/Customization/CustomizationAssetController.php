<?php

namespace App\Http\Controllers\Customization;

use App\Exceptions\AiServiceUnavailableException;
use App\Http\Controllers\Controller;
use App\Services\AI\OpenAIModerationService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Group(name: 'Customization', weight: 4)]
class CustomizationAssetController extends Controller
{
    /**
     * Upload a logo (PNG/JPG/JPEG/WebP, ≤ 3 MB)
     *
     * @throws AiServiceUnavailableException if the moderation service is unreachable (rendered as 503)
     *
     * @response 422 scenario="Invalid file" {"message": "The file must be a file of type: png, jpg, jpeg, webp."}
     * @response 422 scenario="Image rejected by moderation" {"message": "Cette image ne respecte pas nos règles de contenu et a été refusée.", "reason": "image_flagged", "categories": ["violence"]}
     * @response 422 scenario="Image rejected for prohibited visual content (weapons, drugs, hate symbols)" {"message": "Cette image contient un élément interdit sur nos produits (armes, drogues ou symboles haineux) et ne peut pas être utilisée.", "reason": "visual_content_flagged", "details": "Depicts a firearm"}
     */
    public function uploadLogo(Request $request, OpenAIModerationService $moderation): JsonResponse
    {
        return $this->storeUpload($request, $moderation, 'logos', 3072, 'Logo uploadé avec succès.');
    }

    /**
     * Upload a customization image (PNG/JPG/JPEG/WebP, ≤ 5 MB).
     *
     * @throws AiServiceUnavailableException if the moderation service is unreachable (rendered as 503)
     *
     * @response 422 scenario="Invalid file" {"message": "The file must be a file of type: png, jpg, jpeg, webp."}
     * @response 422 scenario="Image rejected by moderation" {"message": "Cette image ne respecte pas nos règles de contenu et a été refusée.", "reason": "image_flagged", "categories": ["violence"]}
     * @response 422 scenario="Image rejected for prohibited visual content (weapons, drugs, hate symbols)" {"message": "Cette image contient un élément interdit sur nos produits (armes, drogues ou symboles haineux) et ne peut pas être utilisée.", "reason": "visual_content_flagged", "details": "Depicts a firearm"}
     */
    public function uploadImage(Request $request, OpenAIModerationService $moderation): JsonResponse
    {
        return $this->storeUpload($request, $moderation, 'images', 5120, 'Image uploadée avec succès.');
    }

    private function storeUpload(Request $request, OpenAIModerationService $moderation, string $subdirectory, int $max_kb, string $success_message): JsonResponse
    {
        $data = $request->validate(['file' => ['required', 'file', 'mimes:png,jpg,jpeg,webp', "max:$max_kb"]]);
        $file = $data['file'];
        $base64 = base64_encode(file_get_contents($file->getRealPath()));

        // Reject content that violates our policy
        $check = $moderation->moderateImage($base64, $file->getMimeType());

        if ($check['flagged']) {
            return response()->json([
                'message' => 'Cette image ne respecte pas nos règles de contenu et a été refusée.',
                'reason' => 'image_flagged',
                'categories' => $check['categories'],
            ], 422);
        }

        // Second pass: catches content outside OpenAI's standard moderation categories
        $visual_check = $moderation->detectProhibitedVisualContent($base64, $file->getMimeType());

        if ($visual_check['flagged']) {
            return response()->json([
                'message' => 'Cette image contient un élément interdit sur nos produits (armes, drogues ou symboles haineux) et ne peut pas être utilisée.',
                'reason' => 'visual_content_flagged',
                'details' => $visual_check['reason'],
            ], 422);
        }

        $user = $request->user('sanctum');

        if ($user) {
            $owner_key = (string) $user->id;
        } else {
            $guest_token = $request->header('X-Guest-Cart-Token');
            abort_if(! $guest_token, 400, 'Identifiant de panier invité manquant');
            $owner_key = 'guest-'.preg_replace('/[^a-zA-Z0-9_-]/', '', $guest_token);
        }

        $extension = strtolower($file->getClientOriginalExtension() ?: 'png');
        $directory = "customization/$subdirectory/".$owner_key;
        $filename = Str::uuid()->toString().'.'.$extension;
        $stored_path = $file->storeAs($directory, $filename, 'public');

        return response()->json([
            'message' => $success_message,
            'data' => [
                'path' => $stored_path,
                'url' => Storage::disk('public')->url($stored_path),
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
            ],
        ], 201);
    }
}
