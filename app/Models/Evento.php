<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Evento extends Model
{
    use HasFactory;
    protected $table = 'eventos';
    protected $fillable = ['nombre', 'fecha', 'link_compra', 'descripcion', 'lugar', 'imagenUrl'];

    public function testimonios()
    {
        return $this->hasMany(TestimonioEvento::class, 'id_evento');
    }

    public function galeria()
    {
        return $this->hasMany(GaleriaEvento::class, 'id_evento');
    }
}
