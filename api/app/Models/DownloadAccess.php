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

    /**
     * Relationships
     */
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

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeExpired($query)
    {
        return $query->where('status', 'expired')
            ->orWhere(function ($q) {
                $q->where('expires_at', '<', now())
                    ->where('status', 'active');
            });
    }

    public function scopeRevoked($query)
    {
        return $query->where('status', 'revoked');
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByProduct($query, $productId)
    {
        return $query->where('product_id', $productId);
    }

    public function scopeByOrder($query, $orderId)
    {
        return $query->where('order_id', $orderId);
    }

    public function scopeUnused($query)
    {
        return $query->where('downloads_used', 0);
    }

    public function scopeExhausted($query)
    {
        return $query->whereColumn('downloads_used', '>=', 'download_limit');
    }

    /**
     * Accessors
     */
    public function getRemainingDownloads(): int
    {
        if ($this->download_limit === null) {
            return PHP_INT_MAX; // Unlimited
        }

        return max(0, $this->download_limit - $this->downloads_used);
    }

    public function getRemainingDownloadsAttribute(): int
    {
        return $this->getRemainingDownloads();
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function getIsExhaustedAttribute(): bool
    {
        if ($this->download_limit === null) {
            return false; // Unlimited downloads
        }

        return $this->downloads_used >= $this->download_limit;
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'active' => 'Active',
            'expired' => 'Expired',
            'revoked' => 'Revoked',
            'suspended' => 'Suspended',
            default => ucfirst($this->status),
        };
    }

    public function getUsagePercentageAttribute(): float
    {
        if ($this->download_limit === null || $this->download_limit === 0) {
            return 0;
        }

        return min(100, ($this->downloads_used / $this->download_limit) * 100);
    }

    public function getDaysUntilExpiryAttribute(): ?int
    {
        if (!$this->expires_at) {
            return null;
        }

        return max(0, now()->diffInDays($this->expires_at, false));
    }

    /**
     * Business Logic Methods
     */
    public function isValid(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        if ($this->is_expired) {
            return false;
        }

        if ($this->is_exhausted) {
            return false;
        }

        return true;
    }

    public function canDownload(): bool
    {
        return $this->isValid();
    }

    public function canDownloadFromIp(?string $ipAddress): bool
    {
        if (!$ipAddress) {
            return false;
        }

        $allowedIps = $this->allowed_ips ?? [];

        // If no IP restrictions, allow any IP
        if (empty($allowedIps)) {
            return true;
        }

        // Check if IP is in allowed list
        foreach ($allowedIps as $allowedIp) {
            if ($this->matchesIpPattern($ipAddress, $allowedIp)) {
                return true;
            }
        }

        return false;
    }

    public function recordDownload(): void
    {
        $this->increment('downloads_used');

        $now = now();
        $updates = [
            'last_downloaded_at' => $now,
        ];

        if (!$this->first_downloaded_at) {
            $updates['first_downloaded_at'] = $now;
        }

        // Auto-expire if limit reached
        if ($this->is_exhausted) {
            $updates['status'] = 'expired';
        }

        $this->update($updates);
    }

    public function addAllowedIp(string $ipAddress): void
    {
        $allowedIps = $this->allowed_ips ?? [];

        if (!in_array($ipAddress, $allowedIps)) {
            $allowedIps[] = $ipAddress;
            $this->update(['allowed_ips' => $allowedIps]);
        }
    }

    public function removeAllowedIp(string $ipAddress): void
    {
        $allowedIps = $this->allowed_ips ?? [];
        $allowedIps = array_filter($allowedIps, fn($ip) => $ip !== $ipAddress);

        $this->update(['allowed_ips' => array_values($allowedIps)]);
    }

    public function extendExpiry(int $days): void
    {
        $newExpiry = $this->expires_at
            ? $this->expires_at->addDays($days)
            : now()->addDays($days);

        $this->update(['expires_at' => $newExpiry]);
    }

    public function increaseDownloadLimit(int $additionalDownloads): void
    {
        $newLimit = ($this->download_limit ?? 0) + $additionalDownloads;
        $this->update(['download_limit' => $newLimit]);
    }

    public function revoke(string $reason = 'Access revoked'): void
    {
        $this->update([
            'status' => 'revoked',
            'metadata' => array_merge($this->metadata ?? [], [
                'revoked_at' => now()->toISOString(),
                'revoked_reason' => $reason,
                'revoked_by_ip' => request()?->ip(),
            ]),
        ]);
    }

    public function getSuccessfulDownloads(): int
    {
        return $this->downloadAttempts()
            ->where('status', 'completed')
            ->count();
    }

    public function getFailedDownloads(): int
    {
        return $this->downloadAttempts()
            ->where('status', 'failed')
            ->count();
    }

    public function getLastSuccessfulDownload(): ?DownloadAttempt
    {
        return $this->downloadAttempts()
            ->where('status', 'completed')
            ->latest('completed_at')
            ->first();
    }

    /**
     * Helper Methods
     */
    protected function matchesIpPattern(string $ip, string $pattern): bool
    {
        // Exact match
        if ($ip === $pattern) {
            return true;
        }

        // CIDR notation
        if (str_contains($pattern, '/')) {
            return $this->ipInCidr($ip, $pattern);
        }

        // Wildcard pattern (e.g., 192.168.1.*)
        if (str_contains($pattern, '*')) {
            $regex = str_replace(['*', '.'], ['.*', '\.'], $pattern);
            return preg_match('/^' . $regex . '$/', $ip);
        }

        return false;
    }

    protected function ipInCidr(string $ip, string $cidr): bool
    {
        list($subnet, $mask) = explode('/', $cidr);

        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        $maskLong = -1 << (32 - (int)$mask);

        return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
    }

    /**
     * Static Methods
     */
    public static function generateToken(): string
    {
        do {
            $token = strtolower(Str::random(40));
        } while (self::where('access_token', $token)->exists());

        return $token;
    }

    /**
     * Boot method
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($access) {
            if (!$access->access_token) {
                $access->access_token = self::generateToken();
            }
        });

        static::updating(function ($access) {
            // Auto-expire if past expiry date
            if ($access->expires_at && $access->expires_at->isPast() && $access->status === 'active') {
                $access->status = 'expired';
            }
        });
    }
}
