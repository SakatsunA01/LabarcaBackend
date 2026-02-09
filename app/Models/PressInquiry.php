<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PressInquiry extends Model
{
    use HasFactory;

    protected $fillable = [
        'first_name',
        'last_name',
        'birth_date',
        'email',
        'phone',
        'media_station',
        'media_position',
        'belongs_to_church',
        'church_name',
        'pastor_name',
        'program_slots',
    ];

    protected $casts = [
        'program_slots' => 'array',
        'belongs_to_church' => 'boolean',
        'birth_date' => 'date',
    ];
}
