<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DownloadAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'download_access_id',
        'user_id',
        'product_file_id',
        'ip_address',
        'user_agent',
        'status',
        'bytes_downloaded',
        'total_file_size',
        'download_speed_kbps',
        'duration_seconds',
        'failure_reason',
        'headers',
        'started_at',
        'completed_at'
    ];

    protected $casts = [
        'headers' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime'
    ];

    public function downloadAccess(): BelongsTo
    {
        return $this->belongsTo(DownloadAccess::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function productFile(): BelongsTo
    {
        return $this->belongsTo(ProductFile::class);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeByIp($query, string $ip)
    {
        return $query->where('ip_address', $ip);
    }

    public function markAsCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
            'duration_seconds' => $this->started_at->diffInSeconds(now())
        ]);
    }

    public function markAsFailed(string $reason): void
    {
        $this->update([
            'status' => 'failed',
            'failure_reason' => $reason,
            'completed_at' => now(),
            'duration_seconds' => $this->started_at->diffInSeconds(now())
        ]);
    }

    public function updateProgress(int $bytesDownloaded): void
    {
        $this->update(['bytes_downloaded' => $bytesDownloaded]);
    }

    public function getProgressPercentageAttribute(): float
    {
        if ($this->total_file_size === 0) {
            return 0;
        }

        return min(100, ($this->bytes_downloaded / $this->total_file_size) * 100);
    }

    public function getDownloadSpeedFormatted(): string
    {
        if (!$this->download_speed_kbps) {
            return 'Unknown';
        }

        if ($this->download_speed_kbps >= 1024) {
            return round($this->download_speed_kbps / 1024, 2) . ' MB/s';
        }

        return round($this->download_speed_kbps, 2) . ' KB/s';
    }

    public function getDurationFormatted(): string
    {
        if (!$this->duration_seconds) {
            return 'Unknown';
        }

        $minutes = floor($this->duration_seconds / 60);
        $seconds = $this->duration_seconds % 60;

        if ($minutes > 0) {
            return "{$minutes}m {$seconds}s";
        }

        return "{$seconds}s";
    }
}
