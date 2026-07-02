<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

#[Group(name: 'Authentification', weight: 1)]
class ResetPasswordController extends Controller
{
    /**
     * Password reset.
     * Verifies the token received by email and changes the password.
     * All existing Sanctum tokens are revoked: the user must log in again everywhere after a reset.
     *
     * @unauthenticated
     *
     * @response 200 scenario="Succès" {"message": "Mot de passe réinitialisé avec succès."}
     * @response 422 scenario="Token invalide ou expiré" {"message": "This password reset token is invalid."}
     */
    public function __invoke(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
            'password' => ['required', 'min:6', 'confirmed'],
        ]);

        $status = Password::reset(
            [
                'email' => strtolower(trim($validated['email'])),
                'token' => $validated['token'],
                'password' => $validated['password'],
                'password_confirmation' => $request->input('password_confirmation'),
            ],
            function (User $user, string $password) {
                $user->password = Hash::make($password);
                $user->save();

                $user->tokens()->delete();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json(['message' => __($status)], 422);
        }

        return response()->json(['message' => 'Mot de passe réinitialisé avec succès.']);
    }
}
