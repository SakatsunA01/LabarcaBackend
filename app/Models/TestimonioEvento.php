<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Support\Facades\Storage;

class TestimonioEvento extends Model
{
    use HasFactory;
    protected $table = 'testimonios_eventos';
    protected $fillable = [
        'id_evento',
        'usuario_id',
        'comentario',
        'nombre_usuario',
        'approved',
        'foto_path',
    ];
    protected $casts = [
        'approved' => 'boolean',
    ];
    protected $appends = ['foto_url'];

    public function evento()
    {
        return $this->belongsTo(Evento::class, 'id_evento');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function getFotoUrlAttribute()
    {
        if (!$this->foto_path) {
            return null;
        }
        return Storage::disk('public')->url($this->foto_path);
    }
}
