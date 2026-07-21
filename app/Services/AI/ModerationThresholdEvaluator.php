<?php

namespace App\Services\AI;

// Pure decision logic for OpenAI moderation
class ModerationThresholdEvaluator
{
    /**
     * @param  array<string, bool>  $flagged_categories  OpenAI's own boolean flags per category
     * @param  array<string, float>  $category_scores  OpenAI's numeric scores per category
     * @return array{flagged: bool, categories: list<string>}
     */
    public static function evaluate(bool $open_ai_flagged, array $flagged_categories, array $category_scores, float $threshold): array
    {
        $flagged_by_openai = collect($flagged_categories)->filter(fn ($flagged) => $flagged === true)->keys();
        $over_threshold = collect($category_scores)->filter(fn ($score) => (float) $score >= $threshold)->keys();
        $categories = $flagged_by_openai->merge($over_threshold)->unique()->values()->all();

        return [
            'flagged' => $open_ai_flagged || $over_threshold->isNotEmpty(),
            'categories' => $categories,
        ];
    }
}
