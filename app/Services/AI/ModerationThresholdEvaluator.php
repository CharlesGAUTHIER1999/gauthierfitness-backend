<?php

namespace App\Services\AI;

/** Pure decision logic for OpenAI moderation results — no HTTP, no config lookup. */
class ModerationThresholdEvaluator
{
    /**
     * @param  array<string, bool>  $flaggedCategories  OpenAI's own boolean flags per category
     * @param  array<string, float>  $categoryScores  OpenAI's numeric scores per category
     * @return array{flagged: bool, categories: list<string>}
     */
    public static function evaluate(bool $openAiFlagged, array $flaggedCategories, array $categoryScores, float $threshold): array
    {
        $flaggedByOpenAi = collect($flaggedCategories)->filter(fn ($flagged) => $flagged === true)->keys();
        $overThreshold = collect($categoryScores)->filter(fn ($score) => (float) $score >= $threshold)->keys();
        $categories = $flaggedByOpenAi->merge($overThreshold)->unique()->values()->all();

        return [
            'flagged' => $openAiFlagged || $overThreshold->isNotEmpty(),
            'categories' => $categories,
        ];
    }
}
