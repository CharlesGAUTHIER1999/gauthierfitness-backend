<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Route de diagnostic du monitoring Sentry : déclenche volontairement une
// exception non gérée pour vérifier qu'elle est bien capturée et remontée.
// Désactivée en production pour ne pas exposer d'endpoint d'erreur.
if (! app()->isProduction()) {
    Route::get('/debug-sentry', function () {
        throw new RuntimeException('Sentry test exception — monitoring GauthierFitness OK.');
    });
}
