<?php

namespace App\Services\AI;

use App\Exceptions\AiServiceUnavailableException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Vérifie qu'un contenu (texte ou image) respecte les règles de contenu via
 * l'API Moderation d'OpenAI (`omni-moderation-latest`), qui détecte notamment
 * la haine, le harcèlement, la violence, le contenu sexuel et l'automutilation.
 */
class OpenAIModerationService
{
    /**
     * Modère un prompt texte.
     *
     * @return array{flagged: bool, categories: list<string>, payload: array}
     *
     * @throws AiServiceUnavailableException
     */
    public function moderateText(string $text): array
    {
        return $this->moderate([
            ['type' => 'text', 'text' => $text],
        ]);
    }

    /**
     * Modère une image fournie en base64 (envoyée en data URL à l'API).
     *
     * @return array{flagged: bool, categories: list<string>, payload: array}
     *
     * @throws AiServiceUnavailableException
     */
    public function moderateImage(string $base64, string $mimeType = 'image/png'): array
    {
        return $this->moderate([
            ['type' => 'image_url', 'image_url' => ['url' => "data:{$mimeType};base64,{$base64}"]],
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $input
     * @return array{flagged: bool, categories: list<string>, payload: array}
     *
     * @throws AiServiceUnavailableException
     */
    private function moderate(array $input): array
    {
        try {
            $response = Http::withToken(config('services.openai.key'))
                ->timeout(30)
                ->post('https://api.openai.com/v1/moderations', [
                    'model' => config('services.openai.moderation_model', 'omni-moderation-latest'),
                    'input' => $input,
                ]);
        } catch (ConnectionException $e) {
            throw new AiServiceUnavailableException('OpenAI moderation unreachable.', $e);
        }

        if ($response->failed()) {
            throw new AiServiceUnavailableException('OpenAI moderation failed: '.$response->body());
        }

        $result = $response->json('results.0', []);

        $threshold = (float) config('ai.moderation.threshold', 0.10);

        // Catégories signalées par OpenAI (booléen `true`)...
        $flaggedByOpenAi = collect($result['categories'] ?? [])
            ->filter(fn ($flagged) => $flagged === true)
            ->keys();

        // ...complétées par celles dont le score dépasse notre seuil custom
        // (plus strict que les seuils par défaut d'OpenAI).
        $overThreshold = collect($result['category_scores'] ?? [])
            ->filter(fn ($score) => (float) $score >= $threshold)
            ->keys();

        $categories = $flaggedByOpenAi->merge($overThreshold)->unique()->values()->all();

        return [
            'flagged' => (bool) ($result['flagged'] ?? false) || $overThreshold->isNotEmpty(),
            'categories' => $categories,
            'payload' => $result,
        ];
    }
}
