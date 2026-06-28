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
     * Génère une image via OpenAI et retourne son contenu base64 SANS l'écrire
     * sur disque. La persistance est volontairement séparée (voir `store()`) afin
     * de pouvoir modérer l'image générée avant tout stockage.
     *
     * @return array{b64: string, payload: array}
     *
     * @throws AiContentRejectedException si le prompt est refusé par gpt-image-1 (400)
     * @throws AiServiceUnavailableException si le service est injoignable ou en panne
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
                ]);
        } catch (ConnectionException $e) {
            throw new AiServiceUnavailableException('OpenAI image generation unreachable.', $e);
        }

        if ($response->failed()) {
            // gpt-image-1 refuse les prompts contraires à sa politique avec un 400 :
            // c'est un rejet de contenu, pas une panne de service.
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

        return [
            'b64' => $base64,
            'payload' => $payload,
        ];
    }

    /**
     * Écrit une image base64 sur le disque public et retourne son chemin + URL.
     *
     * @return array{path: string, url: string}
     */
    public function store(string $base64, string $filenamePrefix = 'design'): array
    {
        $binary = base64_decode($base64);
        $path = 'designs/'.uniqid($filenamePrefix.'_').'.png';

        Storage::disk('public')->put($path, $binary);

        return [
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
        ];
    }
}
