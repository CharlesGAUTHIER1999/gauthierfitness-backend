<?php

return [

    'moderation' => [

        'threshold' => (float) env('OPENAI_MODERATION_THRESHOLD', 0.10),

        'blocklist' => [

            /*
            |--------------------------------------------------------------------------
            | Terrorism / Extremism
            |--------------------------------------------------------------------------
            */

            'terrorist',
            'terroriste',
            'terrorism',
            'terrorisme',
            'attentat',
            'extremist',
            'extrémiste',
            'isis',
            'isil',
            'daesh',
            'al qaeda',
            'al-qaida',
            'hamas',
            'hamas propaganda',

            /*
            |--------------------------------------------------------------------------
            | Hate symbols
            |--------------------------------------------------------------------------
            */

            'nazi',
            'nazism',
            'nazisme',
            'neo nazi',
            'néonazi',
            'hitler',
            'führer',
            'fuhrer',
            'third reich',
            'troisième reich',
            'gestapo',
            'ss-totenkopf',
            'totenkopf',
            'swastika',
            'croix gammée',
            'white supremacy',
            'white power',
            'ku klux klan',
            'kkk',

            /*
            |--------------------------------------------------------------------------
            | Graphic violence
            |--------------------------------------------------------------------------
            */

            'gore',
            'graphic violence',
            'graphic gore',
            'decapitation',
            'dismemberment',
            'corpse',
            'dead body',
            'cadavre',
            'mutilation',
            'execution',
            'massacre',

            /*
            |--------------------------------------------------------------------------
            | Self harm
            |--------------------------------------------------------------------------
            */

            'suicide',
            'suicidal',
            'self harm',
            'self-harm',
            'automutilation',

            /*
            |--------------------------------------------------------------------------
            | Drugs
            |--------------------------------------------------------------------------
            */

            'cocaine',
            'crack',
            'meth',
            'methamphetamine',
            'crystal meth',
            'heroin',
            'heroine',
            'ecstasy',
            'mdma',
            'lsd',
            'ketamine',
            'fentanyl',
            'opioid',

            /*
            |--------------------------------------------------------------------------
            | Weapons
            |--------------------------------------------------------------------------
            */

            'arme',
            'armes',
            'weapon',
            'weapons',
            'gun',
            'guns',
            'fusil',
            'pistolet',
            'ak47',
            'ak-47',
            'kalashnikov',
            'kalachnikov',
            'sniper rifle',
            'machine gun',
            'flamethrower',

            /*
            |--------------------------------------------------------------------------
            | Explosives
            |--------------------------------------------------------------------------
            */

            'guerre',
            'war',
            'bombe',
            'bomb',
            'explosif',
            'explosive',
            'missile',
            'grenade',
            'nucleaire',
            'nucléaire',
            'nuclear',
            'atomic bomb',
            'nuclear bomb',
            'bombe atomique',
            'bombe nucléaire',
            'chemical weapon',
            'biological weapon',
            'arme chimique',
            'arme biologique',
            'improvised explosive device',
            'ied',
            'dynamite',

            /*
            |--------------------------------------------------------------------------
            | Child exploitation
            |--------------------------------------------------------------------------
            */

            'child pornography',
            'child porn',
            'underage sexual',
            'sexualized child',
            'sexualised child',
            'pedophile',
            'pedophilia',
            'pédophile',
            'pédophilie',
            'grooming',

            /*
            |--------------------------------------------------------------------------
            | Adult explicit
            |--------------------------------------------------------------------------
            */

            'porn',
            'pornography',
            'xxx',
            'nsfw',
            'explicit sexual',
            'hardcore porn',
            'hentai',

        ],
    ],

];
