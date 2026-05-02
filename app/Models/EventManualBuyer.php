<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventManualBuyer extends Model
{
    use HasFactory;

    protected $fillable = ['event_id', 'name', 'email', 'phone', 'notes', 'created_by'];

    public function event()
    {
        return $this->belongsTo(Evento::class, 'event_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
