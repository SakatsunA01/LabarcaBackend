<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShopProductMedia extends Model
{
    use HasFactory;

    protected $fillable = [
        'shop_product_id',
        'media_type',
        'url',
        'thumbnail_url',
        'alt_text',
        'is_primary',
        'sort_order',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function product()
    {
        return $this->belongsTo(ShopProduct::class, 'shop_product_id');
    }

    public function getTypeAttribute(): string
    {
        return $this->media_type;
    }

    public function getIsFeaturedAttribute(): bool
    {
        return (bool) $this->is_primary;
    }
}
