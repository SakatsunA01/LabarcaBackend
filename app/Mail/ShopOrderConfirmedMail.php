<?php

namespace App\Mail;

use App\Models\ShopOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ShopOrderConfirmedMail extends Mailable
{
    use Queueable, SerializesModels;

    public ShopOrder $order;

    public function __construct(ShopOrder $order)
    {
        $this->order = $order;
    }

    public function build()
    {
        $order = $this->order;

        return $this->subject("Compra confirmada - Orden #{$order->id}")
            ->view('emails.shop-order-confirmed')
            ->with([
                'order' => $order,
                'items' => $order->items,
            ]);
    }
}
