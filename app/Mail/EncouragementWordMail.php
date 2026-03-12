<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class EncouragementWordMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $recipientName;
    public array $payload;

    public function __construct(string $recipientName, array $payload)
    {
        $this->recipientName = $recipientName;
        $this->payload = $payload;
    }

    public function build()
    {
        return $this
            ->subject('Tu Palabra de Animo')
            ->view('emails.encouragement-word')
            ->with([
                'recipientName' => $this->recipientName,
                'payload' => $this->payload,
            ]);
    }
}

