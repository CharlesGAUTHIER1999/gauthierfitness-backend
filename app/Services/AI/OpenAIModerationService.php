<?php

namespace App\Services\AI;

use App\Exceptions\AiServiceUnavailableException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

// Checks whether content (text or images) complies with content guidelines using OpenAI's Moderation API
class OpenAIModerationService
{
    /**
     * Moderates a text prompt
     *
     * @return array{flagged: bool, categories: list<string>, payload: array}
     *
     * @throws AiServiceUnavailableException
     */
    public function moderateText(string $text): array
    {
        return $this->moderate([['type' => 'text', 'text' => $text]]);
    }

    /**
     * Moderates an image provided in base64 format (sent as a data URL to the API)
     *
     * @return array{flagged: bool, categories: list<string>, payload: array}
     *
     * @throws AiServiceUnavailableException
     */
    public function moderateImage(string $base64, string $mimeType = 'image/png'): array
    {
        return $this->moderate([['type' => 'image_url', 'image_url' => ['url' => "data:$mimeType;base64,$base64"]]]);
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

        $evaluation = ModerationThresholdEvaluator::evaluate(
            (bool) ($result['flagged'] ?? false),
            $result['categories'] ?? [],
            $result['category_scores'] ?? [],
            $threshold
        );

        return [
            'flagged' => $evaluation['flagged'],
            'categories' => $evaluation['categories'],
            'payload' => $result,
        ];
    }
}
