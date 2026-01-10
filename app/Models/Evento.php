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
