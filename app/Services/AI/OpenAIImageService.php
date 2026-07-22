<?php

namespace App\Services\AI;

use App\Exceptions\AiContentRejectedException;
use App\Exceptions\AiServiceUnavailableException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class OpenAIImageService
{
    /**
     * Generates an image using OpenAI
     *
     * @return array{b64: string, payload: array}
     *
     * @throws AiContentRejectedException if prompt rejected by gpt-image-1
     * @throws AiServiceUnavailableException if service is unreachable/down
     */
    public function generate(string $prompt): array
    {
        try {
            $response = Http::withToken(config('services.openai.key'))
                ->timeout(120)
                ->post('https://api.openai.com/v1/images/generations', [
                    'model' => config('services.openai.image_model', 'gpt-image-1'),
                    'prompt' => $prompt,
                    'size' => '1024x1024',
                    'quality' => 'medium',
                ]);
        } catch (ConnectionException $e) {
            throw new AiServiceUnavailableException('OpenAI image generation unreachable.', $e);
        }

        if ($response->failed()) {
            if ($response->status() === 400) {
                throw new AiContentRejectedException('OpenAI image generation rejected the prompt: '.$response->body());
            }
            throw new AiServiceUnavailableException('OpenAI image generation failed: '.$response->body());
        }

        $payload = $response->json();
        $base64 = $payload['data'][0]['b64_json'] ?? null;
        if (! $base64) {
            throw new AiServiceUnavailableException('No image returned by OpenAI.');
        }

        return ['b64' => $base64, 'payload' => $payload];
    }

    /**
     * Writes a base64-encoded image to public disk
     *
     * @return array{path: string, url: string}
     */
    public function store(string $base64, string $filename_prefix = 'design'): array
    {
        $binary = base64_decode($base64);
        $path = 'designs/'.uniqid($filename_prefix.'_').'.png';
        Storage::disk('public')->put($path, $binary);

        return [
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
        ];
    }
}
