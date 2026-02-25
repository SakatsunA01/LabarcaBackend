<?php

namespace App\Mail;

use App\Models\Evento;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class EventBuyersNoticeMail extends Mailable
{
    use Queueable, SerializesModels;

    public Evento $event;
    public string $frontendUrl;

    public function __construct(Evento $event)
    {
        $this->event = $event;
        $this->frontendUrl = rtrim(env('FRONTEND_URL', config('app.url') ?: 'https://labarcaministerio.com'), '/');
    }

    public function build()
    {
        return $this
            ->subject('🏛️ COMUNICADO URGENTE: Reprogramación y Mejora VIP - La Barca Music')
            ->view('emails.event-buyers-notice')
            ->with([
                'event' => $this->event,
                'frontendUrl' => $this->frontendUrl,
            ]);
    }
}
