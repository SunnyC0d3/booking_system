<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;
use Illuminate\Support\Facades\Crypt;

class CalendarIntegration extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'service_id',
        'provider',
        'calendar_id',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'is_active',
        'sync_bookings',
        'sync_availability',
        'auto_block_external_events',
        'sync_settings',
        'last_sync_at',
    ];

    protected $casts = [
        'token_expires_at' => 'datetime',
        'is_active' => 'boolean',
        'sync_bookings' => 'boolean',
        'sync_availability' => 'boolean',
        'auto_block_external_events' => 'boolean',
        'sync_settings' => 'array',
        'last_sync_at' => 'datetime',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('is_active', false);
    }

    public function scopeForProvider(Builder $query, string $provider): Builder
    {
        return $query->where('provider', $provider);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForService(Builder $query, int $serviceId): Builder
    {
        return $query->where('service_id', $serviceId);
    }

    public function scopeWithExpiredTokens(Builder $query): Builder
    {
        return $query->where('token_expires_at', '<', now());
    }

    public function scopeNeedingSync(Builder $query, int $minutesAgo = 60): Builder
    {
        return $query->active()
            ->where(function ($q) use ($minutesAgo) {
                $q->whereNull('last_sync_at')
                    ->orWhere('last_sync_at', '<', now()->subMinutes($minutesAgo));
            });
    }

    // Accessors & Mutators
    public function setAccessTokenAttribute($value): void
    {
        $this->attributes['access_token'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getAccessTokenAttribute($value): ?string
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function setRefreshTokenAttribute($value): void
    {
        $this->attributes['refresh_token'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getRefreshTokenAttribute($value): ?string
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function getProviderDisplayAttribute(): string
    {
        return match ($this->provider) {
            'google' => 'Google Calendar',
            'outlook' => 'Microsoft Outlook',
            'apple' => 'Apple Calendar',
            'ical' => 'iCal',
            default => ucfirst($this->provider)
        };
    }

    public function getStatusDisplayAttribute(): string
    {
        if (!$this->is_active) {
            return 'Inactive';
        }

        if ($this->isTokenExpired()) {
            return 'Token Expired';
        }

        if ($this->last_sync_at && $this->last_sync_at->lt(now()->subHours(2))) {
            return 'Sync Overdue';
        }

        return 'Active';
    }

    public function getSyncSettingsDisplayAttribute(): array
    {
        $defaults = [
            'sync_frequency' => 30, // minutes
            'event_title_template' => 'Booking: {service_name}',
            'include_client_name' => true,
            'include_location' => true,
            'include_notes' => false,
            'calendar_color' => '#1976d2',
            'reminder_minutes' => [15, 60],
        ];

        return array_merge($defaults, $this->sync_settings ?? []);
    }

    public function getFormattedLastSyncAttribute(): string
    {
        if (!$this->last_sync_at) {
            return 'Never';
        }

        return $this->last_sync_at->diffForHumans();
    }

    // Helper methods
    public function isTokenExpired(): bool
    {
        return $this->token_expires_at && $this->token_expires_at->isPast();
    }

    public function needsTokenRefresh(): bool
    {
        return $this->token_expires_at && $this->token_expires_at->lt(now()->addMinutes(10));
    }

    public function isHealthy(): bool
    {
        return $this->is_active && !$this->isTokenExpired();
    }

    public function canSync(): bool
    {
        return $this->isHealthy() && $this->access_token;
    }

    public function shouldAutoSync(): bool
    {
        if (!$this->canSync()) {
            return false;
        }

        $syncFrequency = $this->sync_settings_display['sync_frequency'] ?? 30;

        return !$this->last_sync_at ||
            $this->last_sync_at->lt(now()->subMinutes($syncFrequency));
    }

    public function activate(): void
    {
        $this->update(['is_active' => true]);
    }

    public function deactivate(): void
    {
        $this->update(['is_active' => false]);
    }

    public function updateTokens(string $accessToken, ?string $refreshToken = null, ?Carbon $expiresAt = null): void
    {
        $this->update([
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken ?: $this->refresh_token,
            'token_expires_at' => $expiresAt,
            'is_active' => true,
        ]);
    }

    public function markSyncCompleted(): void
    {
        $this->update(['last_sync_at' => now()]);
    }

    public function updateSyncSettings(array $settings): void
    {
        $current = $this->sync_settings ?? [];
        $this->update(['sync_settings' => array_merge($current, $settings)]);
    }

    public function getCalendarEventTitle(Booking $booking): string
    {
        $template = $this->sync_settings_display['event_title_template'];

        $replacements = [
            '{service_name}' => $booking->service->name,
            '{client_name}' => $booking->client_name,
            '{booking_ref}' => $booking->booking_reference,
            '{duration}' => $booking->duration_minutes . ' min',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }

    public function getCalendarEventDescription(Booking $booking): string
    {
        $description = [];

        $description[] = "Service: {$booking->service->name}";
        $description[] = "Reference: {$booking->booking_reference}";

        if ($this->sync_settings_display['include_client_name'] && $booking->client_name) {
            $description[] = "Client: {$booking->client_name}";
        }

        if ($this->sync_settings_display['include_location'] && $booking->serviceLocation) {
            $description[] = "Location: {$booking->serviceLocation->name}";
        }

        if ($this->sync_settings_display['include_notes'] && $booking->notes) {
            $description[] = "Notes: {$booking->notes}";
        }

        $description[] = "Duration: {$booking->duration_minutes} minutes";
        $description[] = "Status: " . ucfirst($booking->status);

        return implode("\n", $description);
    }

    public function getProviderConfig(): array
    {
        return match ($this->provider) {
            'google' => [
                'auth_url' => 'https://accounts.google.com/o/oauth2/auth',
                'token_url' => 'https://oauth2.googleapis.com/token',
                'api_base' => 'https://www.googleapis.com/calendar/v3',
                'scopes' => ['https://www.googleapis.com/auth/calendar'],
            ],
            'outlook' => [
                'auth_url' => 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize',
                'token_url' => 'https://login.microsoftonline.com/common/oauth2/v2.0/token',
                'api_base' => 'https://graph.microsoft.com/v1.0',
                'scopes' => ['https://graph.microsoft.com/calendars.readwrite'],
            ],
            default => []
        };
    }

    // Static helper methods
    public static function createForUser(
        int $userId,
        string $provider,
        string $calendarId,
        ?int $serviceId = null
    ): self {
        return self::create([
            'user_id' => $userId,
            'service_id' => $serviceId,
            'provider' => $provider,
            'calendar_id' => $calendarId,
            'is_active' => false, // Activate after successful token exchange
            'sync_bookings' => true,
            'sync_availability' => false,
            'auto_block_external_events' => false,
        ]);
    }

    public static function getActiveIntegrationsForService(int $serviceId): \Illuminate\Database\Eloquent\Collection
    {
        return self::active()
            ->where(function ($query) use ($serviceId) {
                $query->where('service_id', $serviceId)
                    ->orWhereNull('service_id');
            })
            ->get();
    }

    public static function getHealthyIntegrationsForUser(int $userId): \Illuminate\Database\Eloquent\Collection
    {
        return self::forUser($userId)
            ->active()
            ->where('token_expires_at', '>', now())
            ->get();
    }

    public static function getIntegrationsNeedingRefresh(): \Illuminate\Database\Eloquent\Collection
    {
        return self::active()
            ->where('token_expires_at', '<', now()->addMinutes(10))
            ->whereNotNull('refresh_token')
            ->get();
    }

    public static function cleanupInactiveIntegrations(int $daysOld = 30): int
    {
        return self::inactive()
            ->where('updated_at', '<', now()->subDays($daysOld))
            ->delete();
    }

    // Validation rules
    public static function getValidationRules(): array
    {
        return [
            'user_id' => 'required|exists:users,id',
            'service_id' => 'nullable|exists:services,id',
            'provider' => 'required|in:google,outlook,apple,ical',
            'calendar_id' => 'required|string|max:255',
            'sync_bookings' => 'boolean',
            'sync_availability' => 'boolean',
            'auto_block_external_events' => 'boolean',
            'sync_settings' => 'nullable|array',
        ];
    }

    public static function getSyncSettingsValidationRules(): array
    {
        return [
            'sync_frequency' => 'integer|min:5|max:1440', // 5 minutes to 24 hours
            'event_title_template' => 'string|max:255',
            'include_client_name' => 'boolean',
            'include_location' => 'boolean',
            'include_notes' => 'boolean',
            'calendar_color' => 'string|regex:/^#[a-fA-F0-9]{6}$/',
            'reminder_minutes' => 'array',
            'reminder_minutes.*' => 'integer|min:0|max:10080', // Up to 1 week
        ];
    }
}
