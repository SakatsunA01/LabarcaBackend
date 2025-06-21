<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TestimonioEvento extends Model
{
    use HasFactory;
    protected $table = 'testimonios_eventos';
    protected $fillable = ['id_evento', 'usuario_id', 'comentario', 'nombre_usuario'];

    public function evento()
    {
        return $this->belongsTo(Evento::class, 'id_evento');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
