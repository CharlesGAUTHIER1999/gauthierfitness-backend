<?php

namespace App\Services\AI;

use Illuminate\Support\Str;

/** “Brand Policy” filter: detects topics in a prompt that are explicitly prohibited (weapons, war, drugs, hate). */
class PromptBlocklist
{
    /**
     * List of forbidden terms detected in the given text.
     *
     * @return list<string>
     */
    public function matches(string $text): array
    {
        $normalized = Str::lower(Str::ascii($text));

        $matched = [];

        foreach (config('ai.moderation.blocklist', []) as $term) {
            $needle = Str::lower(Str::ascii($term));

            if ($needle === '') {
                continue;
            }

            if (preg_match('/\b'.preg_quote($needle, '/').'\b/u', $normalized)) {
                $matched[] = $term;
            }
        }

        return array_values(array_unique($matched));
    }
}
