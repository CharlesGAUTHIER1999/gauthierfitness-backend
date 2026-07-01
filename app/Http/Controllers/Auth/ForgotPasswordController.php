<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

#[Group(name: 'Authentification', weight: 1)]
class ForgotPasswordController extends Controller
{
    /**
     * Demande de réinitialisation de mot de passe.
     *
     * Envoie un email contenant un lien de réinitialisation si un compte existe pour cet email.
     * Renvoie toujours le même message générique, que l'email existe ou non, pour éviter
     * qu'un attaquant ne puisse déduire quels emails sont enregistrés (OWASP A01 - user enumeration).
     *
     * @unauthenticated
     *
     * @response 200 scenario="Succès" {"message": "Si un compte existe pour cet email, un lien de réinitialisation a été envoyé."}
     * @response 422 scenario="Validation" {"message": "The email field is required.", "errors": {"email": ["The email field is required."]}}
     * @response 429 scenario="Trop de tentatives" {"message": "Too Many Attempts."}
     */
    public function __invoke(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        Password::sendResetLink(['email' => strtolower(trim($validated['email']))]);

        return response()->json([
            'message' => 'Si un compte existe pour cet email, un lien de réinitialisation a été envoyé.',
        ]);
    }
}
