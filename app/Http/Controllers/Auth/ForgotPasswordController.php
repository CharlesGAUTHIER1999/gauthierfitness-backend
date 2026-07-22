<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

#[Group(name: 'Authentication', weight: 1)]
class ForgotPasswordController extends Controller
{
    /**
     * Password reset request
     *
     * @unauthenticated
     *
     * @response 200 scenario="Success" {"message": "Si un compte existe pour cet email, un lien de réinitialisation a été envoyé."}
     * @response 422 scenario="Validation" {"message": "The email field is required.", "errors": {"email": ["The email field is required."]}}
     * @response 429 scenario="Too many attempts" {"message": "Too Many Attempts."}
     */
    public function __invoke(Request $request)
    {
        $validated = $request->validate(['email' => ['required', 'email']]);
        Password::sendResetLink(['email' => strtolower(trim($validated['email']))]);

        return response()->json(['message' => 'Si un compte existe pour cet email, un lien de réinitialisation a été envoyé.']);
    }
}
