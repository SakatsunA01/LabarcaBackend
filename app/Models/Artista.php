<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\ArtistCategory;

class Artista extends Model
{
    use HasFactory;

    protected $table = 'artistas'; // Especifica el nombre de la tabla si no sigue la convención de pluralización de Laravel

    // Campos que se pueden asignar masivamente
    protected $fillable = [
        'name',
        'vinculacion',
        'imageUrl',
        'heroImageUrl',
        'secondaryImageUrl',
        'color',
        'description',
        'spotifyEmbedUrl',
        'youtubeVideoId',
        'social_instagram',
        'social_facebook',
        'social_youtubeChannel',
        'social_tiktok',
        'social_spotifyProfile',
    ];

    protected $casts = [
        'vinculacion' => 'string',
    ];

    public function categories()
    {
        return $this->belongsToMany(ArtistCategory::class, 'artist_category_pivot', 'artist_id', 'category_id');
    }

    // Laravel maneja created_at y updated_at por defecto si los campos existen
    // y son de tipo TIMESTAMP. Si no quieres que Laravel los maneje:
    // public $timestamps = false;
}
