<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;

#[Group(name: 'Authentification', weight: 1)]
class VerificationController extends Controller
{
    /**
     * Profil utilisateur courant.
     *
     * Renvoie l'utilisateur authentifié avec ses rôles. Sert au frontend pour hydrater le store
     * et vérifier si le token est toujours valide au démarrage de l'application.
     *
     * @response 200 scenario="Succès" {
     *   "id": 1, "firstname": "Alice", "lastname": "Dupont",
     *   "email": "alice@example.com", "roles": ["customer"], "is_admin": false
     * }
     */
    public function __invoke(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'id' => $user->id,
            'firstname' => $user->firstname,
            'lastname' => $user->lastname,
            'email' => $user->email,
            'roles' => $user->roles()->pluck('name'),
            'is_admin' => $user->isAdmin(),
        ]);
    }
}
