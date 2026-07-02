<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;

#[Group(name: 'Authentification', weight: 1)]
class VerificationController extends Controller
{
    /**
     * Current user profile.
     * Returns the authenticated user with their roles.
     * Used by the frontend to hydrate the store and check whether the token is still valid on application startup.
     * @response 200 scenario="Succès" { "id": 1, "firstname": "Alice", "lastname": "Dupont", "email": "alice@example.com", "roles": ["customer"], "is_admin": false }
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
