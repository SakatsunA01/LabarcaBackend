<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Track extends Model
{
    use HasFactory;

    protected $fillable = [
        'lanzamiento_id',
        'titulo',
        'duracion',
    ];

    public function lanzamiento()
    {
        return $this->belongsTo(Lanzamiento::class);
    }
}