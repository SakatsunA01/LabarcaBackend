<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShopOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'customer_name',
        'customer_email',
        'customer_phone',
        'delivery_method',
        'pickup_note',
        'shipping_address_line1',
        'shipping_address_line2',
        'shipping_city',
        'shipping_state',
        'shipping_postal_code',
        'shipping_country',
        'shipping_distance_km',
        'shipping_rate_per_km',
        'shipping_cost_ars',
        'shipping_quote_snapshot',
        'subtotal_ars',
        'discount_ars',
        'total_ars',
        'currency',
        'promotion_snapshot',
        'payment_method',
        'status',
        'mp_preference_id',
        'mp_payment_id',
        'mp_checkout_url',
    ];

    protected $casts = [
        'shipping_distance_km' => 'decimal:2',
        'shipping_rate_per_km' => 'decimal:2',
        'shipping_cost_ars' => 'decimal:2',
        'subtotal_ars' => 'decimal:2',
        'discount_ars' => 'decimal:2',
        'total_ars' => 'decimal:2',
        'shipping_quote_snapshot' => 'array',
        'promotion_snapshot' => 'array',
    ];

    public function items()
    {
        return $this->hasMany(ShopOrderItem::class)->orderBy('id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getGuestNameAttribute(): ?string
    {
        return $this->customer_name;
    }

    public function getGuestEmailAttribute(): ?string
    {
        return $this->customer_email;
    }

    public function getShippingArsAttribute(): float
    {
        return (float) $this->shipping_cost_ars;
    }
}
