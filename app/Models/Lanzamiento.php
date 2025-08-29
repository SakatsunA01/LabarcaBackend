<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lanzamiento extends Model
{
    use HasFactory;

    protected $fillable = [
        'titulo',
        'artista_id',
        'fecha_lanzamiento',
        'cover_image_url',
        'youtube_link',
        'spotify_link',
    ];

    protected $casts = [
        'fecha_lanzamiento' => 'date',
    ];

    public function artista()
    {
        return $this->belongsTo(Artista::class);
    }

    public function tracks()
    {
        return $this->hasMany(Track::class);
    }
}