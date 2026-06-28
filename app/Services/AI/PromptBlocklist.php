<?php

namespace App\Services\AI;

use Illuminate\Support\Str;

/**
 * Filtre « politique de marque » : détecte dans un prompt les thèmes
 * explicitement interdits (armes, guerre, drogue, haine…) que la modération
 * générique d'OpenAI ne flague pas forcément. Comparaison par mot entier,
 * insensible à la casse et aux accents.
 */
class PromptBlocklist
{
    /**
     * Retourne la liste des termes interdits détectés dans le texte.
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
