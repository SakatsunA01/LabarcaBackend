<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShopProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'shop_category_id',
        'shop_product_type_id',
        'name',
        'slug',
        'description',
        'price_ars',
        'stock',
        'image_url',
        'is_active',
        'is_featured',
        'featured_order',
        'track_stock',
    ];

    protected $casts = [
        'price_ars' => 'decimal:2',
        'stock' => 'integer',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'featured_order' => 'integer',
        'track_stock' => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(ShopCategory::class, 'shop_category_id');
    }

    public function type()
    {
        return $this->belongsTo(ShopProductType::class, 'shop_product_type_id');
    }

    public function productType()
    {
        return $this->type();
    }

    public function variants()
    {
        return $this->hasMany(ShopProductVariant::class)->orderBy('sort_order');
    }

    public function media()
    {
        return $this->hasMany(ShopProductMedia::class)->orderBy('sort_order');
    }

    public function promotions()
    {
        return $this->belongsToMany(ShopPromotion::class, 'shop_promotion_shop_product', 'shop_product_id', 'shop_promotion_id')
            ->withPivot('required_quantity')
            ->withTimestamps();
    }

    public function artists()
    {
        return $this->belongsToMany(Artista::class, 'artist_shop_product', 'shop_product_id', 'artist_id');
    }

    public function eventos()
    {
        return $this->belongsToMany(Evento::class, 'evento_shop_product', 'shop_product_id', 'evento_id');
    }

    public function getPrimaryMediaAttribute(): ?ShopProductMedia
    {
        return $this->media->firstWhere('is_primary', true)
            ?: $this->media->firstWhere('media_type', 'image')
            ?: $this->media->first();
    }

    public function getPrimaryImageUrlAttribute(): ?string
    {
        return $this->image_url ?: $this->primary_media?->url;
    }

    public function getBasePriceArsAttribute(): float
    {
        return (float) $this->price_ars;
    }

    public function getBaseStockAttribute(): int
    {
        return (int) $this->stock;
    }

    public function getFeaturedAttribute(): bool
    {
        return (bool) $this->is_featured;
    }

    public function getRequiresShippingAttribute(): bool
    {
        return true;
    }

    public function getAllowPickupAttribute(): bool
    {
        return true;
    }
}
