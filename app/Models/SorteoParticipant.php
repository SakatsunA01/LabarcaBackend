<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SorteoParticipant extends Model
{
    use HasFactory;

    protected $fillable = [
        'sorteo_id',
        'user_id',
        'is_manual',
        'added_by',
    ];

    protected $casts = [
        'is_manual' => 'boolean',
    ];

    public function sorteo()
    {
        return $this->belongsTo(Sorteo::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
