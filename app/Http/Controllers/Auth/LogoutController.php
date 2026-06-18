<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;

#[Group(name: 'Authentification', weight: 1)]
class LogoutController extends Controller
{
    /**
     * Déconnexion.
     *
     * Révoque le token Sanctum courant. L'appel suivant nécessitera un nouveau login.
     *
     * @response 200 scenario="Succès" {"message": "ok"}
     * @response 401 scenario="Token absent ou invalide" {"message": "Unauthenticated"}
     */
    public function __invoke(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated',
            ], 401);
        }

        $token = $user->currentAccessToken();

        if ($token) {
            $token->delete();
        }

        return response()->json(['message' => 'ok']);
    }
}
