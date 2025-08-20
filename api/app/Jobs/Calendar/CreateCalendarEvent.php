<?php

namespace App\Jobs\Calendar;

use App\Models\CalendarIntegration;
use App\Models\Booking;
use App\Models\ConsultationBooking;
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

class CreateCalendarEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public CalendarIntegration $integration;
    public Booking $booking;
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
    public function __construct(CalendarIntegration $integration, Booking $booking, array $options = [])
    {
        $this->integration = $integration;
        $this->booking = $booking;
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
            Log::info('Starting calendar event creation', [
                'integration_id' => $this->integration->id,
                'booking_id' => $this->booking->id,
                'provider' => $this->integration->provider,
                'attempt' => $this->attempts(),
            ]);

            // Validate integration is still active
            $this->integration->refresh();

            if (!$this->integration->is_active) {
                Log::warning('Skipping event creation for inactive integration', [
                    'integration_id' => $this->integration->id,
                    'booking_id' => $this->booking->id,
                ]);
                return;
            }

            // Check if booking sync is enabled
            if (!$this->integration->sync_bookings) {
                Log::info('Skipping event creation - booking sync disabled', [
                    'integration_id' => $this->integration->id,
                    'booking_id' => $this->booking->id,
                ]);
                return;
            }

            // Validate booking is in syncable state
            $this->validateBookingState();

            // Check for existing event
            $existingEvent = $this->checkForExistingEvent();
            if ($existingEvent) {
                Log::info('Calendar event already exists for booking', [
                    'integration_id' => $this->integration->id,
                    'booking_id' => $this->booking->id,
                    'existing_event_id' => $existingEvent,
                ]);
                return;
            }

            // Perform conflict check if enabled
            if ($this->options['check_conflicts'] ?? true) {
                $this->checkForConflicts();
            }

            // Create the calendar event
            $success = $syncService->syncBookingToIntegration($this->booking, $this->integration);

            if (!$success) {
                throw new Exception('Failed to create calendar event via sync service');
            }

            Log::info('Calendar event created successfully', [
                'integration_id' => $this->integration->id,
                'booking_id' => $this->booking->id,
                'booking_reference' => $this->booking->booking_reference,
                'scheduled_at' => $this->booking->scheduled_at->toISOString(),
            ]);

            // Send success notification if requested
            if ($this->options['notify_success'] ?? false) {
                $this->sendSuccessNotification();
            }

        } catch (Exception $e) {
            $this->handleCreationError($e);
            throw $e; // Re-throw to trigger retry mechanism
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Exception $exception): void
    {
        Log::error('Calendar event creation failed permanently', [
            'integration_id' => $this->integration->id,
            'booking_id' => $this->booking->id,
            'booking_reference' => $this->booking->booking_reference,
            'attempts' => $this->attempts(),
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        // Update integration error count
        $this->integration->increment('sync_error_count');
        $this->integration->update([
            'last_sync_error' => "Event creation failed: " . $exception->getMessage(),
        ]);

        // Mark booking for manual review if critical
        if ($this->isCriticalBooking()) {
            $this->booking->update([
                'metadata' => array_merge($this->booking->metadata ?? [], [
                    'calendar_sync_failed' => true,
                    'calendar_sync_error' => $exception->getMessage(),
                    'calendar_sync_failed_at' => now()->toISOString(),
                ]),
            ]);
        }

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
            Log::info('Not retrying event creation for non-active booking', [
                'booking_id' => $this->booking->id,
                'booking_status' => $this->booking->status,
            ]);
            return false;
        }

        // Don't retry for permanent calendar errors
        $nonRetryableErrors = [
            'invalid_grant',
            'unauthorized',
            'forbidden',
            'calendar_not_found',
            'event_already_exists',
        ];

        $errorMessage = strtolower($exception->getMessage());

        foreach ($nonRetryableErrors as $nonRetryableError) {
            if (str_contains($errorMessage, $nonRetryableError)) {
                Log::info('Not retrying event creation due to permanent error', [
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
            'calendar-event-creation',
            'integration:' . $this->integration->id,
            'booking:' . $this->booking->id,
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
            new \App\Jobs\Middleware\RateLimitCalendarApi($this->integration->provider),
            // Prevent concurrent event creation for same booking
            new \App\Jobs\Middleware\WithoutOverlapping("create_event_{$this->booking->id}"),
        ];
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public function retryAfter(): int
    {
        $baseDelay = 30; // 30 seconds base delay
        $attempt = $this->attempts();

        // Shorter delays for urgent bookings
        if ($this->isUrgentBooking()) {
            $baseDelay = 15;
        }

        // Exponential backoff
        $delay = min($baseDelay * pow(2, $attempt - 1), 1800); // Max 30 minutes

        return $delay + rand(0, 15); // Add jitter
    }

    /**
     * Validate booking is in correct state for sync
     */
    private function validateBookingState(): void
    {
        $this->booking->refresh();

        // Check booking status
        $syncableStatuses = ['pending', 'confirmed', 'in_progress'];
        if (!in_array($this->booking->status, $syncableStatuses)) {
            throw new Exception("Booking status '{$this->booking->status}' is not syncable");
        }

        // Check booking is in the future (with small buffer for current bookings)
        if ($this->booking->scheduled_at->lt(now()->subMinutes(30))) {
            throw new Exception('Cannot create calendar event for past booking');
        }

        // Validate booking has required data
        if (!$this->booking->client_name || !$this->booking->scheduled_at) {
            throw new Exception('Booking missing required data for calendar event');
        }
    }

    /**
     * Check if event already exists for this booking
     */
    private function checkForExistingEvent(): ?string
    {
        $existingEvent = $this->booking->calendar_events()
            ->where('calendar_integration_id', $this->integration->id)
            ->first();

        return $existingEvent?->external_event_id;
    }

    /**
     * Check for calendar conflicts
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

                Log::warning('Calendar conflicts detected for booking', [
                    'booking_id' => $this->booking->id,
                    'conflicts' => $conflictDetails,
                ]);

                // Don't throw exception unless strict conflict checking is enabled
                if ($this->options['strict_conflicts'] ?? false) {
                    throw new Exception("Calendar conflicts detected: {$conflictDetails}");
                }
            }
        } catch (Exception $e) {
            Log::warning('Failed to check calendar conflicts', [
                'booking_id' => $this->booking->id,
                'error' => $e->getMessage(),
            ]);
            // Continue with event creation even if conflict check fails
        }
    }

    /**
     * Handle creation error
     */
    private function handleCreationError(Exception $exception): void
    {
        Log::error('Calendar event creation error', [
            'integration_id' => $this->integration->id,
            'booking_id' => $this->booking->id,
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

        if (str_contains(strtolower($exception->getMessage()), 'conflict')) {
            $this->handleConflictError($exception);
        }
    }

    /**
     * Handle token-related errors
     */
    private function handleTokenError(Exception $exception): void
    {
        Log::warning('Token error during event creation', [
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
                    Log::info('Successfully refreshed tokens during event creation', [
                        'integration_id' => $this->integration->id,
                    ]);
                }
            } catch (Exception $refreshException) {
                Log::error('Failed to refresh tokens during event creation', [
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
        Log::warning('Rate limit error during event creation', [
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
        Log::warning('Conflict error during event creation', [
            'integration_id' => $this->integration->id,
            'booking_id' => $this->booking->id,
            'error' => $exception->getMessage(),
        ]);

        // Mark booking for review
        $this->booking->update([
            'metadata' => array_merge($this->booking->metadata ?? [], [
                'calendar_conflict_detected' => true,
                'calendar_conflict_details' => $exception->getMessage(),
                'calendar_conflict_detected_at' => now()->toISOString(),
            ]),
        ]);
    }

    /**
     * Determine urgency based on booking timing
     */
    private function determineUrgency(): string
    {
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
     * Check if this is an urgent booking
     */
    private function isUrgentBooking(): bool
    {
        return $this->determineUrgency() === 'urgent';
    }

    /**
     * Check if this is a critical booking
     */
    private function isCriticalBooking(): bool
    {
        return $this->booking->total_amount > 100000 || // High value bookings (£1000+)
            $this->booking->requires_consultation ||
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
     * Send success notification
     */
    private function sendSuccessNotification(): void
    {
        // TODO: Implement success notification logic
        Log::info('Calendar event creation success notification sent', [
            'booking_id' => $this->booking->id,
            'integration_id' => $this->integration->id,
        ]);
    }

    /**
     * Send failure notification
     */
    private function sendFailureNotification(Exception $exception): void
    {
        // TODO: Implement failure notification logic
        Log::warning('Calendar event creation failure notification sent', [
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
        return "create_calendar_event_{$this->integration->id}_{$this->booking->id}";
    }

    /**
     * Determine if job should be unique
     */
    public function uniqueFor(): int
    {
        return 300; // 5 minutes - prevent duplicate event creation
    }

    /**
     * Get job display name for monitoring
     */
    public function displayName(): string
    {
        return "Create Calendar Event (Booking: {$this->booking->booking_reference}, Integration: {$this->integration->id})";
    }
}
