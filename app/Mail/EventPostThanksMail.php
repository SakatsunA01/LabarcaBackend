<?php

namespace App\Mail;

use App\Models\Evento;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class EventPostThanksMail extends Mailable
{
    use Queueable, SerializesModels;

    public Evento $event;
    public string $frontendUrl;
    public string $eventUrl;
    public string $galleryUrl;
    public string $testimonyUrl;

    public function __construct(Evento $event)
    {
        $this->event = $event;
        $this->frontendUrl = rtrim(env('FRONTEND_URL', config('app.url') ?: 'https://labarcaministerio.com'), '/');
        $this->eventUrl = "{$this->frontendUrl}/eventos/{$event->id}";
        $this->galleryUrl = "{$this->eventUrl}#galeria";
        $this->testimonyUrl = "{$this->eventUrl}#deja-tu-testimonio";
    }

    public function build()
    {
        return $this
            ->subject("Gracias por ser parte de {$this->event->nombre} | La Barca Ministerio")
            ->view('emails.event-post-thanks')
            ->with([
                'event' => $this->event,
                'frontendUrl' => $this->frontendUrl,
                'eventUrl' => $this->eventUrl,
                'galleryUrl' => $this->galleryUrl,
                'testimonyUrl' => $this->testimonyUrl,
            ]);
    }
}
