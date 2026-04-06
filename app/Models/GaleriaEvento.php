<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GaleriaEvento extends Model
{
    use HasFactory;
    protected $table = 'galerias_eventos';
    protected $fillable = ['id_evento', 'url_imagen', 'media_type', 'descripcion'];

    protected $attributes = [
        'media_type' => 'image',
    ];

    public function evento()
    {
        return $this->belongsTo(Evento::class, 'id_evento');
    }
}
