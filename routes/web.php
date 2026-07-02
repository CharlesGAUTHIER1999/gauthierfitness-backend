<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Sentry monitoring diagnostic route
if (! app()->isProduction()) {
    Route::get('/debug-sentry', function () {
        throw new RuntimeException('Sentry test exception — monitoring GauthierFitness OK.');
    });
}
