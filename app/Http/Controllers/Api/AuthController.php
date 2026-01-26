<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    /**
     * Handle an incoming authentication request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials)) {
            $user = Auth::user();
            $token = $user->createToken('auth_token')->plainTextToken;
            return response()->json([
                'message' => 'Login successful',
                'user' => $user,
                'token' => $token,
            ]);
        }

        throw ValidationException::withMessages([
            'email' => ['The provided credentials do not match our records.'],
        ]);
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        return response()->json(['message' => 'Logged out successfully']);
    }

    /**
     * Handle an incoming registration request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function register(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'birth_date' => ['required', 'date'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'phone' => ['required', 'string', 'max:20'],
            'belongs_to_church' => ['required', 'boolean'],
            'church_name' => ['nullable', 'string', 'max:255', 'required_if:belongs_to_church,true'],
            'pastor_name' => ['nullable', 'string', 'max:255', 'required_if:belongs_to_church,true'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $request->name,
            'birth_date' => $request->birth_date,
            'email' => $request->email,
            'phone' => $request->phone,
            'belongs_to_church' => $request->belongs_to_church,
            'church_name' => $request->church_name,
            'pastor_name' => $request->pastor_name,
            'password' => Hash::make($request->password),
            'admin_sn' => false,
        ]);

        return response()->json([
            'message' => 'Registration successful',
            'user' => $user,
        ], 201);
    }

    /**
     * Update the authenticated user's profile.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'birth_date' => ['sometimes', 'nullable', 'date'],
            'email' => ['sometimes', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'belongs_to_church' => ['sometimes', 'boolean'],
            'church_name' => ['nullable', 'string', 'max:255', 'required_if:belongs_to_church,true'],
            'pastor_name' => ['nullable', 'string', 'max:255', 'required_if:belongs_to_church,true'],
            'password' => ['nullable', 'confirmed', Rules\Password::defaults()],
            'profile_incomplete' => ['sometimes', 'boolean'],
        ]);

        $data = $request->only([
            'name',
            'birth_date',
            'email',
            'phone',
            'belongs_to_church',
            'church_name',
            'pastor_name',
            'profile_incomplete',
        ]);

        if ($request->has('belongs_to_church') && !$request->boolean('belongs_to_church')) {
            $data['church_name'] = null;
            $data['pastor_name'] = null;
        }

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->input('password'));
        }

        $user->update($data);

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user->load('socialAccounts'),
        ]);
    }
}
