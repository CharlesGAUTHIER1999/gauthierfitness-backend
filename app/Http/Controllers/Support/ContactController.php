<?php

namespace App\Http\Controllers\Support;

use App\Http\Controllers\Controller;
use App\Mail\ContactMessageMail;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

#[Group(name: 'Contact', weight: 8)]
class ContactController extends Controller
{
    /**
     * Send a contact message.
     *
     * @unauthenticated
     *
     * @response 200 scenario="Message sent" {"message": "Message envoyé. Nous te répondrons rapidement."}
     * @response 429 scenario="Too many requests (throttle)" {"message": "Too Many Attempts."}
     */
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'subject' => ['nullable', 'string', 'max:160'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $to = config('mail.support_address') ?: config('mail.from.address');
        Mail::to($to)->send(new ContactMessageMail($data));

        return response()->json([
            'message' => 'Message envoyé. Nous te répondrons rapidement.',
        ]);
    }
}
