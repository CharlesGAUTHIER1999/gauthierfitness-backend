<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Politique de modération des designs IA
    |--------------------------------------------------------------------------
    |
    | threshold : seuil de score (0.0 → 1.0) au-delà duquel une catégorie de
    | l'API Moderation OpenAI est considérée comme déclenchée, EN PLUS du
    | `flagged` natif d'OpenAI (dont les seuils par défaut sont volontairement
    | conservateurs). Plus bas = plus strict.
    |
    | blocklist : thèmes explicitement interdits par la marque, indépendamment
    | des scores OpenAI (ex. armes/guerre/drogue, que la modération générique
    | ne flague pas forcément). Comparaison par mot entier, insensible à la
    | casse et aux accents.
    |
    */

    'moderation' => [
        'threshold' => (float) env('OPENAI_MODERATION_THRESHOLD', 0.10),

        'blocklist' => [
            // Armes
            'arme', 'armes', 'weapon', 'weapons', 'gun', 'guns', 'fusil',
            'pistolet', 'kalachnikov', 'rifle',
            // Guerre / explosifs
            'guerre', 'war', 'bombe', 'bomb', 'explosif', 'explosive',
            'missile', 'grenade', 'nucleaire', 'nuclear',
            // Terrorisme
            'terroriste', 'terrorist', 'terrorisme', 'terrorism', 'attentat',
            'jihad', 'daesh',
            // Drogues
            'drogue', 'drogues', 'drug', 'drugs', 'cocaine', 'cannabis',
            'heroine', 'heroin', 'meth', 'lsd',
            // Symboles haineux
            'nazi', 'nazie', 'nazisme', 'hitler', 'swastika',
        ],
    ],

];
