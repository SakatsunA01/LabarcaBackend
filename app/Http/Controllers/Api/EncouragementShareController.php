<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\EncouragementWordMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class EncouragementShareController extends Controller
{
    public function sendToAuthenticatedUser(Request $request)
    {
        $user = $request->user();
        if (!$user || !$user->email) {
            return response()->json([
                'message' => 'No encontramos un correo asociado a tu cuenta.',
            ], 422);
        }

        $payload = $request->validate([
            'moodKey' => ['nullable', 'string', 'max:80'],
            'moodText' => ['nullable', 'string', 'max:120'],
            'verseCitation' => ['required', 'string', 'max:255'],
            'verseText' => ['required', 'string', 'max:5000'],
            'initialReflection' => ['required', 'string', 'max:10000'],
            'context' => ['nullable', 'array'],
            'context.authorAndDate' => ['nullable', 'string', 'max:5000'],
            'context.locationAndSociety' => ['nullable', 'string', 'max:5000'],
            'context.originalMeaning' => ['nullable', 'string', 'max:5000'],
            'prayer' => ['nullable', 'string', 'max:10000'],
            'pageUrl' => ['nullable', 'string', 'max:1000'],
        ]);

        Mail::to($user->email)->send(new EncouragementWordMail(
            $user->name ?: 'amigo/a',
            $payload
        ));

        return response()->json([
            'message' => 'Palabra enviada a tu correo.',
        ]);
    }
}

