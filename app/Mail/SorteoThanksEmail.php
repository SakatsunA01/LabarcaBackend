<?php

namespace App\Mail;

use App\Models\Sorteo;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SorteoThanksEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Sorteo $sorteo,
        public readonly ?User $user
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Gracias por participar — ' . $this->sorteo->nombre,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.sorteo-thanks',
        );
    }
}
