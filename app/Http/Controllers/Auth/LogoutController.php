<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;

#[Group(name: 'Authentification', weight: 1)]
class LogoutController extends Controller
{
    /**
     * Logout
     * Revokes the current Sanctum token. The next call will require a new login.
     * @response 200 scenario="Success" {"message": "ok"}
     * @response 401 scenario="Missing or invalid token" {"message": "Unauthenticated"}
     */
    public function __invoke(Request $request)
    {
        $user = $request->user();
        if (!$user) return response()->json(['message' => 'Unauthenticated',], 401);
        $token = $user->currentAccessToken();
        if ($token) $token->delete();
        return response()->json(['message' => 'ok']);
    }
}
