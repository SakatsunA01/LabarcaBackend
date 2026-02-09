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
        'paid_quantity',
        'bonus_quantity',
        'promotion_snapshot',
        'unit_price_ars',
        'currency',
        'payment_method',
        'status',
        'expires_at',
        'approved_at',
        'email_sent_at',
        'approved_by',
        'coordination_phone',
        'admin_note',
        'pickup_point_name',
        'pickup_point_map_url',
        'mp_preference_id',
        'mp_payment_id',
    ];

    protected $casts = [
        'unit_price_ars' => 'decimal:2',
        'promotion_snapshot' => 'array',
        'expires_at' => 'datetime',
        'approved_at' => 'datetime',
        'email_sent_at' => 'datetime',
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

    public function checkins()
    {
        return $this->hasMany(TicketOrderCheckin::class);
    }

    public static function expirePendingCashOrders(): int
    {
        return static::query()
            ->where('status', 'pending_cash')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->update(['status' => 'expired']);
    }
}
