<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserSocialAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SocialAuthController extends Controller
{
    public function google(Request $request)
    {
        $request->validate([
            'id_token' => ['required', 'string'],
        ]);

        $payload = $this->getGooglePayload($request->input('id_token'));

        $googleId = $payload['sub'] ?? null;
        $email = $payload['email'] ?? null;
        $name = $payload['name'] ?? 'Usuario';
        $avatarUrl = $payload['picture'] ?? null;
        $emailVerified = ($payload['email_verified'] ?? 'false') === 'true';

        if (!$googleId || !$email) {
            throw ValidationException::withMessages([
                'id_token' => ['Token de Google incompleto.'],
            ]);
        }

        $account = UserSocialAccount::where('provider', 'google')
            ->where('provider_id', $googleId)
            ->first();

        if ($account) {
            $user = $account->user;
        } else {
            $user = User::where('email', $email)->first();

            if (!$user) {
                $user = User::create([
                    'name' => $name,
                    'email' => $email,
                    'password' => Hash::make(Str::random(40)),
                    'belongs_to_church' => false,
                    'profile_incomplete' => true,
                ]);

                if ($emailVerified) {
                    $user->forceFill(['email_verified_at' => now()])->save();
                }
            }

            UserSocialAccount::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'provider' => 'google',
                ],
                [
                    'provider_id' => $googleId,
                    'email' => $email,
                    'avatar_url' => $avatarUrl,
                    'raw_profile' => $payload,
                ]
            );
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'user' => $user->load('socialAccounts'),
            'token' => $token,
        ]);
    }

    public function linkGoogle(Request $request)
    {
        $request->validate([
            'id_token' => ['required', 'string'],
        ]);

        $user = $request->user();
        $payload = $this->getGooglePayload($request->input('id_token'));

        $googleId = $payload['sub'] ?? null;
        $email = $payload['email'] ?? null;
        $avatarUrl = $payload['picture'] ?? null;

        if (!$googleId || !$email) {
            throw ValidationException::withMessages([
                'id_token' => ['Token de Google incompleto.'],
            ]);
        }

        $existingAccount = UserSocialAccount::where('provider', 'google')
            ->where('provider_id', $googleId)
            ->first();

        if ($existingAccount && $existingAccount->user_id !== $user->id) {
            return response()->json([
                'message' => 'Esta cuenta de Google ya esta vinculada a otro usuario.',
            ], 409);
        }

        UserSocialAccount::updateOrCreate(
            [
                'user_id' => $user->id,
                'provider' => 'google',
            ],
            [
                'provider_id' => $googleId,
                'email' => $email,
                'avatar_url' => $avatarUrl,
                'raw_profile' => $payload,
            ]
        );

        return response()->json([
            'message' => 'Cuenta de Google vinculada.',
            'user' => $user->load('socialAccounts'),
        ]);
    }

    private function getGooglePayload(string $idToken): array
    {
        $response = Http::get('https://oauth2.googleapis.com/tokeninfo', [
            'id_token' => $idToken,
        ]);

        if (!$response->ok()) {
            throw ValidationException::withMessages([
                'id_token' => ['Token de Google invalido.'],
            ]);
        }

        $payload = $response->json();
        $clientId = config('services.google.client_id');

        if ($clientId && ($payload['aud'] ?? null) !== $clientId) {
            throw ValidationException::withMessages([
                'id_token' => ['El token no corresponde a este proyecto.'],
            ]);
        }

        return $payload;
    }
}
