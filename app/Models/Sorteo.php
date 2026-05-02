<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sorteo extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'premio',
        'premio_imagen_url',
        'descripcion',
        'fecha_limite',
        'estado',
        'requisitos',
        'ganador_user_id',
        'ganador_snapshot',
        'closed_at',
        'created_by',
        'bendiciones',
        'winners',
    ];

    protected $casts = [
        'fecha_limite' => 'datetime',
        'closed_at' => 'datetime',
        'requisitos' => 'array',
        'ganador_snapshot' => 'array',
        'bendiciones' => 'array',
        'winners' => 'array',
    ];

    public function ganador()
    {
        return $this->belongsTo(User::class, 'ganador_user_id');
    }

    public function participants()
    {
        return $this->hasMany(SorteoParticipant::class);
    }
}
