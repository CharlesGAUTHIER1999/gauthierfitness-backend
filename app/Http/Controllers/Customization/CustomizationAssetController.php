<?php

namespace App\Http\Controllers\Customization;

use App\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Group(name: 'Customisation', weight: 4)]
class CustomizationAssetController extends Controller
{
    /**
     * Upload a logo (PNG/JPG/JPEG/WebP, ≤ 3 MB).
     * Stores the file in `storage/app/public/customization/logos/{user_id}/`
     *
     * @response 422 scenario="Invalid file" {"message": "The file must be a file of type: png, jpg, jpeg, webp."}
     */
    public function uploadLogo(Request $request): JsonResponse
    {
        return $this->storeUpload($request, 'logos', 3072, 'Logo uploadé avec succès.');
    }

    /**
     * Upload a customization image (PNG/JPG/JPEG/WebP, ≤ 5 MB).
     * Stores the file in `storage/app/public/customization/images/{user_id}/`
     *
     * @response 422 scenario="Invalid file" {"message": "The file must be a file of type: png, jpg, jpeg, webp."}
     */
    public function uploadImage(Request $request): JsonResponse
    {
        return $this->storeUpload($request, 'images', 5120, 'Image uploadée avec succès.');
    }

    private function storeUpload(Request $request, string $subdirectory, int $maxKb, string $successMessage): JsonResponse
    {
        $data = $request->validate([
            'file' => [
                'required',
                'file',
                'mimes:png,jpg,jpeg,webp',
                "max:{$maxKb}",
            ],
        ]);

        $file = $data['file'];
        $user = $request->user();

        if ($user) {
            $ownerKey = (string) $user->id;
        } else {
            $guestToken = $request->header('X-Guest-Cart-Token');
            abort_if(! $guestToken, 400, 'Missing guest cart identifier');
            $ownerKey = 'guest-'.preg_replace('/[^a-zA-Z0-9_-]/', '', $guestToken);
        }

        $extension = strtolower($file->getClientOriginalExtension() ?: 'png');
        $directory = "customization/{$subdirectory}/".$ownerKey;
        $filename = Str::uuid()->toString().'.'.$extension;
        $storedPath = $file->storeAs($directory, $filename, 'public');

        return response()->json([
            'message' => $successMessage,
            'data' => [
                'path' => $storedPath,
                'url' => Storage::disk('public')->url($storedPath),
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
            ],
        ], 201);
    }
}
