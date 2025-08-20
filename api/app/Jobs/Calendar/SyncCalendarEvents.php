<?php

namespace App\Jobs\Calendar;

use App\Models\CalendarIntegration;
use App\Models\CalendarSyncJob;
use App\Services\V1\Calendar\CalendarIntegrationService;
use App\Services\V1\Calendar\CalendarSyncService;
use Carbon\Carbon;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncCalendarEvents implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public CalendarIntegration $integration;
    public array $options;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 300; // 5 minutes

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public function backoff(): array
    {
        return [30, 120, 300]; // 30 seconds, 2 minutes, 5 minutes
    }

    /**
     * Create a new job instance.
     */
    public function __construct(CalendarIntegration $integration, array $options = [])
    {
        $this->integration = $integration;
        $this->options = $options;

        // Set queue based on priority
        $priority = $options['priority'] ?? 'normal';
        $this->onQueue($this->getQueueName($priority));
    }

    /**
     * Execute the job.
     */
    public function handle(CalendarSyncService $syncService): void
    {
        try {
            Log::info('Starting calendar sync job', [
                'integration_id' => $this->integration->id,
                'provider' => $this->integration->provider,
                'user_id' => $this->integration->user_id,
                'attempt' => $this->attempts(),
            ]);

            // Check if integration is still active
            $this->integration->refresh();

            if (!$this->integration->is_active) {
                Log::info('Skipping sync for inactive integration', [
                    'integration_id' => $this->integration->id,
                ]);
                return;
            }

            // Create sync job record
            $syncJob = $this->createSyncJobRecord();

            // Update sync job to processing
            $syncJob->update([
                'status' => 'processing',
                'started_at' => now(),
            ]);

            // Perform the sync
            $results = $syncService->syncExternalEvents($this->integration);

            if (isset($results['error'])) {
                throw new Exception($results['error']);
            }

            // Update sync job to completed
            $syncJob->update([
                'status' => 'completed',
                'completed_at' => now(),
                'events_processed' => $results['synced'] ?? 0,
                'job_data' => [
                    'results' => $results,
                    'sync_options' => $this->options,
                ],
            ]);

            // Reset error count on successful sync
            $this->integration->update([
                'sync_error_count' => 0,
                'last_sync_error' => null,
            ]);

            Log::info('Calendar sync job completed successfully', [
                'integration_id' => $this->integration->id,
                'events_synced' => $results['synced'] ?? 0,
                'events_updated' => $results['updated'] ?? 0,
                'sync_duration' => $syncJob->started_at->diffInSeconds(now()),
            ]);

        } catch (Exception $e) {
            $this->handleSyncError($e);
            throw $e; // Re-throw to trigger retry mechanism
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Exception $exception): void
    {
        Log::error('Calendar sync job failed permanently', [
            'integration_id' => $this->integration->id,
            'provider' => $this->integration->provider,
            'attempts' => $this->attempts(),
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        // Update integration with error information
        $this->integration->increment('sync_error_count');
        $this->integration->update([
            'last_sync_error' => $exception->getMessage(),
        ]);

        // Mark sync job as failed
        $syncJob = CalendarSyncJob::where('calendar_integration_id', $this->integration->id)
            ->where('status', 'processing')
            ->latest()
            ->first();

        if ($syncJob) {
            $syncJob->update([
                'status' => 'failed',
                'completed_at' => now(),
                'error_message' => $exception->getMessage(),
            ]);
        }

        // Disable integration if too many failures
        if ($this->integration->sync_error_count >= 10) {
            $this->integration->update(['is_active' => false]);

            Log::warning('Calendar integration disabled due to excessive failures', [
                'integration_id' => $this->integration->id,
                'error_count' => $this->integration->sync_error_count,
            ]);

            // TODO: Send notification to user about disabled integration
        }
    }

    /**
     * Determine if the job should be retried.
     */
    public function shouldRetry(Exception $exception): bool
    {
        // Don't retry for certain types of errors
        $nonRetryableErrors = [
            'invalid_grant', // OAuth token permanently invalid
            'unauthorized', // Permanent authorization issue
            'forbidden', // Permanent permission issue
        ];

        $errorMessage = strtolower($exception->getMessage());

        foreach ($nonRetryableErrors as $nonRetryableError) {
            if (str_contains($errorMessage, $nonRetryableError)) {
                Log::info('Not retrying sync job due to permanent error', [
                    'integration_id' => $this->integration->id,
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
        return [
            'calendar-sync',
            'integration:' . $this->integration->id,
            'provider:' . $this->integration->provider,
            'user:' . $this->integration->user_id,
        ];
    }

    /**
     * Get the middleware the job should pass through.
     */
    public function middleware(): array
    {
        return [
            // Rate limit calendar API calls per provider
            new RateLimitCalendarApi($this->integration->provider),
        ];
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public function retryAfter(): int
    {
        $baseDelay = 60; // 1 minute base delay
        $attempt = $this->attempts();

        // Exponential backoff with jitter
        $delay = min($baseDelay * pow(2, $attempt - 1), 3600); // Max 1 hour
        $jitter = rand(0, 30); // Add up to 30 seconds jitter

        return $delay + $jitter;
    }

    /**
     * Create sync job record for tracking
     */
    private function createSyncJobRecord(): CalendarSyncJob
    {
        return CalendarSyncJob::create([
            'calendar_integration_id' => $this->integration->id,
            'job_type' => 'sync_events',
            'status' => 'pending',
            'job_data' => [
                'sync_options' => $this->options,
                'queue_name' => $this->queue,
                'job_id' => $this->job?->getJobId(),
            ],
        ]);
    }

    /**
     * Handle sync error
     */
    private function handleSyncError(Exception $exception): void
    {
        Log::error('Calendar sync error occurred', [
            'integration_id' => $this->integration->id,
            'provider' => $this->integration->provider,
            'attempt' => $this->attempts(),
            'error' => $exception->getMessage(),
            'error_class' => get_class($exception),
        ]);

        // Update sync job with error
        $syncJob = CalendarSyncJob::where('calendar_integration_id', $this->integration->id)
            ->where('status', 'processing')
            ->latest()
            ->first();

        if ($syncJob) {
            $syncJob->update([
                'error_message' => $exception->getMessage(),
                'job_data' => array_merge($syncJob->job_data ?? [], [
                    'last_error' => [
                        'message' => $exception->getMessage(),
                        'class' => get_class($exception),
                        'attempt' => $this->attempts(),
                        'occurred_at' => now()->toISOString(),
                    ],
                ]),
            ]);
        }

        // Increment error count
        $this->integration->increment('sync_error_count');
        $this->integration->update([
            'last_sync_error' => $exception->getMessage(),
        ]);

        // Handle specific error types
        if (str_contains(strtolower($exception->getMessage()), 'token')) {
            $this->handleTokenError($exception);
        }

        if (str_contains(strtolower($exception->getMessage()), 'rate limit')) {
            $this->handleRateLimitError($exception);
        }
    }

    /**
     * Handle token-related errors
     */
    private function handleTokenError(Exception $exception): void
    {
        Log::warning('Token error detected during sync', [
            'integration_id' => $this->integration->id,
            'error' => $exception->getMessage(),
        ]);

        // Try to refresh token if refresh token exists
        if ($this->integration->refresh_token && $this->integration->provider === 'google') {
            try {
                $integrationService = app(CalendarIntegrationService::class);
                $refreshed = $integrationService->refreshTokens($this->integration);

                if ($refreshed) {
                    Log::info('Successfully refreshed tokens during sync job', [
                        'integration_id' => $this->integration->id,
                    ]);
                }
            } catch (Exception $refreshException) {
                Log::error('Failed to refresh tokens during sync job', [
                    'integration_id' => $this->integration->id,
                    'refresh_error' => $refreshException->getMessage(),
                ]);
            }
        }
    }

    /**
     * Handle rate limit errors
     */
    private function handleRateLimitError(Exception $exception): void
    {
        Log::warning('Rate limit error detected during sync', [
            'integration_id' => $this->integration->id,
            'error' => $exception->getMessage(),
        ]);

        // Add extra delay for rate limit errors
        $this->release($this->retryAfter() + 300); // Add 5 minutes extra delay
    }

    /**
     * Get queue name based on priority
     */
    private function getQueueName(string $priority): string
    {
        return match ($priority) {
            'high' => 'calendar-sync-high',
            'low' => 'calendar-sync-low',
            default => 'calendar-sync'
        };
    }

    /**
     * Get unique identifier for this job type
     */
    public function uniqueId(): string
    {
        return "calendar_sync_{$this->integration->id}";
    }

    /**
     * Determine if job should be unique
     */
    public function uniqueFor(): int
    {
        return 300; // 5 minutes - prevent duplicate sync jobs
    }

    /**
     * Handle job timeout
     */
    public function timeoutAt(): Carbon
    {
        return now()->addMinutes(10); // Hard timeout at 10 minutes
    }

    /**
     * Get job display name for monitoring
     */
    public function displayName(): string
    {
        return "Sync Calendar Events (Integration: {$this->integration->id}, Provider: {$this->integration->provider})";
    }
}
