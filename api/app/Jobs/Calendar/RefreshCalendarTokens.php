<?php

namespace App\Jobs\Calendar;

use App\Models\CalendarIntegration;
use App\Services\V1\Calendar\CalendarIntegrationService;
use Carbon\Carbon;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RefreshCalendarTokens implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public ?CalendarIntegration $integration;
    public array $options;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 180; // 3 minutes

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public function backoff(): array
    {
        return [60, 300, 900]; // 1 minute, 5 minutes, 15 minutes
    }

    /**
     * Create a new job instance.
     */
    public function __construct(?CalendarIntegration $integration = null, array $options = [])
    {
        $this->integration = $integration;
        $this->options = $options;

        // Set queue based on operation type
        $queueName = 'calendar-tokens';
        $this->onQueue($queueName);
    }

    /**
     * Execute the job.
     */
    public function handle(CalendarIntegrationService $integrationService): void
    {
        try {
            if ($this->integration) {
                $this->refreshSingleIntegration($integrationService);
            }
        } catch (Exception $e) {
            Log::error('Calendar token refresh job failed', [
                'integration_id' => $this->integration?->id,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Exception $exception): void
    {
        if ($this->integration) {
            Log::error('Calendar token refresh failed permanently for integration', [
                'integration_id' => $this->integration->id,
                'provider' => $this->integration->provider,
                'user_id' => $this->integration->user_id,
                'attempts' => $this->attempts(),
                'error' => $exception->getMessage(),
            ]);

            // Mark integration as having token issues
            $this->integration->update([
                'last_sync_error' => 'Token refresh failed: ' . $exception->getMessage(),
                'sync_error_count' => $this->integration->sync_error_count + 1,
            ]);

            // Disable integration if too many token refresh failures
            if ($this->integration->sync_error_count >= 5) {
                $this->integration->update(['is_active' => false]);

                Log::warning('Calendar integration disabled due to token refresh failures', [
                    'integration_id' => $this->integration->id,
                    'error_count' => $this->integration->sync_error_count,
                ]);

                // TODO: Send notification to user about disabled integration
                $this->sendIntegrationDisabledNotification();
            }
        }
    }

    /**
     * Determine if the job should be retried.
     */
    public function shouldRetry(Exception $exception): bool
    {
        $errorMessage = strtolower($exception->getMessage());

        // Don't retry for permanent OAuth errors
        $nonRetryableErrors = [
            'invalid_grant',
            'invalid_client',
            'unauthorized_client',
            'refresh_token_expired',
            'refresh_token_revoked',
        ];

        foreach ($nonRetryableErrors as $nonRetryableError) {
            if (str_contains($errorMessage, $nonRetryableError)) {
                Log::info('Not retrying token refresh due to permanent error', [
                    'integration_id' => $this->integration?->id,
                    'error' => $exception->getMessage(),
                ]);
                return false;
            }
        }

        return true;
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        $tags = ['calendar-token-refresh'];

        if ($this->integration) {
            $tags = array_merge($tags, [
                'integration:' . $this->integration->id,
                'provider:' . $this->integration->provider,
                'user:' . $this->integration->user_id,
            ]);
        }

        return $tags;
    }

    /**
     * Get the middleware the job should pass through.
     */
    public function middleware(): array
    {
        return [
            // Rate limit OAuth requests to prevent API abuse
            new RateLimitOAuthRequests(),
        ];
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public function retryAfter(): int
    {
        $baseDelay = 120; // 2 minutes base delay
        $attempt = $this->attempts();

        // Exponential backoff with longer delays for token refresh
        $delay = min($baseDelay * pow(2, $attempt - 1), 3600); // Max 1 hour

        return $delay + rand(0, 60); // Add jitter
    }

    /**
     * Refresh tokens for a single integration
     */
    private function refreshSingleIntegration(CalendarIntegrationService $integrationService): void
    {
        Log::info('Starting token refresh for integration', [
            'integration_id' => $this->integration->id,
            'provider' => $this->integration->provider,
            'user_id' => $this->integration->user_id,
            'token_expires_at' => $this->integration->token_expires_at?->toISOString(),
            'attempt' => $this->attempts(),
        ]);

        // Check if integration is still active
        $this->integration->refresh();

        if (!$this->integration->is_active) {
            Log::info('Skipping token refresh for inactive integration', [
                'integration_id' => $this->integration->id,
            ]);
            return;
        }

        // Check if integration supports token refresh
        if (!$this->integration->refresh_token) {
            Log::warning('No refresh token available for integration', [
                'integration_id' => $this->integration->id,
                'provider' => $this->integration->provider,
            ]);

            // Mark as needing re-authorization
            $this->markNeedsReauthorization();
            return;
        }

        // Perform token refresh
        $success = $integrationService->refreshTokens($this->integration);

        if ($success) {
            Log::info('Token refresh successful for integration', [
                'integration_id' => $this->integration->id,
                'provider' => $this->integration->provider,
                'new_expires_at' => $this->integration->fresh()->token_expires_at?->toISOString(),
            ]);

            // Reset error count on successful refresh
            $this->integration->update([
                'sync_error_count' => 0,
                'last_sync_error' => null,
            ]);

            // Schedule next refresh if auto-refresh is enabled
            if ($this->options['schedule_next'] ?? true) {
                $this->scheduleNextRefresh();
            }

        } else {
            throw new Exception('Token refresh failed via integration service');
        }
    }

    /**
     * Mark integration as needing re-authorization
     */
    private function markNeedsReauthorization(): void
    {
        $this->integration->update([
            'is_active' => false,
            'last_sync_error' => 'Re-authorization required - no refresh token available',
            'sync_error_count' => $this->integration->sync_error_count + 1,
        ]);

        Log::warning('Integration marked as needing re-authorization', [
            'integration_id' => $this->integration->id,
            'provider' => $this->integration->provider,
        ]);

        // TODO: Send notification to user about re-authorization needed
        $this->sendReauthorizationNotification();
    }

    /**
     * Schedule next token refresh for this integration
     */
    private function scheduleNextRefresh(): void
    {
        if (!$this->integration->token_expires_at) {
            return; // No expiry time, can't schedule
        }

        // Schedule refresh 1 hour before expiry
        $refreshAt = $this->integration->token_expires_at->subHour();

        // Don't schedule if it's too soon (less than 5 minutes)
        if ($refreshAt->lt(now()->addMinutes(5))) {
            return;
        }

        RefreshCalendarTokens::dispatch($this->integration, [
            'schedule_next' => true,
            'auto_scheduled' => true,
        ])
            ->delay($refreshAt)
            ->onQueue('calendar-tokens');

        Log::info('Next token refresh scheduled', [
            'integration_id' => $this->integration->id,
            'refresh_at' => $refreshAt->toISOString(),
        ]);
    }

    /**
     * Send integration disabled notification
     */
    private function sendIntegrationDisabledNotification(): void
    {
        // TODO: Implement notification logic
        Log::info('Integration disabled notification sent', [
            'integration_id' => $this->integration->id,
            'user_id' => $this->integration->user_id,
        ]);
    }

    /**
     * Send re-authorization needed notification
     */
    private function sendReauthorizationNotification(): void
    {
        // TODO: Implement notification logic
        Log::info('Re-authorization notification sent', [
            'integration_id' => $this->integration->id,
            'user_id' => $this->integration->user_id,
        ]);
    }

    /**
     * Get unique identifier for this job type
     */
    public function uniqueId(): string
    {
        return "refresh_calendar_tokens_{$this->integration->id}";
    }

    /**
     * Determine if job should be unique
     */
    public function uniqueFor(): int
    {
        return 1800;
    }

    /**
     * Get job display name for monitoring
     */
    public function displayName(): string
    {
        return "Refresh Calendar Tokens (Integration: {$this->integration->id}, Provider: {$this->integration->provider})";
    }

    /**
     * Handle job timeout
     */
    public function timeoutAt(): Carbon
    {
        return now()->addMinutes(5); // Hard timeout
    }

    /**
     * Static method to dispatch refresh for specific integration
     */
    public static function dispatchForIntegration(CalendarIntegration $integration, array $options = []): void
    {
        self::dispatch($integration, array_merge([
            'schedule_next' => true,
        ], $options))->onQueue('calendar-tokens');
    }

    /**
     * Static method to check and refresh all expiring tokens
     */
    public static function refreshExpiringTokens(): array
    {
        $results = ['dispatched' => 0, 'errors' => 0];

        try {
            $expiringIntegrations = CalendarIntegration::where('is_active', true)
                ->whereNotNull('refresh_token')
                ->where('token_expires_at', '<', now()->addHours(2))
                ->get();

            foreach ($expiringIntegrations as $integration) {
                try {
                    self::dispatchForIntegration($integration, [
                        'schedule_next' => false,
                        'urgent' => true,
                    ]);
                    $results['dispatched']++;
                } catch (Exception $e) {
                    $results['errors']++;
                    Log::error('Failed to dispatch urgent token refresh', [
                        'integration_id' => $integration->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            Log::info('Expiring tokens refresh completed', $results);

        } catch (Exception $e) {
            Log::error('Failed to check expiring tokens', [
                'error' => $e->getMessage(),
            ]);
            $results['errors']++;
        }

        return $results;
    }
}
