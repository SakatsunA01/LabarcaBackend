<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\PasswordResetCodeMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class PasswordResetController extends Controller
{
    private const CODE_TTL_MINUTES = 30;

    public function requestCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $email = strtolower(trim((string) $request->input('email')));
        $user = User::where('email', $email)->first();

        // Mensaje uniforme para no exponer si existe o no el email.
        if (!$user) {
            return response()->json([
                'message' => 'Si el email existe, te enviaremos un codigo de verificacion.',
            ]);
        }

        $code = (string) random_int(100000, 999999);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            [
                'token' => Hash::make($code),
                'created_at' => now(),
            ]
        );

        Mail::to($email)->send(new PasswordResetCodeMail($user, $code, self::CODE_TTL_MINUTES));

        return response()->json([
            'message' => 'Si el email existe, te enviaremos un codigo de verificacion.',
        ]);
    }

    public function resetWithCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:255',
            'code' => 'required|string|size:6',
            'password' => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $email = strtolower(trim((string) $request->input('email')));
        $code = trim((string) $request->input('code'));

        $user = User::where('email', $email)->first();
        if (!$user) {
            return response()->json(['message' => 'Codigo invalido o vencido.'], 422);
        }

        $resetRow = DB::table('password_reset_tokens')->where('email', $email)->first();
        if (!$resetRow) {
            return response()->json(['message' => 'Codigo invalido o vencido.'], 422);
        }

        $createdAt = $resetRow->created_at ? Carbon::parse($resetRow->created_at) : null;
        if (!$createdAt || $createdAt->addMinutes(self::CODE_TTL_MINUTES)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();
            return response()->json(['message' => 'Codigo vencido. Solicita uno nuevo.'], 422);
        }

        if (!Hash::check($code, (string) $resetRow->token)) {
            return response()->json(['message' => 'Codigo invalido o vencido.'], 422);
        }

        $user->password = Hash::make((string) $request->input('password'));
        $user->save();

        DB::table('password_reset_tokens')->where('email', $email)->delete();

        return response()->json(['message' => 'Contrasena actualizada correctamente.']);
    }
}
