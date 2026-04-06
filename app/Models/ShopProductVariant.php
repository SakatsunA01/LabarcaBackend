<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShopProductVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'shop_product_id',
        'sku',
        'label',
        'color',
        'size',
        'price_ars',
        'stock',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'price_ars' => 'decimal:2',
        'stock' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function product()
    {
        return $this->belongsTo(ShopProduct::class, 'shop_product_id');
    }

    public function getNameAttribute(): ?string
    {
        return $this->label;
    }
}
