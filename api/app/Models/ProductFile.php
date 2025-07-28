<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Storage;

class ProductFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'name',
        'original_filename',
        'file_path',
        'file_type',
        'mime_type',
        'file_size',
        'file_hash',
        'is_primary',
        'is_active',
        'download_limit',
        'download_count',
        'metadata',
        'version',
        'description',
        'expires_at'
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_primary' => 'boolean',
        'is_active' => 'boolean',
        'expires_at' => 'datetime'
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function downloadAccesses(): HasMany
    {
        return $this->hasMany(DownloadAccess::class);
    }

    public function downloadAttempts(): HasMany
    {
        return $this->hasMany(DownloadAttempt::class);
    }

    public function productUpdates(): HasMany
    {
        return $this->hasMany(ProductUpdate::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    public function scopeNotExpired($query)
    {
        return $query->where(function($q) {
            $q->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
    }

    public function getFileSizeFormattedAttribute(): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = $this->file_size;

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    public function getFullPathAttribute(): string
    {
        return storage_path('app/private/digital-products/' . $this->file_path);
    }

    public function exists(): bool
    {
        return Storage::disk('private')->exists('digital-products/' . $this->file_path);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function canBeDownloaded(): bool
    {
        return $this->is_active &&
            !$this->isExpired() &&
            ($this->download_limit === null || $this->download_count < $this->download_limit);
    }

    public function incrementDownloadCount(): void
    {
        $this->increment('download_count');
    }

    public function getFileTypeIcon(): string
    {
        return match($this->file_type) {
            'pdf' => '📄',
            'zip', 'rar', '7z' => '📦',
            'exe', 'msi' => '⚙️',
            'mp3', 'wav', 'flac' => '🎵',
            'mp4', 'avi', 'mkv' => '🎬',
            'jpg', 'jpeg', 'png', 'gif' => '🖼️',
            'txt', 'doc', 'docx' => '📝',
            default => '📁'
        };
    }
}

