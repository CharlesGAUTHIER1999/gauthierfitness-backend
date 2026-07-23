<?php

// PHPUnit's <env force="true"> only affects getenv() via putenv(), not the $_ENV/$_SERVER
// superglobals that Laravel's env() helper reads from first. Since Docker injects real
// DB_* variables into the container's environment (via .env.docker), those win over the
// XML overrides and tests silently run against the MySQL dev database instead of SQLite
// in-memory — wiping local dev data on every `php artisan test`. Force all three sources
// here, before the framework boots, so the override is unambiguous everywhere.
$testEnv = [
    'APP_ENV' => 'testing',
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => ':memory:',
    'CACHE_STORE' => 'array',
    'SESSION_DRIVER' => 'array',
    'MAIL_MAILER' => 'array',
    'QUEUE_CONNECTION' => 'sync',
    'BROADCAST_CONNECTION' => 'null',
];

foreach ($testEnv as $key => $value) {
    putenv("$key=$value");
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

require __DIR__.'/../vendor/autoload.php';
