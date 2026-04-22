<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MediaCategory extends Model
{
    protected $fillable = ['slug', 'name', 'icon'];

    public function mediaFiles(): HasMany
    {
        return $this->hasMany(MediaFile::class);
    }
}
