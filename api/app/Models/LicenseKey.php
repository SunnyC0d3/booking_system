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

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeNotExpired($query)
    {
        return $query->where(function($q) {
            $q->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
    }

    public function scopeValid($query)
    {
        return $query->active()->notExpired();
    }

    public static function generateKey(string $productPrefix = null): string
    {
        $prefix = $productPrefix ?? 'PROD';
        $segments = [
            $prefix,
            strtoupper(Str::random(4)),
            strtoupper(Str::random(4)),
            strtoupper(Str::random(4)),
            strtoupper(Str::random(4))
        ];

        return implode('-', $segments);
    }

    public function isValid(): bool
    {
        return $this->status === 'active' &&
            ($this->expires_at === null || $this->expires_at->isFuture()) &&
            $this->canActivate();
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function canActivate(): bool
    {
        return $this->activations_used < $this->activation_limit;
    }

    public function activate(array $deviceInfo = []): bool
    {
        if (!$this->canActivate()) {
            return false;
        }

        $devices = $this->activated_devices ?? [];
        $devices[] = array_merge($deviceInfo, [
            'activated_at' => now()->toISOString(),
            'ip_address' => request()->ip()
        ]);

        $this->update([
            'activations_used' => $this->activations_used + 1,
            'activated_devices' => $devices,
            'last_activated_at' => now()
        ]);

        if ($this->first_activated_at === null) {
            $this->update(['first_activated_at' => now()]);
        }

        return true;
    }

    public function deactivate(string $deviceId = null): bool
    {
        if ($deviceId && $this->activated_devices) {
            $devices = collect($this->activated_devices)
                ->reject(fn($device) => $device['device_id'] === $deviceId)
                ->values()
                ->toArray();

            $this->update([
                'activated_devices' => $devices,
                'activations_used' => max(0, $this->activations_used - 1)
            ]);
        }

        return true;
    }

    public function revoke(string $reason = null): void
    {
        $this->update([
            'status' => 'revoked',
            'notes' => $this->notes . "\nRevoked: " . ($reason ?? 'No reason provided') . ' at ' . now()
        ]);
    }

    public function getRemainingActivationsAttribute(): int
    {
        return max(0, $this->activation_limit - $this->activations_used);
    }

    public function getTypeLabelAttribute(): string
    {
        return match($this->type) {
            'single_use' => 'Single Use',
            'multi_use' => 'Multi Use',
            'subscription' => 'Subscription',
            'trial' => 'Trial',
            default => 'Unknown'
        };
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
