<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class LicenseKey extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'user_id',
        'order_id',
        'license_key',
        'type',
        'status',
        'activation_limit',
        'activations_used',
        'expires_at',
        'first_activated_at',
        'last_activated_at',
        'activated_devices',
        'metadata',
        'notes'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'first_activated_at' => 'datetime',
        'last_activated_at' => 'datetime',
        'activated_devices' => 'array',
        'metadata' => 'array'
    ];

    /**
     * Relationships
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
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

    public function scopeByProduct($query, $productId)
    {
        return $query->where('product_id', $productId);
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Accessors & Mutators
     */
    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            'single_use' => 'Single Use',
            'multi_use' => 'Multi-Device',
            'subscription' => 'Subscription',
            'trial' => 'Trial',
            default => ucfirst($this->type),
        };
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

    public function getRemainingActivations(): int
    {
        return max(0, $this->activation_limit - $this->activations_used);
    }

    public function getRemainingActivationsAttribute(): int
    {
        return $this->getRemainingActivations();
    }

    public function getActivatedDevices(): array
    {
        return $this->activated_devices ?? [];
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function getCanActivateAttribute(): bool
    {
        return $this->isValid() && $this->getRemainingActivations() > 0;
    }

    /**
     * Business Logic Methods
     */
    public function isValid(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    public function canActivate(): bool
    {
        return $this->isValid() && $this->getRemainingActivations() > 0;
    }

    public function isActivatedOnDevice(string $deviceId): bool
    {
        $devices = $this->getActivatedDevices();

        foreach ($devices as $device) {
            if ($device['device_id'] === $deviceId) {
                return true;
            }
        }

        return false;
    }

    public function getDeviceActivation(string $deviceId): ?array
    {
        $devices = $this->getActivatedDevices();

        foreach ($devices as $device) {
            if ($device['device_id'] === $deviceId) {
                return $device;
            }
        }

        return null;
    }

    public function getActiveDeviceCount(): int
    {
        return count($this->getActivatedDevices());
    }

    public function getDaysUntilExpiry(): ?int
    {
        if (!$this->expires_at) {
            return null;
        }

        return max(0, now()->diffInDays($this->expires_at, false));
    }

    public function getExpiryStatus(): string
    {
        if (!$this->expires_at) {
            return 'never';
        }

        $days = $this->getDaysUntilExpiry();

        if ($days < 0) {
            return 'expired';
        } elseif ($days <= 7) {
            return 'expiring_soon';
        } elseif ($days <= 30) {
            return 'expiring_this_month';
        }

        return 'active';
    }

    /**
     * Static Methods
     */
    public static function generateKey(string $prefix = 'LIC'): string
    {
        do {
            $segments = [
                strtoupper($prefix),
                strtoupper(Str::random(4)),
                strtoupper(Str::random(4)),
                strtoupper(Str::random(4)),
                strtoupper(Str::random(4)),
            ];

            $key = implode('-', $segments);
        } while (self::where('license_key', $key)->exists());

        return $key;
    }

    public static function generateToken(): string
    {
        do {
            $token = strtolower(Str::random(32));
        } while (self::where('license_key', $token)->exists());

        return $token;
    }

    /**
     * Boot method
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($license) {
            if (!$license->license_key) {
                $license->license_key = self::generateKey();
            }
        });

        static::updating(function ($license) {
            // Auto-expire if past expiry date
            if ($license->expires_at && $license->expires_at->isPast() && $license->status === 'active') {
                $license->status = 'expired';
            }
        });
    }
}
