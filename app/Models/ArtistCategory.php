<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ArtistCategory extends Model
{
    use HasFactory;

    protected $table = 'artist_categories';

    protected $fillable = [
        'nombre',
        'slug',
    ];

    public function artists()
    {
        return $this->belongsToMany(Artista::class, 'artist_category_pivot', 'category_id', 'artist_id');
    }
}
