<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'price_ars',
        'stock',
        'image_url',
        'is_active',
    ];

    protected $casts = [
        'price_ars' => 'decimal:2',
        'is_active' => 'boolean',
    ];
}
