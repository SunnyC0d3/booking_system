<?php

namespace App\Models;

use App\Constants\SupplierIntegrationTypes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SupplierIntegration extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_id',
        'integration_type',
        'name',
        'is_active',
        'configuration',
        'authentication',
        'status',
        'last_successful_sync',
        'last_failed_sync',
        'consecutive_failures',
        'last_error',
        'sync_statistics',
        'sync_frequency_minutes',
        'auto_retry_enabled',
        'max_retry_attempts',
        'webhook_events',
    ];

    protected $casts = [
        'configuration' => 'array',
        'authentication' => 'array',
        'sync_statistics' => 'array',
        'webhook_events' => 'array',
        'is_active' => 'boolean',
        'auto_retry_enabled' => 'boolean',
        'last_successful_sync' => 'datetime',
        'last_failed_sync' => 'datetime',
    ];

    protected $hidden = [
        'authentication',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('integration_type', $type);
    }

    public function scopeHealthy($query)
    {
        return $query->where('status', 'active')
            ->where('consecutive_failures', '<', 3);
    }

    public function scopeUnhealthy($query)
    {
        return $query->where('status', '!=', 'active')
            ->orWhere('consecutive_failures', '>=', 3);
    }

    public function isApiIntegration(): bool
    {
        return $this->integration_type === SupplierIntegrationTypes::API;
    }

    public function isWebhookIntegration(): bool
    {
        return $this->integration_type === SupplierIntegrationTypes::WEBHOOK;
    }

    public function isEmailIntegration(): bool
    {
        return $this->integration_type === SupplierIntegrationTypes::EMAIL;
    }

    public function isFtpIntegration(): bool
    {
        return $this->integration_type === SupplierIntegrationTypes::FTP;
    }

    public function isCsvIntegration(): bool
    {
        return $this->integration_type === SupplierIntegrationTypes::CSV_UPLOAD;
    }

    public function isManualIntegration(): bool
    {
        return $this->integration_type === SupplierIntegrationTypes::MANUAL;
    }

    public function isAutomated(): bool
    {
        return in_array($this->integration_type, SupplierIntegrationTypes::getAutomatedTypes());
    }

    public function isHealthy(): bool
    {
        return $this->status === 'active' && $this->consecutive_failures < 3;
    }

    public function hasRecentSync(int $hours = 24): bool
    {
        if (!$this->last_successful_sync) {
            return false;
        }

        return $this->last_successful_sync->isAfter(now()->subHours($hours));
    }

    public function needsSync(): bool
    {
        if (!$this->is_active || !$this->isAutomated()) {
            return false;
        }

        if (!$this->last_successful_sync) {
            return true;
        }

        return $this->last_successful_sync->addMinutes($this->sync_frequency_minutes)->isPast();
    }

    public function canRetry(): bool
    {
        return $this->auto_retry_enabled &&
            $this->consecutive_failures < $this->max_retry_attempts;
    }

    public function getApiEndpoint(): ?string
    {
        return $this->configuration['api_endpoint'] ?? null;
    }

    public function getApiKey(): ?string
    {
        return $this->authentication['api_key'] ?? null;
    }

    public function getApiSecret(): ?string
    {
        return $this->authentication['api_secret'] ?? null;
    }

    public function getWebhookUrl(): ?string
    {
        return $this->configuration['webhook_url'] ?? null;
    }

    public function getWebhookSecret(): ?string
    {
        return $this->authentication['webhook_secret'] ?? null;
    }

    public function getFtpHost(): ?string
    {
        return $this->configuration['ftp_host'] ?? null;
    }

    public function getFtpUsername(): ?string
    {
        return $this->authentication['ftp_username'] ?? null;
    }

    public function getFtpPassword(): ?string
    {
        return $this->authentication['ftp_password'] ?? null;
    }

    public function getEmailAddress(): ?string
    {
        return $this->configuration['email_address'] ?? null;
    }

    public function getSyncFrequencyFormatted(): string
    {
        $minutes = $this->sync_frequency_minutes;

        if ($minutes < 60) {
            return $minutes . ' minutes';
        }

        $hours = $minutes / 60;
        if ($hours < 24) {
            return $hours . ' hours';
        }

        $days = $hours / 24;
        return $days . ' days';
    }

    public function getLastSyncStatus(): string
    {
        if (!$this->last_successful_sync && !$this->last_failed_sync) {
            return 'Never synced';
        }

        if (!$this->last_failed_sync) {
            return 'Success';
        }

        if (!$this->last_successful_sync) {
            return 'Failed';
        }

        return $this->last_successful_sync->isAfter($this->last_failed_sync) ? 'Success' : 'Failed';
    }

    public function getLastSyncTime(): ?\Carbon\Carbon
    {
        if (!$this->last_successful_sync && !$this->last_failed_sync) {
            return null;
        }

        if (!$this->last_failed_sync) {
            return $this->last_successful_sync;
        }

        if (!$this->last_successful_sync) {
            return $this->last_failed_sync;
        }

        return $this->last_successful_sync->isAfter($this->last_failed_sync)
            ? $this->last_successful_sync
            : $this->last_failed_sync;
    }

    public function getLastSyncAgo(): string
    {
        $lastSync = $this->getLastSyncTime();

        if (!$lastSync) {
            return 'Never';
        }

        return $lastSync->diffForHumans();
    }

    public function getHealthScore(): int
    {
        $score = 100;

        if ($this->consecutive_failures > 0) {
            $score -= ($this->consecutive_failures * 20);
        }

        if (!$this->hasRecentSync(24)) {
            $score -= 30;
        }

        if (!$this->is_active) {
            $score = 0;
        }

        return max(0, $score);
    }

    public function getHealthStatus(): string
    {
        $score = $this->getHealthScore();

        if ($score >= 80) {
            return 'Excellent';
        } elseif ($score >= 60) {
            return 'Good';
        } elseif ($score >= 40) {
            return 'Fair';
        } elseif ($score >= 20) {
            return 'Poor';
        } else {
            return 'Critical';
        }
    }

    public function recordSuccessfulSync(array $statistics = []): void
    {
        $this->update([
            'last_successful_sync' => now(),
            'consecutive_failures' => 0,
            'last_error' => null,
            'status' => 'active',
            'sync_statistics' => array_merge($this->sync_statistics ?? [], $statistics),
        ]);
    }

    public function recordFailedSync(string $error): void
    {
        $this->update([
            'last_failed_sync' => now(),
            'consecutive_failures' => $this->consecutive_failures + 1,
            'last_error' => $error,
            'status' => $this->consecutive_failures >= $this->max_retry_attempts ? 'failed' : 'active',
        ]);
    }

    public function resetFailures(): void
    {
        $this->update([
            'consecutive_failures' => 0,
            'last_error' => null,
            'status' => 'active',
        ]);
    }

    public function disable(): void
    {
        $this->update([
            'is_active' => false,
            'status' => 'disabled',
        ]);
    }

    public function enable(): void
    {
        $this->update([
            'is_active' => true,
            'status' => 'active',
            'consecutive_failures' => 0,
            'last_error' => null,
        ]);
    }

    public function getIntegrationTypeLabel(): string
    {
        return SupplierIntegrationTypes::labels()[$this->integration_type] ?? 'Unknown';
    }

    public function testConnection(): array
    {
        return [
            'success' => false,
            'message' => 'Connection test not implemented for this integration type',
            'data' => null,
        ];
    }

    public function getSyncStatistics(): array
    {
        return $this->sync_statistics ?? [
            'total_syncs' => 0,
            'successful_syncs' => 0,
            'failed_syncs' => 0,
            'products_synced' => 0,
            'last_sync_duration' => 0,
        ];
    }

    public function getSuccessRate(): float
    {
        $stats = $this->getSyncStatistics();
        $total = $stats['total_syncs'] ?? 0;

        if ($total === 0) {
            return 0;
        }

        $successful = $stats['successful_syncs'] ?? 0;
        return round(($successful / $total) * 100, 2);
    }

    public function updateConfiguration(array $config): void
    {
        $this->update(['configuration' => array_merge($this->configuration ?? [], $config)]);
    }

    public function updateAuthentication(array $auth): void
    {
        $this->update(['authentication' => array_merge($this->authentication ?? [], $auth)]);
    }
}
