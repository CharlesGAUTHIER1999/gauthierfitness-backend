<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;

#[Group(name: 'Authentification', weight: 1)]
class ProfileController extends Controller
{
    /**
     * Update profile.
     * Updates the authenticated user's contact and postal address details.
     *
     * @response 200 scenario="Success" { "id": 1, "firstname": "Alice", "lastname": "Dupont", "email": "alice@example.com", "phone": "0601020304", "address": "12 rue de la Paix", "zip": "75002", "city": "Paris", "is_admin": false, "roles": ["customer"] }
     * @response 422 scenario="Validation" {"message": "The zip field must not be greater than 12 characters.", "errors": {"zip": ["The zip field must not be greater than 12 characters."]}}
     */
    public function __invoke(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'firstname' => ['sometimes', 'string', 'max:100'],
            'lastname' => ['sometimes', 'string', 'max:100'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'address' => ['sometimes', 'nullable', 'string', 'max:190'],
            'zip' => ['sometimes', 'nullable', 'string', 'max:12'],
            'city' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);

        $user->fill($validated);
        $user->save();

        return response()->json([
            ...$user->authPayload(),
            'roles' => $user->roles()->pluck('name'),
        ]);
    }
}
