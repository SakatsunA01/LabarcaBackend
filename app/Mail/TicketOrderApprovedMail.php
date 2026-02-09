<?php

namespace App\Mail;

use App\Models\TicketOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TicketOrderApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public TicketOrder $order;

    public function __construct(TicketOrder $order)
    {
        $this->order = $order;
    }

    public function build()
    {
        $order = $this->order;
        $eventName = $order->event?->nombre ?? 'Evento';

        return $this->subject("Tu entrada digital - {$eventName}")
            ->view('emails.ticket_order_approved')
            ->with([
                'order' => $order,
                'event' => $order->event,
                'product' => $order->product,
                'user' => $order->user,
            ]);
    }
}
