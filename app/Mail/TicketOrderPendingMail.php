<?php

namespace App\Mail;

use App\Models\TicketOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TicketOrderPendingMail extends Mailable
{
    use Queueable, SerializesModels;

    public TicketOrder $order;
    public string $pendingUrl;

    public function __construct(TicketOrder $order)
    {
        $this->order = $order;
        $base = rtrim(config('app.url'), '/');
        $eventId = $order->event_id;
        $this->pendingUrl = $eventId
            ? "{$base}/eventos/{$eventId}/compra"
            : ($base . '/pago/pending');
    }

    public function build()
    {
        return $this
            ->subject('Tu compra esta pendiente - completa el pago')
            ->view('emails.ticket-order-pending')
            ->with([
                'order' => $this->order,
                'pendingUrl' => $this->pendingUrl,
            ]);
    }
}
