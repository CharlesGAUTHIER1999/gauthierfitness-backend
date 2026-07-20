<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminMiddleware
{
    // Allow the request through only for authenticated admin users
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        // Not logged in (401)
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Logged in but not admin (403)
        if (! $user->isAdmin()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return $next($request);
    }
}
