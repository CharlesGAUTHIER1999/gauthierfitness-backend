<?php

namespace App\Http\Controllers;

use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Group(name: 'Customisation', weight: 4)]
class CustomizationAssetController extends Controller
{
    /**
     * Uploader un logo (PNG/JPG/JPEG/WebP, ≤ 3 Mo).
     *
     * Stocke le fichier dans `storage/app/public/customization/logos/{user_id}/` et renvoie l'URL
     * publique servie via le disque `public`. Utilisé par l'éditeur de personnalisation 2D.
     *
     * @response 422 scenario="Fichier invalide" {"message": "The file must be a file of type: png, jpg, jpeg, webp."}
     */
    public function uploadLogo(Request $request): JsonResponse
    {
        $data = $request->validate([
            'file' => [
                'required',
                'file',
                'mimes:png,jpg,jpeg,webp',
                'max:3072',
            ],
        ]);

        $file = $data['file'];

        $user = $request->user();
        $extension = strtolower($file->getClientOriginalExtension() ?: 'png');

        $directory = 'customization/logos/' . $user->id;
        $filename = Str::uuid()->toString() . '.' . $extension;

        $storedPath = $file->storeAs($directory, $filename, 'public');

        return response()->json([
            'message' => 'Logo uploadé avec succès.',
            'data' => [
                'path' => $storedPath,
                'url' => Storage::disk('public')->url($storedPath),
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
            ],
        ], 201);
    }

    /**
     * Uploader une image de personnalisation (PNG/JPG/JPEG/WebP, ≤ 5 Mo).
     *
     * Stocke le fichier dans `storage/app/public/customization/images/{user_id}/`.
     *
     * @response 422 scenario="Fichier invalide" {"message": "The file must be a file of type: png, jpg, jpeg, webp."}
     */
    public function uploadImage(Request $request): JsonResponse
    {
        $data = $request->validate([
            'file' => [
                'required',
                'file',
                'mimes:png,jpg,jpeg,webp',
                'max:5120',
            ],
        ]);

        $file = $data['file'];

        $user = $request->user();
        $extension = strtolower($file->getClientOriginalExtension() ?: 'png');

        $directory = 'customization/images/' . $user->id;
        $filename = Str::uuid()->toString() . '.' . $extension;

        $storedPath = $file->storeAs($directory, $filename, 'public');

        return response()->json([
            'message' => 'Image uploadée avec succès.',
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
