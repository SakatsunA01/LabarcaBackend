<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GaleriaEvento extends Model
{
    use HasFactory;
    protected $table = 'galerias_eventos';
    protected $fillable = ['id_evento', 'url_imagen', 'descripcion']; // Aseguramos que 'url_imagen' sea fillable

    public function evento()
    {
        return $this->belongsTo(Evento::class, 'id_evento');
    }
}
