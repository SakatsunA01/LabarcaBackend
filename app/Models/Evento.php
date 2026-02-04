<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Evento extends Model
{
    use HasFactory;
    protected $table = 'eventos';
    protected $fillable = [
        'nombre',
        'fecha',
        'link_compra',
        'general_product_id',
        'vip_product_id',
        'descripcion',
        'lugar',
        'imagenUrl',
        'countdown_enabled',
        'countdown_title',
        'countdown_subtitle',
        'pilar_experiencia',
        'pilar_autoridad',
        'pilar_mensaje',
        'pilar_experiencia_icon',
        'pilar_autoridad_icon',
        'pilar_mensaje_icon',
        'cronograma',
        'lineup_artist_ids',
    ];

    protected $casts = [
        'countdown_enabled' => 'boolean',
        'cronograma' => 'array',
        'lineup_artist_ids' => 'array',
    ];

    public function testimonios()
    {
        return $this->hasMany(TestimonioEvento::class, 'id_evento')->where('approved', true);
    }

    public function galeria()
    {
        return $this->hasMany(GaleriaEvento::class, 'id_evento');
    }

    public function generalProduct()
    {
        return $this->belongsTo(Product::class, 'general_product_id');
    }

    public function vipProduct()
    {
        return $this->belongsTo(Product::class, 'vip_product_id');
    }
}
