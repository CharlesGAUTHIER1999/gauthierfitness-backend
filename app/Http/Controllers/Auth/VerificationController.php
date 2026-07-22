<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;

#[Group(name: 'Authentication', weight: 1)]
class VerificationController extends Controller
{
    /**
     * Current user profile.
     * Returns authenticated user with their roles
     *
     * @response 200 scenario="Success" { "id": 1, "firstname": "Alice", "lastname": "Dupont", "email": "alice@example.com", "roles": ["customer"], "is_admin": false }
     */
    public function __invoke(Request $request)
    {
        $user = $request->user();

        return response()->json([...$user->authPayload(), 'roles' => $user->roles()->pluck('name')]);
    }
}
