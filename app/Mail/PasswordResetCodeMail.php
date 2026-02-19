<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PasswordResetCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public User $user;
    public string $code;
    public int $ttlMinutes;
    public string $resetUrl;

    public function __construct(User $user, string $code, int $ttlMinutes = 30)
    {
        $this->user = $user;
        $this->code = $code;
        $this->ttlMinutes = $ttlMinutes;

        $base = rtrim(env('FRONTEND_URL', config('app.url') ?: 'https://labarcaministerio.com'), '/');
        $this->resetUrl = $base . '/recuperar-password';
    }

    public function build()
    {
        return $this
            ->subject('Codigo de verificacion para cambiar tu contrasena')
            ->view('emails.password-reset-code')
            ->with([
                'user' => $this->user,
                'code' => $this->code,
                'ttlMinutes' => $this->ttlMinutes,
                'resetUrl' => $this->resetUrl,
            ]);
    }
}
