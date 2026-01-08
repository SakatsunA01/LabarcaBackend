<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TicketOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'product_id',
        'user_id',
        'quantity',
        'unit_price_ars',
        'currency',
        'status',
        'mp_preference_id',
        'mp_payment_id',
    ];

    protected $casts = [
        'unit_price_ars' => 'decimal:2',
    ];
}
