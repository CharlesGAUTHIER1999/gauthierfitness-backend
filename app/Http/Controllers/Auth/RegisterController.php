<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

#[Group(name: 'Authentification', weight: 1)]
class RegisterController extends Controller
{
    /**
     * Inscription d'un nouvel utilisateur.
     *
     * Crée un compte utilisateur (non-admin par défaut) et renvoie un token Sanctum permettant de l'authentifier immédiatement.
     *
     * @unauthenticated
     *
     * @response 201 scenario="Compte créé" {
     *   "token": "2|xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx",
     *   "user": { "id": 12, "firstname": "Alice", "lastname": "Dupont", "email": "alice@example.com", "is_admin": false }
     * }
     * @response 422 scenario="Email déjà utilisé" {"message": "The email has already been taken."}
     */
    public function __invoke(Request $request)
    {
        $request->validate([
            'firstname' => 'required|string',
            'lastname' => 'required|string',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:6',
        ]);

        $user = User::create([
            'firstname' => $request->firstname,
            'lastname' => $request->lastname,
            'email' => strtolower($request->email),
            'password' => Hash::make($request->password),
        ]);

        $token = $user->createToken('react')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'firstname' => $user->firstname,
                'lastname' => $user->lastname,
                'email' => $user->email,
                'is_admin' => $user->isAdmin(),
            ],
        ], 201);
    }
}
