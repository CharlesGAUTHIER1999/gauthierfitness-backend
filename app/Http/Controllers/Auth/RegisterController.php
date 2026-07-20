<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Cart\CartMergeService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

#[Group(name: 'Authentification', weight: 1)]
class RegisterController extends Controller
{
    public function __construct(private readonly CartMergeService $cartMergeService)
    {
    }

    /**
     * Register a new user
     * Creates a user account (non-admin by default)
     * @unauthenticated
     * @response 201 scenario="Account created" { "token": "2|xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx", "user": { "id": 12, "firstname": "Alice", "lastname": "Dupont", "email": "alice@example.com", "is_admin": false } }
     * @response 422 scenario="Email already used" {"message": "The email has already been taken."}
     */
    public function __invoke(Request $request)
    {
        $request->validate([
            'firstname' => 'required|string',
            'lastname' => 'required|string',
            'email' => 'required|email|unique:users',
            'password' => ['required', 'min:6', 'confirmed'],
        ]);

        $user = User::create([
            'firstname' => $request->firstname,
            'lastname' => $request->lastname,
            'email' => strtolower($request->email),
            'password' => Hash::make($request->password),
        ]);

        $this->cartMergeService->mergeGuestCartIntoUser($request->input('guest_cart_token'), $user);
        $token = $user->createToken('react')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user->authPayload(),
        ], 201);
    }
}
