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

    public function event()
    {
        return $this->belongsTo(Evento::class, 'event_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
