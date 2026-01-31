<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        // si pas connecté => 401 (plus correct)
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // connecté mais pas admin => 403
        if (! $user->isAdmin()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return $next($request);
    }

}