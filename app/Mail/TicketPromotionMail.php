<?php

namespace App\Mail;

use App\Models\Evento;
use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TicketPromotionMail extends Mailable
{
    use Queueable, SerializesModels;

    public Evento $event;
    public Product $product;
    public array $promotions;
    public string $ctaUrl;

    public function __construct(Evento $event, Product $product, array $promotions)
    {
        $this->event = $event;
        $this->product = $product;
        $this->promotions = $promotions;
        $base = rtrim(env('FRONTEND_URL', config('app.url') ?: 'https://labarcaministerio.com'), '/');
        $this->ctaUrl = "{$base}/eventos/{$event->id}/compra";
    }

    public function build()
    {
        return $this
            ->subject('Promocion activa en tu proximo encuentro')
            ->view('emails.ticket-promotion')
            ->with([
                'event' => $this->event,
                'product' => $this->product,
                'promotions' => $this->promotions,
                'ctaUrl' => $this->ctaUrl,
            ]);
    }
}
