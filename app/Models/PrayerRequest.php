<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PrayerRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'request_text',
        'is_public',
        'is_approved',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
