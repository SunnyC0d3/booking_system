<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class DownloadAccess extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'product_id',
        'order_id',
        'product_file_id',
        'access_token',
        'status',
        'download_limit',
        'downloads_used',
        'expires_at',
        'first_downloaded_at',
        'last_downloaded_at',
        'allowed_ips',
        'metadata'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'first_downloaded_at' => 'datetime',
        'last_downloaded_at' => 'datetime',
        'allowed_ips' => 'array',
        'metadata' => 'array'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function productFile(): BelongsTo
    {
        return $this->belongsTo(ProductFile::class);
    }

    public function downloadAttempts(): HasMany
    {
        return $this->hasMany(DownloadAttempt::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeNotExpired($query)
    {
        return $query->where('expires_at', '>', now());
    }

    public function scopeWithDownloadsRemaining($query)
    {
        return $query->whereRaw('downloads_used < download_limit');
    }

    public function scopeValid($query)
    {
        return $query->active()->notExpired()->withDownloadsRemaining();
    }

    public static function generateToken(): string
    {
        return Str::random(64);
    }

    public function isValid(): bool
    {
        return $this->status === 'active' &&
            $this->expires_at->isFuture() &&
            $this->downloads_used < $this->download_limit;
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function hasDownloadsRemaining(): bool
    {
        return $this->downloads_used < $this->download_limit;
    }

    public function canDownloadFromIp(string $ip): bool
    {
        if (empty($this->allowed_ips)) {
            return true;
        }

        return in_array($ip, $this->allowed_ips);
    }

    public function recordDownload(): void
    {
        $this->increment('downloads_used');

        if ($this->first_downloaded_at === null) {
            $this->update(['first_downloaded_at' => now()]);
        }

        $this->update(['last_downloaded_at' => now()]);
    }

    public function getRemainingDownloadsAttribute(): int
    {
        return max(0, $this->download_limit - $this->downloads_used);
    }

    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'active' => 'Active',
            'expired' => 'Expired',
            'revoked' => 'Revoked',
            'suspended' => 'Suspended',
            default => 'Unknown'
        };
    }
}
