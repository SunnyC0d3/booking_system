<?php

namespace App\Jobs\Calendar;

use App\Models\CalendarIntegration;
use App\Models\Booking;
use App\Models\CalendarEvent;
use App\Services\V1\Calendar\CalendarIntegrationService;
use App\Services\V1\Calendar\CalendarSyncService;
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

class UpdateCalendarEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public CalendarIntegration $integration;
    public Booking $booking;
    public string $externalEventId;
    public array $changes;
    public array $options;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 120; // 2 minutes

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public function backoff(): array
    {
        return [15, 60, 180]; // 15 seconds, 1 minute, 3 minutes
    }

    /**
     * Create a new job instance.
     */
    public function __construct(
        CalendarIntegration $integration,
        Booking $booking,
        string $externalEventId,
        array $changes = [],
        array $options = []
    ) {
        $this->integration = $integration;
        $this->booking = $booking;
        $this->externalEventId = $externalEventId;
        $this->changes = $changes;
        $this->options = $options;

        // Set queue based on urgency
        $urgency = $this->determineUrgency();
        $this->onQueue($this->getQueueName($urgency));
    }

    /**
     * Execute the job.
     */
    public function handle(CalendarSyncService $syncService): void
    {
        try {
            Log::info('Starting calendar event update', [
                'integration_id' => $this->integration->id,
                'booking_id' => $this->booking->id,
                'external_event_id' => $this->externalEventId,
                'changes' => array_keys($this->changes),
                'provider' => $this->integration->provider,
                'attempt' => $this->attempts(),
            ]);

            // Validate integration is still active
            $this->integration->refresh();

            if (!$this->integration->is_active) {
                Log::warning('Skipping event update for inactive integration', [
                    'integration_id' => $this->integration->id,
                    'booking_id' => $this->booking->id,
                ]);
                return;
            }

            // Check if booking sync is enabled
            if (!$this->integration->sync_bookings) {
                Log::info('Skipping event update - booking sync disabled', [
                    'integration_id' => $this->integration->id,
                    'booking_id' => $this->booking->id,
                ]);
                return;
            }

            // Validate booking state
            $this->validateBookingState();

            // Check if event still exists
            $eventExists = $this->verifyEventExists();
            if (!$eventExists) {
                Log::warning('Calendar event no longer exists, creating new one', [
                    'integration_id' => $this->integration->id,
                    'booking_id' => $this->booking->id,
                    'external_event_id' => $this->externalEventId,
                ]);

                // Dispatch create job instead
                CreateCalendarEvent::dispatch($this->integration, $this->booking, $this->options);
                return;
            }

            // Perform conflict check for time/date changes
            if ($this->hasTimeChanges() && ($this->options['check_conflicts'] ?? true)) {
                $this->checkForConflicts();
            }

            // Get provider service
            $providerService = $this->getProviderService();

            // Update the calendar event
            $success = $providerService->updateEvent(
                $this->integration,
                $this->booking,
                $this->externalEventId
            );

            if (!$success) {
                throw new Exception('Failed to update calendar event via provider service');
            }

            // Update local calendar event record
            $this->updateLocalEventRecord();

            Log::info('Calendar event updated successfully', [
                'integration_id' => $this->integration->id,
                'booking_id' => $this->booking->id,
                'external_event_id' => $this->externalEventId,
                'booking_reference' => $this->booking->booking_reference,
                'changes_applied' => array_keys($this->changes),
            ]);

            // Send update notification if requested
            if ($this->options['notify_update'] ?? false) {
                $this->sendUpdateNotification();
            }

        } catch (Exception $e) {
            $this->handleUpdateError($e);
            throw $e; // Re-throw to trigger retry mechanism
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Exception $exception): void
    {
        Log::error('Calendar event update failed permanently', [
            'integration_id' => $this->integration->id,
            'booking_id' => $this->booking->id,
            'external_event_id' => $this->externalEventId,
            'booking_reference' => $this->booking->booking_reference,
            'attempts' => $this->attempts(),
            'error' => $exception->getMessage(),
            'changes' => $this->changes,
        ]);

        // Update integration error count
        $this->integration->increment('sync_error_count');
        $this->integration->update([
            'last_sync_error' => "Event update failed: " . $exception->getMessage(),
        ]);

        // Mark booking for manual review if critical
        if ($this->isCriticalUpdate()) {
            $this->booking->update([
                'metadata' => array_merge($this->booking->metadata ?? [], [
                    'calendar_update_failed' => true,
                    'calendar_update_error' => $exception->getMessage(),
                    'calendar_update_failed_at' => now()->toISOString(),
                    'failed_changes' => $this->changes,
                ]),
            ]);
        }

        // Mark local event as out of sync
        $this->markEventOutOfSync();

        // Send failure notification if requested
        if ($this->options['notify_failure'] ?? true) {
            $this->sendFailureNotification($exception);
        }
    }

    /**
     * Determine if the job should be retried.
     */
    public function shouldRetry(Exception $exception): bool
    {
        // Don't retry for certain booking states
        if (in_array($this->booking->status, ['cancelled', 'completed', 'no_show'])) {
            Log::info('Not retrying event update for non-active booking', [
                'booking_id' => $this->booking->id,
                'booking_status' => $this->booking->status,
            ]);
            return false;
        }

        // Don't retry if event was deleted externally
        if (str_contains(strtolower($exception->getMessage()), 'not found') ||
            str_contains(strtolower($exception->getMessage()), 'deleted')) {
            Log::info('Not retrying event update - event not found/deleted', [
                'integration_id' => $this->integration->id,
                'booking_id' => $this->booking->id,
                'external_event_id' => $this->externalEventId,
            ]);
            return false;
        }

        // Don't retry for permanent errors
        $nonRetryableErrors = [
            'invalid_grant',
            'unauthorized',
            'forbidden',
            'calendar_not_found',
            'event_locked',
        ];

        $errorMessage = strtolower($exception->getMessage());

        foreach ($nonRetryableErrors as $nonRetryableError) {
            if (str_contains($errorMessage, $nonRetryableError)) {
                Log::info('Not retrying event update due to permanent error', [
                    'integration_id' => $this->integration->id,
                    'booking_id' => $this->booking->id,
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
            'calendar-event-update',
            'integration:' . $this->integration->id,
            'booking:' . $this->booking->id,
            'provider:' . $this->integration->provider,
            'user:' . $this->integration->user_id,
            'changes:' . implode(',', array_keys($this->changes)),
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
            // Prevent concurrent updates for same event
            new WithoutOverlapping("update_event_{$this->externalEventId}"),
        ];
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public function retryAfter(): int
    {
        $baseDelay = 30; // 30 seconds base delay
        $attempt = $this->attempts();

        // Shorter delays for urgent updates
        if ($this->isUrgentUpdate()) {
            $baseDelay = 15;
        }

        // Exponential backoff
        $delay = min($baseDelay * pow(2, $attempt - 1), 1800); // Max 30 minutes

        return $delay + rand(0, 15); // Add jitter
    }

    /**
     * Validate booking is in correct state for update
     */
    private function validateBookingState(): void
    {
        $this->booking->refresh();

        // Check booking status - allow updates for cancelled bookings to remove events
        $syncableStatuses = ['pending', 'confirmed', 'in_progress', 'cancelled'];
        if (!in_array($this->booking->status, $syncableStatuses)) {
            throw new Exception("Booking status '{$this->booking->status}' is not syncable");
        }

        // Validate booking has required data for active bookings
        if ($this->booking->status !== 'cancelled') {
            if (!$this->booking->client_name || !$this->booking->scheduled_at) {
                throw new Exception('Booking missing required data for calendar event update');
            }
        }
    }

    /**
     * Verify the event still exists in the external calendar
     */
    private function verifyEventExists(): bool
    {
        try {
            // This would need provider-specific implementation
            // For now, assume it exists and let the update call handle it
            return true;
        } catch (Exception $e) {
            Log::warning('Failed to verify event exists', [
                'integration_id' => $this->integration->id,
                'external_event_id' => $this->externalEventId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Check if this update includes time/date changes
     */
    private function hasTimeChanges(): bool
    {
        return isset($this->changes['scheduled_at']) ||
            isset($this->changes['ends_at']) ||
            isset($this->changes['duration_minutes']);
    }

    /**
     * Check for calendar conflicts with new timing
     */
    private function checkForConflicts(): void
    {
        if (!$this->integration->auto_block_external_events) {
            return; // Skip conflict checking if not enabled
        }

        try {
            $syncService = app(CalendarSyncService::class);
            $availability = $syncService->checkAvailabilityAcrossCalendars(
                $this->booking->user,
                $this->booking->service,
                $this->booking->scheduled_at,
                $this->booking->ends_at
            );

            if (!$availability['available'] && !empty($availability['conflicts'])) {
                $conflictDetails = collect($availability['conflicts'])
                    ->map(fn($conflict) => $conflict['calendar_name'])
                    ->implode(', ');

                Log::warning('Calendar conflicts detected for booking update', [
                    'booking_id' => $this->booking->id,
                    'conflicts' => $conflictDetails,
                ]);

                // Don't throw exception unless strict conflict checking is enabled
                if ($this->options['strict_conflicts'] ?? false) {
                    throw new Exception("Calendar conflicts detected for update: {$conflictDetails}");
                }
            }
        } catch (Exception $e) {
            Log::warning('Failed to check calendar conflicts for update', [
                'booking_id' => $this->booking->id,
                'error' => $e->getMessage(),
            ]);
            // Continue with event update even if conflict check fails
        }
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
     * Update the local calendar event record
     */
    private function updateLocalEventRecord(): void
    {
        $calendarEvent = CalendarEvent::where('calendar_integration_id', $this->integration->id)
            ->where('external_event_id', $this->externalEventId)
            ->first();

        if ($calendarEvent) {
            $updateData = [
                'title' => $this->integration->getCalendarEventTitle($this->booking),
                'starts_at' => $this->booking->scheduled_at,
                'ends_at' => $this->booking->ends_at,
                'synced_at' => now(),
                'last_updated_externally' => now(),
            ];

            $calendarEvent->update($updateData);

            Log::info('Local calendar event record updated', [
                'calendar_event_id' => $calendarEvent->id,
                'booking_id' => $this->booking->id,
            ]);
        }
    }

    /**
     * Mark event as out of sync
     */
    private function markEventOutOfSync(): void
    {
        $calendarEvent = CalendarEvent::where('calendar_integration_id', $this->integration->id)
            ->where('external_event_id', $this->externalEventId)
            ->first();

        if ($calendarEvent) {
            $calendarEvent->update([
                'synced_at' => null,
                'last_updated_externally' => now(),
            ]);
        }
    }

    /**
     * Handle update error
     */
    private function handleUpdateError(Exception $exception): void
    {
        Log::error('Calendar event update error', [
            'integration_id' => $this->integration->id,
            'booking_id' => $this->booking->id,
            'external_event_id' => $this->externalEventId,
            'attempt' => $this->attempts(),
            'error' => $exception->getMessage(),
            'error_class' => get_class($exception),
            'changes' => $this->changes,
        ]);

        // Handle specific error types
        if (str_contains(strtolower($exception->getMessage()), 'token')) {
            $this->handleTokenError($exception);
        }

        if (str_contains(strtolower($exception->getMessage()), 'rate limit')) {
            $this->handleRateLimitError($exception);
        }

        if (str_contains(strtolower($exception->getMessage()), 'conflict')) {
            $this->handleConflictError($exception);
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
        Log::warning('Token error during event update', [
            'integration_id' => $this->integration->id,
            'booking_id' => $this->booking->id,
            'error' => $exception->getMessage(),
        ]);

        // Try to refresh token
        if ($this->integration->refresh_token && $this->integration->provider === 'google') {
            try {
                $integrationService = app(CalendarIntegrationService::class);
                $refreshed = $integrationService->refreshTokens($this->integration);

                if ($refreshed) {
                    Log::info('Successfully refreshed tokens during event update', [
                        'integration_id' => $this->integration->id,
                    ]);
                }
            } catch (Exception $refreshException) {
                Log::error('Failed to refresh tokens during event update', [
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
        Log::warning('Rate limit error during event update', [
            'integration_id' => $this->integration->id,
            'booking_id' => $this->booking->id,
            'error' => $exception->getMessage(),
        ]);

        // Add extra delay for rate limit errors
        $delay = $this->retryAfter() + 120; // Add 2 minutes extra delay
        $this->release($delay);
    }

    /**
     * Handle conflict errors
     */
    private function handleConflictError(Exception $exception): void
    {
        Log::warning('Conflict error during event update', [
            'integration_id' => $this->integration->id,
            'booking_id' => $this->booking->id,
            'error' => $exception->getMessage(),
        ]);

        // Mark booking for review
        $this->booking->update([
            'metadata' => array_merge($this->booking->metadata ?? [], [
                'calendar_update_conflict' => true,
                'calendar_conflict_details' => $exception->getMessage(),
                'calendar_conflict_detected_at' => now()->toISOString(),
            ]),
        ]);
    }

    /**
     * Handle event not found errors
     */
    private function handleEventNotFoundError(Exception $exception): void
    {
        Log::warning('Event not found during update, will create new one', [
            'integration_id' => $this->integration->id,
            'booking_id' => $this->booking->id,
            'external_event_id' => $this->externalEventId,
        ]);

        // Clean up local reference to deleted event
        CalendarEvent::where('calendar_integration_id', $this->integration->id)
            ->where('external_event_id', $this->externalEventId)
            ->delete();

        // Dispatch create job for new event
        CreateCalendarEvent::dispatch($this->integration, $this->booking, $this->options);
    }

    /**
     * Determine urgency based on booking timing and change type
     */
    private function determineUrgency(): string
    {
        $hoursUntilBooking = now()->diffInHours($this->booking->scheduled_at, false);

        // Time changes are more urgent
        if ($this->hasTimeChanges()) {
            if ($hoursUntilBooking <= 4) {
                return 'urgent';
            }
            if ($hoursUntilBooking <= 24) {
                return 'high';
            }
        }

        if ($hoursUntilBooking <= 2) {
            return 'urgent';
        }

        if ($hoursUntilBooking <= 24) {
            return 'high';
        }

        return 'normal';
    }

    /**
     * Check if this is an urgent update
     */
    private function isUrgentUpdate(): bool
    {
        return $this->determineUrgency() === 'urgent';
    }

    /**
     * Check if this is a critical update
     */
    private function isCriticalUpdate(): bool
    {
        return $this->hasTimeChanges() ||
            $this->booking->total_amount > 100000 || // High value bookings (£1000+)
            in_array($this->determineUrgency(), ['urgent', 'high']);
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
     * Send update notification
     */
    private function sendUpdateNotification(): void
    {
        // TODO: Implement update notification logic
        Log::info('Calendar event update notification sent', [
            'booking_id' => $this->booking->id,
            'integration_id' => $this->integration->id,
            'changes' => array_keys($this->changes),
        ]);
    }

    /**
     * Send failure notification
     */
    private function sendFailureNotification(Exception $exception): void
    {
        // TODO: Implement failure notification logic
        Log::warning('Calendar event update failure notification sent', [
            'booking_id' => $this->booking->id,
            'integration_id' => $this->integration->id,
            'error' => $exception->getMessage(),
        ]);
    }

    /**
     * Get unique identifier for this job type
     */
    public function uniqueId(): string
    {
        return "update_calendar_event_{$this->integration->id}_{$this->externalEventId}";
    }

    /**
     * Determine if job should be unique
     */
    public function uniqueFor(): int
    {
        return 180; // 3 minutes - prevent duplicate updates
    }

    /**
     * Get job display name for monitoring
     */
    public function displayName(): string
    {
        return "Update Calendar Event (Booking: {$this->booking->booking_reference}, Event: {$this->externalEventId})";
    }
}
