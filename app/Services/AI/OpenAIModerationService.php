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
    public function moderateImage(string $base64, string $mime_type = 'image/png'): array
    {
        return $this->moderate([['type' => 'image_url', 'image_url' => ['url' => "data:$mime_type;base64,$base64"]]]);
    }

    /**
     * Detects content prohibited on printed clothing but outside OpenAI's standard moderation categories
     * (e.g. weapons, drugs), using a vision-capable chat model with a dedicated classification prompt.
     *
     * @return array{flagged: bool, reason: ?string, payload: array}
     *
     * @throws AiServiceUnavailableException
     */
    public function detectProhibitedVisualContent(string $base64, string $mime_type = 'image/png'): array
    {
        try {
            $response = Http::withToken(config('services.openai.key'))
                ->timeout(30)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => config('services.openai.vision_moderation_model', 'gpt-4o-mini'),
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'You moderate images uploaded to a fitness clothing customization store, to be '.
                                'printed on garments sold to the public. Reply with only a JSON object: '.
                                '{"prohibited": boolean, "reason": string}. Set "prohibited" to true if the image depicts '.
                                'weapons or firearms, illegal drugs, or extremist/hate symbols. Otherwise set it to false.',
                        ],
                        [
                            'role' => 'user',
                            'content' => [
                                ['type' => 'image_url', 'image_url' => ['url' => "data:$mime_type;base64,$base64"]],
                            ],
                        ],
                    ],
                ]);
        } catch (ConnectionException $e) {
            throw new AiServiceUnavailableException('OpenAI vision moderation unreachable.', $e);
        }

        if ($response->failed()) {
            throw new AiServiceUnavailableException('OpenAI vision moderation failed: '.$response->body());
        }

        $result = json_decode($response->json('choices.0.message.content', '{}'), true) ?? [];

        return [
            'flagged' => (bool) ($result['prohibited'] ?? false),
            'reason' => $result['reason'] ?? null,
            'payload' => $result,
        ];
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
