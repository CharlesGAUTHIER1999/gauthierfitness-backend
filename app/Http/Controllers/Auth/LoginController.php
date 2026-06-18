<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

#[Group(name: 'Authentification', weight: 1)]
class LoginController extends Controller
{
    /**
     * Connexion utilisateur.
     *
     * Authentifie un utilisateur via email/mot de passe et renvoie un token Sanctum.
     * Le token précédent nommé "react" est révoqué pour limiter la session à un seul appareil actif.
     *
     * @unauthenticated
     *
     * @response 200 scenario="Succès" {
     *   "token": "1|xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx",
     *   "user": { "id": 1, "firstname": "Alice", "lastname": "Dupont", "email": "alice@example.com", "is_admin": false }
     * }
     * @response 401 scenario="Identifiants invalides" {"message": "Invalid credentials"}
     * @response 422 scenario="Validation" {"message": "The email field is required.", "errors": {"email": ["The email field is required."]}}
     */
    public function __invoke(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $email = strtolower(trim($validated['email']));

        $user = User::where('email', $email)->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        // Optionnel mais recommandé : 1 seul token "react" par user
        $user->tokens()->where('name', 'react')->delete();

        $token = $user->createToken('react')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $this->userPayload($user),
        ]);
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'firstname' => $user->firstname,
            'lastname' => $user->lastname,
            'email' => $user->email,
            'is_admin' => $user->isAdmin(),
        ];
    }
}
