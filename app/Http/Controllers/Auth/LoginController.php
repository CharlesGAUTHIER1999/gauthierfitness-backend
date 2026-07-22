<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Cart\CartMergeService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

#[Group(name: 'Authentication', weight: 1)]
class LoginController extends Controller
{
    public function __construct(private readonly CartMergeService $cart_merge_service) {}

    /**
     * User login.
     *
     * @unauthenticated
     *
     * @response 200 scenario="Success" {"token": "1|xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx", "user": { "id": 1, "firstname": "Alice", "lastname": "Dupont", "email": "alice@example.com", "is_admin": false } }
     * @response 401 scenario="Wrong credentials" {"message": "Identifiants invalides"}
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
        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json(['message' => 'Identifiants invalides'], 401);
        }
        $user->tokens()->where('name', 'react')->delete();
        $this->cart_merge_service->mergeGuestCartIntoUser($request->input('guest_cart_token'), $user);
        $token = $user->createToken('react')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user->authPayload(),
        ]);
    }
}
