<?php

namespace App\Services\AI;

use Illuminate\Support\Str;

// Brand Policy filter : detects topics in a prompt that are explicitly prohibited
class PromptBlocklist
{
    /**
     * List of forbidden terms detected in the given text.
     * @param  list<string>|null  $blocklist  Overrides the configured blocklist (mainly for testing).
     * @return list<string>
     */
    public function matches(string $text, ?array $blocklist = null): array
    {
        $normalized = Str::lower(Str::ascii($text));
        $matched = [];

        foreach ($blocklist ?? config('ai.moderation.blocklist', []) as $term) {
            $needle = Str::lower(Str::ascii($term));
            if ($needle === '') continue;
            if (preg_match('/\b'.preg_quote($needle, '/').'\b/u', $normalized)) $matched[] = $term;
        }

        return array_values(array_unique($matched));
    }
}
