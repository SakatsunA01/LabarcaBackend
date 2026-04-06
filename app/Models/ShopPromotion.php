<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class ShopPromotion extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'name',
        'description',
        'promotion_type',
        'discount_percent',
        'discount_amount_ars',
        'combo_price_ars',
        'buy_qty',
        'get_qty',
        'is_active',
        'starts_at',
        'ends_at',
    ];

    protected $casts = [
        'discount_percent' => 'decimal:2',
        'discount_amount_ars' => 'decimal:2',
        'combo_price_ars' => 'decimal:2',
        'buy_qty' => 'integer',
        'get_qty' => 'integer',
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function products()
    {
        return $this->belongsToMany(ShopProduct::class, 'shop_promotion_shop_product', 'shop_promotion_id', 'shop_product_id')
            ->withPivot('required_quantity')
            ->withTimestamps();
    }

    public function isActiveNow(?Carbon $moment = null): bool
    {
        $moment = $moment ?: now();

        if (!$this->is_active) {
            return false;
        }

        if ($this->starts_at && $moment->lt($this->starts_at)) {
            return false;
        }

        if ($this->ends_at && $moment->gt($this->ends_at)) {
            return false;
        }

        return true;
    }
}
