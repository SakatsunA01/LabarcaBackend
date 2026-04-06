<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShopOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'shop_order_id',
        'shop_product_id',
        'shop_product_variant_id',
        'name_snapshot',
        'sku_snapshot',
        'quantity',
        'unit_price_ars',
        'discount_ars',
        'line_total_ars',
        'product_snapshot',
        'variant_snapshot',
        'promotion_snapshot',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price_ars' => 'decimal:2',
        'discount_ars' => 'decimal:2',
        'line_total_ars' => 'decimal:2',
        'product_snapshot' => 'array',
        'variant_snapshot' => 'array',
        'promotion_snapshot' => 'array',
    ];

    public function order()
    {
        return $this->belongsTo(ShopOrder::class, 'shop_order_id');
    }

    public function product()
    {
        return $this->belongsTo(ShopProduct::class, 'shop_product_id');
    }

    public function variant()
    {
        return $this->belongsTo(ShopProductVariant::class, 'shop_product_variant_id');
    }

    public function getProductNameAttribute(): string
    {
        return $this->name_snapshot;
    }

    public function getVariantNameAttribute(): ?string
    {
        return $this->variant_snapshot['label'] ?? null;
    }

    public function getTotalArsAttribute(): float
    {
        return (float) $this->line_total_ars;
    }
}
