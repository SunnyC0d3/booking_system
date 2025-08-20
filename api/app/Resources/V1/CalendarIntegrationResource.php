<?php

namespace App\Resources\V1;

use App\Constants\CalendarProviders;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class CalendarIntegrationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'provider' => $this->provider,
            'provider_display_name' => $this->getProviderDisplayName(),
            'calendar_id' => $this->calendar_id,
            'calendar_name' => $this->calendar_name,

            // Ownership and service association
            'user_id' => $this->user_id,
            'service_id' => $this->service_id,
            'service' => $this->when($this->service, function () {
                return [
                    'id' => $this->service->id,
                    'name' => $this->service->name,
                    'category' => $this->service->category,
                ];
            }),
            'applies_to_all_services' => is_null($this->service_id),

            // Integration status and health
            'status' => [
                'is_active' => $this->is_active,
                'is_healthy' => $this->getHealthStatus(),
                'health_score' => $this->getHealthScore(),
                'connection_status' => $this->getConnectionStatus(),
                'last_successful_sync' => $this->getLastSuccessfulSync()?->toISOString(),
                'sync_error_count' => $this->sync_error_count,
                'last_sync_error' => $this->last_sync_error,
                'requires_attention' => $this->requiresAttention(),
                'status_message' => $this->getStatusMessage(),
            ],

            // Token information (security filtered)
            'token_info' => [
                'has_valid_tokens' => $this->hasValidTokens(),
                'token_expires_at' => $this->token_expires_at?->toISOString(),
                'expires_in_minutes' => $this->getTokenExpiresInMinutes(),
                'needs_refresh' => $this->needsTokenRefresh(),
                'can_auto_refresh' => $this->canAutoRefreshTokens(),
            ],

            // Sync configuration
            'sync_settings' => [
                'sync_bookings' => $this->sync_bookings,
                'sync_availability' => $this->sync_availability,
                'auto_block_external_events' => $this->auto_block_external_events,
                'advanced_settings' => $this->getSyncSettingsDisplay(),
            ],

            // Sync statistics and history
            'sync_stats' => [
                'total_syncs' => $this->getTotalSyncCount(),
                'successful_syncs' => $this->getSuccessfulSyncCount(),
                'failed_syncs' => $this->getFailedSyncCount(),
                'last_sync_at' => $this->last_sync_at?->toISOString(),
                'last_sync_duration' => $this->getLastSyncDuration(),
                'average_sync_duration' => $this->getAverageSyncDuration(),
                'next_scheduled_sync' => $this->getNextScheduledSync()?->toISOString(),
                'sync_frequency_minutes' => $this->getSyncFrequency(),
            ],

            // Event counts and calendar data
            'calendar_data' => [
                'total_events_synced' => $this->getTotalEventsSynced(),
                'events_last_30_days' => $this->getEventsCount(30),
                'upcoming_events' => $this->getUpcomingEventsCount(),
                'blocked_time_slots' => $this->getBlockedTimeSlotsCount(),
                'last_event_sync' => $this->getLastEventSync()?->toISOString(),
            ],

            // Provider-specific information
            'provider_info' => $this->getProviderSpecificInfo(),

            // User capabilities and permissions
            'capabilities' => [
                'can_sync' => $this->canSync(),
                'can_force_sync' => $this->canForceSync($request->user()),
                'can_edit_settings' => $this->canEditSettings($request->user()),
                'can_delete' => $this->canDelete($request->user()),
                'can_view_events' => $this->canViewEvents($request->user()),
                'sync_directions_available' => $this->getAvailableSyncDirections(),
            ],

            // Recent activity and logs
            'recent_activity' => $this->when($this->shouldIncludeActivity($request), function () {
                return $this->getRecentActivity();
            }),

            // Integration warnings and recommendations
            'warnings' => $this->getWarnings(),
            'recommendations' => $this->getRecommendations(),

            // Metadata
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            'display_order' => $this->getDisplayOrder(),
            'is_primary' => $this->isPrimaryIntegration(),
        ];
    }

    /**
     * Get provider display name
     */
    private function getProviderDisplayName(): string
    {
        return match ($this->provider) {
            CalendarProviders::GOOGLE => 'Google Calendar',
            CalendarProviders::OUTLOOK => 'Outlook Calendar',
            CalendarProviders::APPLE => 'Apple Calendar',
            CalendarProviders::ICAL => 'iCal/WebCal',
            default => ucfirst($this->provider) . ' Calendar'
        };
    }

    /**
     * Get overall health status
     */
    private function getHealthStatus(): bool
    {
        return $this->is_active &&
            $this->sync_error_count < 3 &&
            $this->hasValidTokens() &&
            (!$this->last_sync_at || $this->last_sync_at->gt(now()->subDays(7)));
    }

    /**
     * Get health score (0-100)
     */
    private function getHealthScore(): int
    {
        $score = 100;

        // Deduct points for errors
        $score -= min($this->sync_error_count * 10, 50);

        // Deduct points for inactive status
        if (!$this->is_active) {
            $score -= 30;
        }

        // Deduct points for expired tokens
        if (!$this->hasValidTokens()) {
            $score -= 25;
        }

        // Deduct points for old sync
        if ($this->last_sync_at && $this->last_sync_at->lt(now()->subDays(3))) {
            $score -= 15;
        }

        return max(0, $score);
    }

    /**
     * Get connection status
     */
    private function getConnectionStatus(): string
    {
        if (!$this->is_active) {
            return 'inactive';
        }

        if (!$this->hasValidTokens()) {
            return 'token_expired';
        }

        if ($this->sync_error_count >= 5) {
            return 'error';
        }

        if ($this->sync_error_count >= 3) {
            return 'warning';
        }

        return 'connected';
    }

    /**
     * Check if integration has valid tokens
     */
    private function hasValidTokens(): bool
    {
        // iCal doesn't use OAuth tokens
        if ($this->provider === CalendarProviders::ICAL) {
            return !empty($this->calendar_id);
        }

        return !empty($this->access_token) &&
            (!$this->token_expires_at || $this->token_expires_at->gt(now()));
    }

    /**
     * Get minutes until token expires
     */
    private function getTokenExpiresInMinutes(): ?int
    {
        if (!$this->token_expires_at) {
            return null;
        }

        $minutes = now()->diffInMinutes($this->token_expires_at, false);
        return $minutes > 0 ? $minutes : 0;
    }

    /**
     * Check if token needs refresh
     */
    private function needsTokenRefresh(): bool
    {
        if (!$this->token_expires_at) {
            return false;
        }

        // Refresh if expires within 1 hour
        return $this->token_expires_at->lt(now()->addHour());
    }

    /**
     * Check if can auto-refresh tokens
     */
    private function canAutoRefreshTokens(): bool
    {
        return !empty($this->refresh_token) &&
            $this->provider !== CalendarProviders::ICAL;
    }

    /**
     * Get last successful sync time
     */
    private function getLastSuccessfulSync(): ?Carbon
    {
        $lastSuccessfulJob = $this->calendarSyncJobs()
            ->where('status', 'completed')
            ->latest('completed_at')
            ->first();

        return $lastSuccessfulJob?->completed_at;
    }

    /**
     * Check if integration requires attention
     */
    private function requiresAttention(): bool
    {
        return !$this->is_active ||
            $this->sync_error_count >= 3 ||
            !$this->hasValidTokens() ||
            ($this->last_sync_at && $this->last_sync_at->lt(now()->subDays(7)));
    }

    /**
     * Get status message
     */
    private function getStatusMessage(): string
    {
        if (!$this->is_active) {
            return 'Integration is disabled';
        }

        if (!$this->hasValidTokens()) {
            return 'Calendar access expired - please reconnect';
        }

        if ($this->sync_error_count >= 5) {
            return 'Multiple sync failures - check calendar connection';
        }

        if ($this->sync_error_count >= 3) {
            return 'Recent sync issues detected';
        }

        if (!$this->last_sync_at) {
            return 'No sync performed yet';
        }

        if ($this->last_sync_at->lt(now()->subDays(7))) {
            return 'Sync may be overdue';
        }

        return 'Operating normally';
    }

    /**
     * Get sync settings display (processed for UI)
     */
    private function getSyncSettingsDisplay(): array
    {
        $settings = $this->sync_settings_display ?? [];

        return [
            'include_client_name' => $settings['include_client_name'] ?? true,
            'include_location' => $settings['include_location'] ?? true,
            'calendar_color' => $settings['calendar_color'] ?? '#4285F4',
            'reminder_minutes' => $settings['reminder_minutes'] ?? [15, 60],
            'sync_frequency' => $settings['sync_frequency'] ?? 60,
            'max_events_per_sync' => $settings['max_events_per_sync'] ?? 100,
            'event_title_template' => $settings['event_title_template'] ?? 'Booking: {{client_name}}',
            'event_description_template' => $settings['event_description_template'] ?? null,
        ];
    }

    /**
     * Get total sync count
     */
    private function getTotalSyncCount(): int
    {
        return $this->calendarSyncJobs()->count();
    }

    /**
     * Get successful sync count
     */
    private function getSuccessfulSyncCount(): int
    {
        return $this->calendarSyncJobs()
            ->where('status', 'completed')
            ->count();
    }

    /**
     * Get failed sync count
     */
    private function getFailedSyncCount(): int
    {
        return $this->calendarSyncJobs()
            ->where('status', 'failed')
            ->count();
    }

    /**
     * Get last sync duration in seconds
     */
    private function getLastSyncDuration(): ?int
    {
        $lastJob = $this->calendarSyncJobs()
            ->whereNotNull('started_at')
            ->whereNotNull('completed_at')
            ->latest('completed_at')
            ->first();

        if (!$lastJob) {
            return null;
        }

        return $lastJob->started_at->diffInSeconds($lastJob->completed_at);
    }

    /**
     * Get average sync duration in seconds
     */
    private function getAverageSyncDuration(): ?int
    {
        $jobs = $this->calendarSyncJobs()
            ->whereNotNull('started_at')
            ->whereNotNull('completed_at')
            ->where('status', 'completed')
            ->limit(10)
            ->get();

        if ($jobs->isEmpty()) {
            return null;
        }

        $totalDuration = $jobs->sum(function ($job) {
            return $job->started_at->diffInSeconds($job->completed_at);
        });

        return intval($totalDuration / $jobs->count());
    }

    /**
     * Get next scheduled sync time
     */
    private function getNextScheduledSync(): ?Carbon
    {
        if (!$this->is_active || !$this->last_sync_at) {
            return null;
        }

        $frequency = $this->getSyncFrequency();
        return $this->last_sync_at->addMinutes($frequency);
    }

    /**
     * Get sync frequency in minutes
     */
    private function getSyncFrequency(): int
    {
        $settings = $this->sync_settings_display ?? [];
        return $settings['sync_frequency'] ?? 60; // Default 1 hour
    }

    /**
     * Get total events synced count
     */
    private function getTotalEventsSynced(): int
    {
        return $this->calendarEvents()->count();
    }

    /**
     * Get events count for last N days
     */
    private function getEventsCount(int $days): int
    {
        return $this->calendarEvents()
            ->where('synced_at', '>=', now()->subDays($days))
            ->count();
    }

    /**
     * Get upcoming events count
     */
    private function getUpcomingEventsCount(): int
    {
        return $this->calendarEvents()
            ->where('starts_at', '>=', now())
            ->where('blocks_booking', true)
            ->count();
    }

    /**
     * Get blocked time slots count
     */
    private function getBlockedTimeSlotsCount(): int
    {
        return $this->calendarEvents()
            ->where('starts_at', '>=', now())
            ->where('ends_at', '<=', now()->addDays(30))
            ->where('blocks_booking', true)
            ->count();
    }

    /**
     * Get last event sync time
     */
    private function getLastEventSync(): ?Carbon
    {
        $lastEvent = $this->calendarEvents()
            ->latest('synced_at')
            ->first();

        return $lastEvent?->synced_at;
    }

    /**
     * Get provider-specific information
     */
    private function getProviderSpecificInfo(): array
    {
        $info = [
            'supports_push' => $this->supportsPushSync(),
            'supports_pull' => $this->supportsPullSync(),
            'supports_webhooks' => $this->supportsWebhooks(),
            'max_events_per_sync' => $this->getMaxEventsPerSync(),
            'rate_limit_info' => $this->getRateLimitInfo(),
        ];

        switch ($this->provider) {
            case CalendarProviders::GOOGLE:
                $info['calendar_list_url'] = 'https://calendar.google.com/calendar/r/settings';
                $info['sharing_url'] = $this->calendar_id ?
                    "https://calendar.google.com/calendar/embed?src={$this->calendar_id}" : null;
                break;

            case CalendarProviders::OUTLOOK:
                $info['calendar_list_url'] = 'https://outlook.live.com/calendar/';
                $info['requires_business_account'] = true;
                break;

            case CalendarProviders::ICAL:
                $info['is_read_only'] = true;
                $info['calendar_url'] = $this->calendar_id;
                $info['requires_public_url'] = true;
                break;
        }

        return $info;
    }

    /**
     * Check if can sync
     */
    private function canSync(): bool
    {
        return $this->is_active &&
            $this->hasValidTokens() &&
            $this->sync_error_count < 5;
    }

    /**
     * Check if user can force sync
     */
    private function canForceSync($user): bool
    {
        return $user && (
                $user->hasPermission('force_calendar_sync') ||
                ($user->id === $this->user_id && $user->hasPermission('force_own_calendar_sync'))
            );
    }

    /**
     * Check if user can edit settings
     */
    private function canEditSettings($user): bool
    {
        return $user && (
                $user->hasPermission('manage_all_calendar_integrations') ||
                ($user->id === $this->user_id && $user->hasPermission('manage_calendar_integrations'))
            );
    }

    /**
     * Check if user can delete
     */
    private function canDelete($user): bool
    {
        return $user && (
                $user->hasPermission('manage_all_calendar_integrations') ||
                ($user->id === $this->user_id && $user->hasPermission('manage_calendar_integrations'))
            );
    }

    /**
     * Check if user can view events
     */
    private function canViewEvents($user): bool
    {
        return $user && (
                $user->hasPermission('view_all_calendar_events') ||
                ($user->id === $this->user_id && $user->hasPermission('view_calendar_events'))
            );
    }

    /**
     * Get available sync directions
     */
    private function getAvailableSyncDirections(): array
    {
        $directions = [];

        if ($this->supportsPushSync()) {
            $directions[] = 'push';
        }

        if ($this->supportsPullSync()) {
            $directions[] = 'pull';
        }

        if (count($directions) === 2) {
            $directions[] = 'bidirectional';
        }

        return $directions;
    }

    /**
     * Check if should include activity in response
     */
    private function shouldIncludeActivity($request): bool
    {
        return $request->boolean('include_activity', false) &&
            $this->canViewEvents($request->user());
    }

    /**
     * Get recent activity
     */
    private function getRecentActivity(): array
    {
        $recentJobs = $this->calendarSyncJobs()
            ->latest('created_at')
            ->limit(5)
            ->get();

        return $recentJobs->map(function ($job) {
            return [
                'id' => $job->id,
                'type' => $job->job_type,
                'status' => $job->status,
                'started_at' => $job->started_at?->toISOString(),
                'completed_at' => $job->completed_at?->toISOString(),
                'events_processed' => $job->events_processed,
                'error_message' => $job->error_message,
                'duration_seconds' => $job->started_at && $job->completed_at ?
                    $job->started_at->diffInSeconds($job->completed_at) : null,
            ];
        })->toArray();
    }

    /**
     * Get integration warnings
     */
    private function getWarnings(): array
    {
        $warnings = [];

        if (!$this->is_active) {
            $warnings[] = [
                'type' => 'inactive',
                'message' => 'Integration is disabled',
                'severity' => 'medium',
                'action' => 'Enable integration to resume calendar sync',
            ];
        }

        if (!$this->hasValidTokens()) {
            $warnings[] = [
                'type' => 'token_expired',
                'message' => 'Calendar access has expired',
                'severity' => 'high',
                'action' => 'Reconnect your calendar to restore sync',
            ];
        }

        if ($this->sync_error_count >= 3) {
            $warnings[] = [
                'type' => 'sync_errors',
                'message' => "Multiple sync failures ({$this->sync_error_count} errors)",
                'severity' => $this->sync_error_count >= 5 ? 'high' : 'medium',
                'action' => 'Check calendar connection and error details',
            ];
        }

        if ($this->needsTokenRefresh()) {
            $warnings[] = [
                'type' => 'token_expiring',
                'message' => 'Calendar access expires soon',
                'severity' => 'low',
                'action' => 'Tokens will be automatically refreshed',
            ];
        }

        return $warnings;
    }

    /**
     * Get recommendations
     */
    private function getRecommendations(): array
    {
        $recommendations = [];

        // Sync frequency recommendations
        $frequency = $this->getSyncFrequency();
        if ($frequency > 240) { // More than 4 hours
            $recommendations[] = [
                'type' => 'sync_frequency',
                'message' => 'Consider more frequent syncing for better availability accuracy',
                'action' => 'Reduce sync frequency to 1-2 hours',
                'priority' => 'low',
            ];
        }

        // Settings recommendations
        if (!$this->sync_availability && !$this->sync_bookings) {
            $recommendations[] = [
                'type' => 'sync_settings',
                'message' => 'Enable sync options to get the most from calendar integration',
                'action' => 'Enable booking or availability sync in settings',
                'priority' => 'medium',
            ];
        }

        // Provider-specific recommendations
        if ($this->provider === CalendarProviders::ICAL && $this->sync_bookings) {
            $recommendations[] = [
                'type' => 'provider_limitation',
                'message' => 'iCal calendars are read-only and cannot receive booking updates',
                'action' => 'Consider upgrading to Google or Outlook calendar for full sync',
                'priority' => 'medium',
            ];
        }

        return $recommendations;
    }

    /**
     * Get display order for UI
     */
    private function getDisplayOrder(): int
    {
        // Primary provider first, then by creation date
        if ($this->isPrimaryIntegration()) {
            return 0;
        }

        return $this->created_at->timestamp;
    }

    /**
     * Check if this is the primary integration
     */
    private function isPrimaryIntegration(): bool
    {
        // Could be determined by user preference or first active integration
        return $this->is_active &&
            $this->sync_bookings &&
            $this->hasValidTokens();
    }

    /**
     * Helper methods for provider capabilities
     */
    private function supportsPushSync(): bool
    {
        return $this->provider !== CalendarProviders::ICAL;
    }

    private function supportsPullSync(): bool
    {
        return true; // All providers support pull
    }

    private function supportsWebhooks(): bool
    {
        return in_array($this->provider, [
            CalendarProviders::GOOGLE,
            CalendarProviders::OUTLOOK
        ]);
    }

    private function getMaxEventsPerSync(): int
    {
        return match ($this->provider) {
            CalendarProviders::GOOGLE => 500,
            CalendarProviders::OUTLOOK => 300,
            CalendarProviders::APPLE => 200,
            CalendarProviders::ICAL => 1000,
            default => 100
        };
    }

    private function getRateLimitInfo(): array
    {
        return match ($this->provider) {
            CalendarProviders::GOOGLE => [
                'requests_per_minute' => 100,
                'requests_per_day' => 1000000,
                'burst_limit' => 10,
            ],
            CalendarProviders::OUTLOOK => [
                'requests_per_minute' => 60,
                'requests_per_day' => 50000,
                'burst_limit' => 5,
            ],
            default => [
                'requests_per_minute' => 30,
                'requests_per_day' => 10000,
                'burst_limit' => 3,
            ]
        };
    }
}
