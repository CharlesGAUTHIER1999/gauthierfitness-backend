<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class VerificationController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'id'        => $user->id,
            'firstname' => $user->firstname,
            'lastname'  => $user->lastname,
            'email'     => $user->email,
            'roles' => $user->roles()->pluck('name'),
            'is_admin'  => $user->isAdmin(),
        ]);
    }
}
