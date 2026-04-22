<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class MediaFile extends Model
{
    protected $fillable = [
        'user_id', 'media_category_id', 'title',
        'file_path', 'file_name', 'mime_type', 'file_size',
        'is_downloadable', 'disk',
    ];

    protected $casts = [
        'is_downloadable' => 'boolean',
        'file_size' => 'integer',
    ];

    protected $appends = ['url', 'file_size_formatted', 'mime_group'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MediaCategory::class, 'media_category_id');
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk($this->disk)->url($this->file_path);
    }

    public function getFileSizeFormattedAttribute(): string
    {
        $bytes = $this->file_size;
        if ($bytes < 1024) return "{$bytes} B";
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
        if ($bytes < 1073741824) return round($bytes / 1048576, 1) . ' MB';
        return round($bytes / 1073741824, 2) . ' GB';
    }

    public function getMimeGroupAttribute(): string
    {
        if (str_starts_with($this->mime_type ?? '', 'image/')) return 'image';
        if (str_starts_with($this->mime_type ?? '', 'audio/')) return 'audio';
        if (str_starts_with($this->mime_type ?? '', 'video/')) return 'video';
        return 'file';
    }
}
