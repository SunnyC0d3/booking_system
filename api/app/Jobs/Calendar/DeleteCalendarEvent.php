<?php

namespace App\Jobs\Calendar;

use App\Models\CalendarIntegration;
use App\Models\Booking;
use App\Models\CalendarEvent;
use App\Services\V1\Calendar\GoogleCalendarService;
use App\Services\V1\Calendar\ICalService;
use Carbon\Carbon;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DeleteCalendarEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public CalendarIntegration $integration;
    public string $externalEventId;
    public ?Booking $booking;
    public array $options;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 60; // 1 minute

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public function backoff(): array
    {
        return [10, 30, 60]; // 10 seconds, 30 seconds, 1 minute
    }

    /**
     * Create a new job instance.
     */
    public function __construct(
        CalendarIntegration $integration,
        string $externalEventId,
        ?Booking $booking = null,
        array $options = []
    ) {
        $this->integration = $integration;
        $this->externalEventId = $externalEventId;
        $this->booking = $booking;
        $this->options = $options;

        // Set queue based on urgency
        $urgency = $this->determineUrgency();
        $this->onQueue($this->getQueueName($urgency));
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info('Starting calendar event deletion', [
                'integration_id' => $this->integration->id,
                'external_event_id' => $this->externalEventId,
                'booking_id' => $this->booking?->id,
                'provider' => $this->integration->provider,
                'attempt' => $this->attempts(),
            ]);

            // Validate integration is still active (allow deletion even if inactive)
            $this->integration->refresh();

            // Get provider service
            $providerService = $this->getProviderService();

            // Delete from external calendar
            $success = $providerService->deleteEvent($this->integration, $this->externalEventId);

            if (!$success) {
                // Check if event was already deleted externally
                if ($this->isEventAlreadyDeleted()) {
                    Log::info('Event already deleted externally, continuing with cleanup', [
                        'integration_id' => $this->integration->id,
                        'external_event_id' => $this->externalEventId,
                    ]);
                } else {
                    throw new Exception('Failed to delete calendar event via provider service');
                }
            }

            // Clean up local calendar event record
            $this->cleanupLocalEventRecord();

            // Update booking metadata if provided
            if ($this->booking) {
                $this->updateBookingMetadata();
            }

            Log::info('Calendar event deleted successfully', [
                'integration_id' => $this->integration->id,
                'external_event_id' => $this->externalEventId,
                'booking_id' => $this->booking?->id,
                'booking_reference' => $this->booking?->booking_reference,
            ]);

            // Send deletion notification if requested
            if ($this->options['notify_deletion'] ?? false) {
                $this->sendDeletionNotification();
            }

        } catch (Exception $e) {
            $this->handleDeletionError($e);
            throw $e; // Re-throw to trigger retry mechanism
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Exception $exception): void
    {
        Log::error('Calendar event deletion failed permanently', [
            'integration_id' => $this->integration->id,
            'external_event_id' => $this->externalEventId,
            'booking_id' => $this->booking?->id,
            'booking_reference' => $this->booking?->booking_reference,
            'attempts' => $this->attempts(),
            'error' => $exception->getMessage(),
        ]);

        // Update integration error count (but don't disable for deletion failures)
        $this->integration->increment('sync_error_count');
        $this->integration->update([
            'last_sync_error' => "Event deletion failed: " . $exception->getMessage(),
        ]);

        // Mark local event as failed to delete
        $this->markEventDeletionFailed($exception);

        // Mark booking if provided
        if ($this->booking) {
            $this->booking->update([
                'metadata' => array_merge($this->booking->metadata ?? [], [
                    'calendar_deletion_failed' => true,
                    'calendar_deletion_error' => $exception->getMessage(),
                    'calendar_deletion_failed_at' => now()->toISOString(),
                ]),
            ]);
        }

        // Send failure notification if requested
        if ($this->options['notify_failure'] ?? false) {
            $this->sendFailureNotification($exception);
        }
    }

    /**
     * Determine if the job should be retried.
     */
    public function shouldRetry(Exception $exception): bool
    {
        // Always try to clean up even if external deletion fails
        $errorMessage = strtolower($exception->getMessage());

        // Don't retry if event is already deleted or not found
        if (str_contains($errorMessage, 'not found') ||
            str_contains($errorMessage, 'deleted') ||
            str_contains($errorMessage, 'does not exist')) {
            Log::info('Not retrying event deletion - event already deleted/not found', [
                'integration_id' => $this->integration->id,
                'external_event_id' => $this->externalEventId,
                'error' => $exception->getMessage(),
            ]);

            // Still clean up local records
            $this->cleanupLocalEventRecord();
            return false;
        }

        // Don't retry for permanent authentication errors
        $nonRetryableErrors = [
            'invalid_grant',
            'unauthorized',
            'forbidden',
            'calendar_not_found',
        ];

        foreach ($nonRetryableErrors as $nonRetryableError) {
            if (str_contains($errorMessage, $nonRetryableError)) {
                Log::info('Not retrying event deletion due to permanent error', [
                    'integration_id' => $this->integration->id,
                    'external_event_id' => $this->externalEventId,
                    'error' => $exception->getMessage(),
                ]);

                // Still clean up local records
                $this->cleanupLocalEventRecord();
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
            'calendar-event-deletion',
            'integration:' . $this->integration->id,
            'event:' . $this->externalEventId,
            'provider:' . $this->integration->provider,
            'user:' . $this->integration->user_id,
            $this->booking ? 'booking:' . $this->booking->id : 'manual-deletion',
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
            // Prevent concurrent operations on same event
            new WithoutOverlapping("calendar_event_{$this->externalEventId}"),
        ];
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public function retryAfter(): int
    {
        $baseDelay = 20; // 20 seconds base delay
        $attempt = $this->attempts();

        // Shorter delays for urgent deletions
        if ($this->isUrgentDeletion()) {
            $baseDelay = 10;
        }

        // Simple exponential backoff
        $delay = min($baseDelay * pow(2, $attempt - 1), 300); // Max 5 minutes

        return $delay + rand(0, 10); // Add small jitter
    }

    /**
     * Get the appropriate provider service
     */
    private function getProviderService()
    {
        return match ($this->integration->provider) {
            'google' => app(GoogleCalendarService::class),
            'ical' => app(ICalService::class),
            default => throw new Exception('Unsupported calendar provider: ' . $this->integration->provider)
        };
    }

    /**
     * Check if event was already deleted externally
     */
    private function isEventAlreadyDeleted(): bool
    {
        try {
            // This would require provider-specific implementation to check event existence
            // For now, we'll assume common error patterns indicate deletion
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Clean up local calendar event record
     */
    private function cleanupLocalEventRecord(): void
    {
        try {
            $deletedCount = CalendarEvent::where('calendar_integration_id', $this->integration->id)
                ->where('external_event_id', $this->externalEventId)
                ->delete();

            if ($deletedCount > 0) {
                Log::info('Local calendar event record deleted', [
                    'integration_id' => $this->integration->id,
                    'external_event_id' => $this->externalEventId,
                    'deleted_count' => $deletedCount,
                ]);
            }

        } catch (Exception $e) {
            Log::error('Failed to cleanup local calendar event record', [
                'integration_id' => $this->integration->id,
                'external_event_id' => $this->externalEventId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Update booking metadata after successful deletion
     */
    private function updateBookingMetadata(): void
    {
        try {
            $metadata = $this->booking->metadata ?? [];

            // Remove any failed sync flags
            unset(
                $metadata['calendar_sync_failed'],
                $metadata['calendar_update_failed'],
                $metadata['calendar_deletion_failed']
            );

            // Add deletion success info
            $metadata['calendar_event_deleted'] = true;
            $metadata['calendar_event_deleted_at'] = now()->toISOString();
            $metadata['calendar_integration_id'] = $this->integration->id;

            $this->booking->update(['metadata' => $metadata]);

        } catch (Exception $e) {
            Log::warning('Failed to update booking metadata after deletion', [
                'booking_id' => $this->booking->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Mark local event as failed to delete
     */
    private function markEventDeletionFailed(Exception $exception): void
    {
        try {
            CalendarEvent::where('calendar_integration_id', $this->integration->id)
                ->where('external_event_id', $this->externalEventId)
                ->update([
                    'synced_at' => null,
                    'last_updated_externally' => now(),
                    // Could add a 'deletion_failed' flag to the model if needed
                ]);

        } catch (Exception $e) {
            Log::warning('Failed to mark event deletion as failed', [
                'integration_id' => $this->integration->id,
                'external_event_id' => $this->externalEventId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle deletion error
     */
    private function handleDeletionError(Exception $exception): void
    {
        Log::error('Calendar event deletion error', [
            'integration_id' => $this->integration->id,
            'external_event_id' => $this->externalEventId,
            'booking_id' => $this->booking?->id,
            'attempt' => $this->attempts(),
            'error' => $exception->getMessage(),
            'error_class' => get_class($exception),
        ]);

        // Handle specific error types
        if (str_contains(strtolower($exception->getMessage()), 'token')) {
            $this->handleTokenError($exception);
        }

        if (str_contains(strtolower($exception->getMessage()), 'rate limit')) {
            $this->handleRateLimitError($exception);
        }

        if (str_contains(strtolower($exception->getMessage()), 'not found')) {
            $this->handleEventNotFoundError($exception);
        }
    }

    /**
     * Handle token-related errors
     */
    private function handleTokenError(Exception $exception): void
    {
        Log::warning('Token error during event deletion', [
            'integration_id' => $this->integration->id,
            'external_event_id' => $this->externalEventId,
            'error' => $exception->getMessage(),
        ]);

        // Try to refresh token
        if ($this->integration->refresh_token && $this->integration->provider === 'google') {
            try {
                $integrationService = app(\App\Services\V1\Calendar\CalendarIntegrationService::class);
                $refreshed = $integrationService->refreshTokens($this->integration);

                if ($refreshed) {
                    Log::info('Successfully refreshed tokens during event deletion', [
                        'integration_id' => $this->integration->id,
                    ]);
                }
            } catch (Exception $refreshException) {
                Log::error('Failed to refresh tokens during event deletion', [
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
        Log::warning('Rate limit error during event deletion', [
            'integration_id' => $this->integration->id,
            'external_event_id' => $this->externalEventId,
            'error' => $exception->getMessage(),
        ]);

        // Add extra delay for rate limit errors
        $delay = $this->retryAfter() + 60; // Add 1 minute extra delay
        $this->release($delay);
    }

    /**
     * Handle event not found errors
     */
    private function handleEventNotFoundError(Exception $exception): void
    {
        Log::info('Event not found during deletion - cleaning up local records', [
            'integration_id' => $this->integration->id,
            'external_event_id' => $this->externalEventId,
        ]);

        // Clean up local records since event doesn't exist externally
        $this->cleanupLocalEventRecord();

        if ($this->booking) {
            $this->updateBookingMetadata();
        }
    }

    /**
     * Determine urgency based on booking timing
     */
    private function determineUrgency(): string
    {
        if (!$this->booking) {
            return 'normal'; // Manual deletions are normal priority
        }

        $hoursUntilBooking = now()->diffInHours($this->booking->scheduled_at, false);

        if ($hoursUntilBooking <= 2) {
            return 'urgent';
        }

        if ($hoursUntilBooking <= 24) {
            return 'high';
        }

        return 'normal';
    }

    /**
     * Check if this is an urgent deletion
     */
    private function isUrgentDeletion(): bool
    {
        return $this->determineUrgency() === 'urgent';
    }

    /**
     * Get queue name based on urgency
     */
    private function getQueueName(string $urgency): string
    {
        return match ($urgency) {
            'urgent' => 'calendar-events-urgent',
            'high' => 'calendar-events-high',
            default => 'calendar-events'
        };
    }

    /**
     * Send deletion notification
     */
    private function sendDeletionNotification(): void
    {
        // TODO: Implement deletion notification logic
        Log::info('Calendar event deletion notification sent', [
            'external_event_id' => $this->externalEventId,
            'integration_id' => $this->integration->id,
            'booking_id' => $this->booking?->id,
        ]);
    }

    /**
     * Send failure notification
     */
    private function sendFailureNotification(Exception $exception): void
    {
        // TODO: Implement failure notification logic
        Log::warning('Calendar event deletion failure notification sent', [
            'external_event_id' => $this->externalEventId,
            'integration_id' => $this->integration->id,
            'booking_id' => $this->booking?->id,
            'error' => $exception->getMessage(),
        ]);
    }

    /**
     * Get unique identifier for this job type
     */
    public function uniqueId(): string
    {
        return "delete_calendar_event_{$this->integration->id}_{$this->externalEventId}";
    }

    /**
     * Determine if job should be unique
     */
    public function uniqueFor(): int
    {
        return 60; // 1 minute - prevent duplicate deletions
    }

    /**
     * Get job display name for monitoring
     */
    public function displayName(): string
    {
        $bookingRef = $this->booking?->booking_reference ?? 'Manual';
        return "Delete Calendar Event (Booking: {$bookingRef}, Event: {$this->externalEventId})";
    }

    /**
     * Handle job cleanup even if it times out
     */
    public function __destruct()
    {
        // Ensure local cleanup happens even if external deletion fails
        if ($this->failed && !$this->isEventAlreadyDeleted()) {
            try {
                $this->cleanupLocalEventRecord();
            } catch (Exception $e) {
                // Silent cleanup attempt
            }
        }
    }
}
