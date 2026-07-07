<?php

namespace Tests\Unit\Services\AI;

use App\Services\AI\PromptBlocklist;
use PHPUnit\Framework\TestCase;

class PromptBlocklistTest extends TestCase
{
    private PromptBlocklist $blocklist;

    protected function setUp(): void
    {
        parent::setUp();
        $this->blocklist = new PromptBlocklist;
    }

    public function test_returns_empty_list_when_no_forbidden_term_is_present(): void
    {
        $matches = $this->blocklist->matches('un t-shirt bleu avec un logo montagne', ['arme', 'guerre']);

        $this->assertSame([], $matches);
    }

    public function test_detects_a_forbidden_term_as_a_whole_word(): void
    {
        $matches = $this->blocklist->matches('un design avec une arme au centre', ['arme', 'guerre']);

        $this->assertSame(['arme'], $matches);
    }

    public function test_does_not_match_a_forbidden_term_inside_another_word(): void
    {
        $matches = $this->blocklist->matches('une armee de fans dans les gradins', ['arme']);

        $this->assertSame([], $matches);
    }

    public function test_is_case_and_accent_insensitive(): void
    {
        $matches = $this->blocklist->matches('GUERRE totale sur le logo', ['guerre']);

        $this->assertSame(['guerre'], $matches);
    }

    public function test_returns_each_matched_term_only_once(): void
    {
        $matches = $this->blocklist->matches('arme, encore une arme', ['arme']);

        $this->assertSame(['arme'], $matches);
    }
}
