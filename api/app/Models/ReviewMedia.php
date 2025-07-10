<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Storage;

class ReviewMedia extends Model
{
    use HasFactory;

    protected $fillable = [
        'review_id',
        'media_path',
        'media_type',
        'original_name',
        'mime_type',
        'file_size',
        'metadata',
        'sort_order',
    ];

    protected $casts = [
        'metadata' => 'array',
        'file_size' => 'integer',
        'sort_order' => 'integer',
    ];

    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }

    public function getUrlAttribute(): string
    {
        return Storage::url($this->media_path);
    }

    public function getThumbnailUrlAttribute(): ?string
    {
        if ($this->media_type !== 'image') {
            return null;
        }

        $thumbnailPath = str_replace(
            basename($this->media_path),
            'thumb_' . basename($this->media_path),
            $this->media_path
        );

        return Storage::exists($thumbnailPath) ? Storage::url($thumbnailPath) : $this->url;
    }

    public function getFileSizeFormattedAttribute(): string
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    public function isImage(): bool
    {
        return $this->media_type === 'image';
    }

    public function isVideo(): bool
    {
        return $this->media_type === 'video';
    }
}
