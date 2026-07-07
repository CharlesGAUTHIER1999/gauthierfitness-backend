<?php

namespace Tests\Unit\Services\AI;

use App\Services\AI\ModerationThresholdEvaluator;
use PHPUnit\Framework\TestCase;

class ModerationThresholdEvaluatorTest extends TestCase
{
    public function test_not_flagged_when_openai_says_no_and_all_scores_are_under_threshold(): void
    {
        $result = ModerationThresholdEvaluator::evaluate(
            false,
            ['violence' => false, 'hate' => false],
            ['violence' => 0.02, 'hate' => 0.01],
            0.10
        );

        $this->assertFalse($result['flagged']);
        $this->assertSame([], $result['categories']);
    }

    public function test_flagged_when_a_category_score_reaches_the_threshold(): void
    {
        $result = ModerationThresholdEvaluator::evaluate(
            false,
            ['violence' => false],
            ['violence' => 0.10],
            0.10
        );

        $this->assertTrue($result['flagged']);
        $this->assertSame(['violence'], $result['categories']);
    }

    public function test_flagged_when_openai_flags_it_even_if_no_score_reaches_the_threshold(): void
    {
        $result = ModerationThresholdEvaluator::evaluate(
            true,
            ['hate' => false],
            ['hate' => 0.0],
            0.10
        );

        $this->assertTrue($result['flagged']);
    }

    public function test_categories_merge_openai_flags_and_over_threshold_scores_without_duplicates(): void
    {
        $result = ModerationThresholdEvaluator::evaluate(
            false,
            ['hate' => true, 'violence' => false],
            ['hate' => 0.90, 'violence' => 0.15],
            0.10
        );

        $this->assertTrue($result['flagged']);
        $this->assertEqualsCanonicalizing(['hate', 'violence'], $result['categories']);
    }
}
