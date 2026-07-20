<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Sentry monitoring
if (! app()->isProduction()) {
    Route::get('/debug-sentry', function () {
        throw new RuntimeException('Sentry test exception — monitoring GauthierFitness OK.');
    });
}
