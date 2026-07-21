<?php

return [

    // AI design moderation policy
    'moderation' => [
        'threshold' => (float) env('OPENAI_MODERATION_THRESHOLD', 0.10),

        'blocklist' => [
            // Weapons
            'arme', 'armes', 'weapon', 'weapons', 'gun', 'guns', 'fusil',
            'pistolet', 'kalachnikov', 'rifle',
            // War / explosives
            'guerre', 'war', 'bombe', 'bomb', 'explosif', 'explosive',
            'missile', 'grenade', 'nucleaire', 'nuclear',
            // Terrorism
            'terroriste', 'terrorist', 'terrorisme', 'terrorism', 'attentat',
            'jihad', 'daesh',
            // Drugs
            'drogue', 'drogues', 'drug', 'drugs', 'cocaine', 'cannabis',
            'heroine', 'heroin', 'meth', 'lsd',
            // Hate symbols
            'nazi', 'nazie', 'nazisme', 'hitler', 'swastika', 'poutine', 'dictator',
        ],
    ],

];
