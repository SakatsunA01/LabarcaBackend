<?php

namespace App\Mail;

use App\Models\Evento;
use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class EventInstitutionalInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public Evento $event;
    public Product $product;
    public array $promotions;
    public array $lineup;
    public string $ctaUrl;
    public string $churchName;
    public string $pastorRole;
    public string $customSubject;

    public function __construct(
        Evento $event,
        Product $product,
        array $promotions,
        array $lineup = [],
        string $churchName = '',
        string $pastorRole = '',
        ?string $customSubject = null
    ) {
        $this->event = $event;
        $this->product = $product;
        $this->promotions = $promotions;
        $this->lineup = $lineup;
        $this->churchName = $churchName;
        $this->pastorRole = $pastorRole;
        $this->customSubject = $customSubject ?: 'Invitacion Institucional: Su Iglesia en el Teatro Opera (Ilumina Argentina)';

        $base = rtrim(env('FRONTEND_URL', config('app.url') ?: 'https://labarcaministerio.com'), '/');
        $this->ctaUrl = "{$base}/eventos/{$event->id}/compra";
    }

    public function build()
    {
        return $this
            ->subject($this->customSubject)
            ->view('emails.event-institutional-invitation')
            ->with([
                'event' => $this->event,
                'product' => $this->product,
                'promotions' => $this->promotions,
                'lineup' => $this->lineup,
                'ctaUrl' => $this->ctaUrl,
                'churchName' => $this->churchName,
                'pastorRole' => $this->pastorRole,
            ]);
    }
}
