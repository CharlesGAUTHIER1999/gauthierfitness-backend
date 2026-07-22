<?php

namespace App\Services\AI;

use Illuminate\Support\Str;

// Brand Policy filter
class PromptBlocklist
{
    /**
     * List of forbidden terms detected in the given text.
     *
     * @param  list<string>|null  $blocklist  Overrides configured blocklist (mainly for testing).
     * @return list<string>
     */
    public function matches(string $text, ?array $blocklist = null): array
    {
        $normalized = $this->normalize($text);
        $matched = [];

        foreach ($blocklist ?? config('ai.moderation.blocklist', []) as $term) {
            $needle = $this->normalize($term);
            if ($needle === '') {
                continue;
            }
            if (preg_match('/\b'.preg_quote($needle, '/').'\b/u', $normalized)) {
                $matched[] = $term;
            }
        }

        return array_values(array_unique($matched));
    }

    // Lowercases, strips accents, and maps common leetspeak substitutions
    private function normalize(string $text): string
    {
        $text = Str::lower(Str::ascii($text));

        return strtr($text, [
            '0' => 'o', '1' => 'i', '3' => 'e', '4' => 'a', '5' => 's',
            '7' => 't', '@' => 'a', '$' => 's', '!' => 'i', '|' => 'i',
        ]);
    }
}
