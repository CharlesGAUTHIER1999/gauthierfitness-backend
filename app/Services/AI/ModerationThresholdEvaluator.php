<?php

namespace App\Services\AI;

// Pure decision logic for OpenAI moderation
class ModerationThresholdEvaluator
{
    /**
     * @param  array<string, bool>  $flaggedCategories  OpenAI's own boolean flags per category
     * @param  array<string, float>  $categoryScores  OpenAI's numeric scores per category
     * @return array{flagged: bool, categories: list<string>}
     */
    public static function evaluate(bool $openAiFlagged, array $flaggedCategories, array $categoryScores, float $threshold): array
    {
        $flagged_by_openai = collect($flaggedCategories)->filter(fn ($flagged) => $flagged === true)->keys();
        $over_threshold = collect($categoryScores)->filter(fn ($score) => (float) $score >= $threshold)->keys();
        $categories = $flagged_by_openai->merge($over_threshold)->unique()->values()->all();

        return [
            'flagged' => $openAiFlagged || $over_threshold->isNotEmpty(),
            'categories' => $categories,
        ];
    }
}
