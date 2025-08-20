<?php

namespace App\Jobs\Calendar;

use App\Constants\CalendarProviders;
use App\Models\CalendarIntegration;
use App\Models\CalendarEvent;
use App\Models\CalendarSyncJob;
use App\Models\Booking;
use App\Services\V1\Calendar\CalendarSyncService;
use App\Services\V1\Calendar\GoogleCalendarService;
use App\Services\V1\Calendar\CalendarEventService;
use App\Services\V1\Bookings\BookingService;
use App\Traits\V1\ApiResponses;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class ProcessCalendarWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, ApiResponses;

    public int $timeout = 300; // 5 minutes
    public int $tries = 3;
    public bool $deleteWhenMissingModels = true;

    private CalendarIntegration $integration;
    private array $webhookData;
    private string $webhookSignature;
    private array $options;
    private ?CalendarSyncJob $syncJob = null;

    /**
     * Create a new job instance.
     */
    public function __construct(
        CalendarIntegration $integration,
        array $webhookData,
        string $webhookSignature = '',
        array $options = []
    ) {
        $this->integration = $integration;
        $this->webhookData = $webhookData;
        $this->webhookSignature = $webhookSignature;
        $this->options = $options;

        // Set queue based on priority
        $priority = $options['priority'] ?? 'normal';
        $this->onQueue($this->getQueueName($priority));

        // Set delay for batch processing if specified
        if (isset($options['delay_seconds'])) {
            $this->delay(now()->addSeconds($options['delay_seconds']));
        }
    }

    /**
     * Execute the job.
     */
    public function handle(
        CalendarSyncService $syncService,
        CalendarEventService $eventService,
        BookingService $bookingService
    ): void {
        try {
            Log::info('Processing calendar webhook', [
                'integration_id' => $this->integration->id,
                'provider' => $this->integration->provider,
                'webhook_type' => $this->getWebhookType(),
                'data_size' => count($this->webhookData),
                'attempt' => $this->attempts(),
            ]);

            // Create sync job record for tracking
            $this->syncJob = $this->createSyncJobRecord();

            // Validate integration is still active and accessible
            $this->validateIntegration();

            // Verify webhook signature and authenticity
            $this->verifyWebhookSignature();

            // Parse and validate webhook data
            $parsedData = $this->parseWebhookData();

            // Check for duplicate webhook processing
            if ($this->isDuplicateWebhook($parsedData)) {
                Log::info('Skipping duplicate webhook', [
                    'integration_id' => $this->integration->id,
                    'webhook_id' => $parsedData['webhook_id'] ?? 'unknown',
                ]);
                $this->markJobCompleted('duplicate_skipped');
                return;
            }

            // Process webhook data based on type and provider
            $result = $this->processWebhookData($parsedData, $syncService, $eventService, $bookingService);

            // Handle any conflicts detected during processing
            if (!empty($result['conflicts'])) {
                $this->handleConflicts($result['conflicts'], $bookingService);
            }

            // Update integration sync status
            $this->updateIntegrationStatus($result);

            // Send notifications if configured
            $this->sendNotifications($result);

            // Mark job as completed
            $this->markJobCompleted('success', $result);

            Log::info('Calendar webhook processed successfully', [
                'integration_id' => $this->integration->id,
                'events_processed' => $result['events_processed'] ?? 0,
                'conflicts_detected' => count($result['conflicts'] ?? []),
                'duration_seconds' => $this->getJobDuration(),
            ]);

        } catch (Exception $e) {
            $this->handleWebhookError($e);
        }
    }

    /**
     * Validate integration status and access
     */
    private function validateIntegration(): void
    {
        // Refresh integration from database
        $this->integration->refresh();

        if (!$this->integration->is_active) {
            throw new Exception('Calendar integration is disabled', 422);
        }

        if (!$this->integration->sync_availability && !$this->integration->sync_bookings) {
            throw new Exception('No sync settings enabled for webhook processing', 422);
        }

        // Check if integration has valid tokens (for OAuth providers)
        if ($this->integration->provider !== CalendarProviders::ICAL) {
            if (empty($this->integration->access_token)) {
                throw new Exception('Integration missing access token', 401);
            }

            if ($this->integration->token_expires_at && $this->integration->token_expires_at->isPast()) {
                throw new Exception('Integration access token expired', 401);
            }
        }
    }

    /**
     * Verify webhook signature for security
     */
    private function verifyWebhookSignature(): void
    {
        if (empty($this->webhookSignature)) {
            Log::warning('Webhook received without signature', [
                'integration_id' => $this->integration->id,
                'provider' => $this->integration->provider,
            ]);
            return; // Some providers don't send signatures
        }

        $isValid = match ($this->integration->provider) {
            CalendarProviders::GOOGLE => $this->verifyGoogleWebhookSignature(),
            CalendarProviders::OUTLOOK => $this->verifyOutlookWebhookSignature(),
            default => true // Skip verification for providers without signature support
        };

        if (!$isValid) {
            throw new Exception('Invalid webhook signature', 401);
        }
    }

    /**
     * Verify Google Calendar webhook signature
     */
    private function verifyGoogleWebhookSignature(): bool
    {
        // Google uses X-Goog-Channel-Token for verification
        $expectedToken = $this->integration->sync_settings_display['webhook_token'] ?? null;
        $receivedToken = $this->webhookData['headers']['X-Goog-Channel-Token'] ?? null;

        if (!$expectedToken || !$receivedToken) {
            return false;
        }

        return hash_equals($expectedToken, $receivedToken);
    }

    /**
     * Verify Outlook webhook signature
     */
    private function verifyOutlookWebhookSignature(): bool
    {
        // Outlook uses subscription validation
        $clientState = $this->webhookData['headers']['ClientState'] ?? null;
        $expectedState = $this->integration->sync_settings_display['client_state'] ?? null;

        if (!$expectedState || !$clientState) {
            return false;
        }

        return hash_equals($expectedState, $clientState);
    }

    /**
     * Parse webhook data based on provider
     */
    private function parseWebhookData(): array
    {
        return match ($this->integration->provider) {
            CalendarProviders::GOOGLE => $this->parseGoogleWebhookData(),
            CalendarProviders::OUTLOOK => $this->parseOutlookWebhookData(),
            default => $this->parseGenericWebhookData()
        };
    }

    /**
     * Parse Google Calendar webhook data
     */
    private function parseGoogleWebhookData(): array
    {
        $data = [
            'provider' => CalendarProviders::GOOGLE,
            'webhook_id' => $this->webhookData['headers']['X-Goog-Message-Number'] ?? uniqid(),
            'resource_id' => $this->webhookData['headers']['X-Goog-Resource-ID'] ?? null,
            'resource_uri' => $this->webhookData['headers']['X-Goog-Resource-URI'] ?? null,
            'resource_state' => $this->webhookData['headers']['X-Goog-Resource-State'] ?? 'exists',
            'changed_events' => [],
            'sync_token' => null,
        ];

        // Google webhooks don't contain event data, just notifications of changes
        // We need to fetch the actual changes using the Events API
        if ($data['resource_state'] === 'exists') {
            $data['requires_fetch'] = true;
            $data['fetch_url'] = $data['resource_uri'];
        }

        return $data;
    }

    /**
     * Parse Outlook webhook data
     */
    private function parseOutlookWebhookData(): array
    {
        $payload = $this->webhookData['body'] ?? [];
        $value = $payload['value'] ?? [];

        $data = [
            'provider' => CalendarProviders::OUTLOOK,
            'webhook_id' => $payload['@odata.id'] ?? uniqid(),
            'changed_events' => [],
            'requires_fetch' => false,
        ];

        // Parse individual change notifications
        foreach ($value as $notification) {
            $data['changed_events'][] = [
                'id' => $notification['resourceData']['id'] ?? null,
                'change_type' => $notification['changeType'] ?? 'updated',
                'resource' => $notification['resource'] ?? null,
                'odata_type' => $notification['resourceData']['@odata.type'] ?? null,
            ];
        }

        return $data;
    }

    /**
     * Parse generic webhook data
     */
    private function parseGenericWebhookData(): array
    {
        return [
            'provider' => $this->integration->provider,
            'webhook_id' => $this->webhookData['id'] ?? uniqid(),
            'changed_events' => $this->webhookData['events'] ?? [],
            'requires_fetch' => false,
        ];
    }

    /**
     * Check if this webhook has already been processed
     */
    private function isDuplicateWebhook(array $parsedData): bool
    {
        $webhookId = $parsedData['webhook_id'];
        $recentCutoff = now()->subHours(24);

        return CalendarSyncJob::where('calendar_integration_id', $this->integration->id)
            ->where('job_type', 'webhook_sync')
            ->where('created_at', '>=', $recentCutoff)
            ->whereJsonContains('job_data->webhook_id', $webhookId)
            ->exists();
    }

    /**
     * Process webhook data based on type and provider
     */
    private function processWebhookData(
        array $parsedData,
        CalendarSyncService $syncService,
        CalendarEventService $eventService,
        BookingService $bookingService
    ): array {
        $result = [
            'events_processed' => 0,
            'events_created' => 0,
            'events_updated' => 0,
            'events_deleted' => 0,
            'conflicts' => [],
            'errors' => [],
        ];

        return DB::transaction(function () use ($parsedData, $syncService, $eventService, $bookingService, $result) {
            if ($parsedData['requires_fetch'] ?? false) {
                // Fetch actual event changes from provider API
                $changes = $this->fetchEventChanges($parsedData);
                $result = $this->processEventChanges($changes, $eventService, $bookingService, $result);
            } else {
                // Process events directly from webhook data
                $result = $this->processEventChanges($parsedData['changed_events'], $eventService, $bookingService, $result);
            }

            // Update sync job with progress
            if ($this->syncJob) {
                $this->syncJob->update([
                    'events_processed' => $result['events_processed'],
                    'job_data' => array_merge($this->syncJob->job_data ?? [], [
                        'events_created' => $result['events_created'],
                        'events_updated' => $result['events_updated'],
                        'events_deleted' => $result['events_deleted'],
                        'conflicts_detected' => count($result['conflicts']),
                    ]),
                ]);
            }

            return $result;
        });
    }

    /**
     * Fetch actual event changes from provider API
     */
    private function fetchEventChanges(array $parsedData): array
    {
        switch ($this->integration->provider) {
            case CalendarProviders::GOOGLE:
                return $this->fetchGoogleEventChanges($parsedData);

            case CalendarProviders::OUTLOOK:
                return $this->fetchOutlookEventChanges($parsedData);

            default:
                return [];
        }
    }

    /**
     * Fetch Google Calendar event changes
     */
    private function fetchGoogleEventChanges(array $parsedData): array
    {
        try {
            $googleService = app(GoogleCalendarService::class);

            // Use sync token for incremental sync if available
            $syncToken = $this->integration->sync_settings_display['sync_token'] ?? null;

            $events = $googleService->getEventChanges(
                $this->integration,
                $syncToken,
                [
                    'max_results' => 100,
                    'show_deleted' => true,
                ]
            );

            // Update sync token for next incremental sync
            if (isset($events['next_sync_token'])) {
                $this->updateSyncToken($events['next_sync_token']);
            }

            return $events['items'] ?? [];

        } catch (Exception $e) {
            Log::error('Failed to fetch Google event changes', [
                'integration_id' => $this->integration->id,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Fetch Outlook event changes
     */
    private function fetchOutlookEventChanges(array $parsedData): array
    {
        // Outlook webhooks already contain the change data
        return $parsedData['changed_events'] ?? [];
    }

    /**
     * Process individual event changes
     */
    private function processEventChanges(
        array $changes,
        CalendarEventService $eventService,
        BookingService $bookingService,
        array $result
    ): array {
        foreach ($changes as $change) {
            try {
                $processResult = $this->processEventChange($change, $eventService, $bookingService);

                $result['events_processed']++;
                $result['events_' . $processResult['action']]++;

                if (!empty($processResult['conflicts'])) {
                    $result['conflicts'] = array_merge($result['conflicts'], $processResult['conflicts']);
                }

            } catch (Exception $e) {
                Log::error('Failed to process event change', [
                    'integration_id' => $this->integration->id,
                    'event_id' => $change['id'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ]);

                $result['errors'][] = [
                    'event_id' => $change['id'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $result;
    }

    /**
     * Process individual event change
     */
    private function processEventChange(
        array $change,
        CalendarEventService $eventService,
        BookingService $bookingService
    ): array {
        $eventId = $change['id'] ?? $change['external_event_id'] ?? null;
        $changeType = $change['change_type'] ?? $change['status'] ?? 'updated';

        if (!$eventId) {
            throw new Exception('Event ID missing from webhook data');
        }

        // Find existing calendar event
        $existingEvent = CalendarEvent::where('calendar_integration_id', $this->integration->id)
            ->where('external_event_id', $eventId)
            ->first();

        switch ($changeType) {
            case 'created':
            case 'updated':
                return $this->processEventCreateOrUpdate($change, $existingEvent, $eventService, $bookingService);

            case 'deleted':
            case 'cancelled':
                return $this->processEventDelete($existingEvent, $bookingService);

            default:
                Log::warning('Unknown event change type', [
                    'integration_id' => $this->integration->id,
                    'event_id' => $eventId,
                    'change_type' => $changeType,
                ]);

                return ['action' => 'skipped', 'conflicts' => []];
        }
    }

    /**
     * Process event creation or update
     */
    private function processEventCreateOrUpdate(
        array $change,
        ?CalendarEvent $existingEvent,
        CalendarEventService $eventService,
        BookingService $bookingService
    ): array {
        $conflicts = [];

        // Normalize event data
        $eventData = $this->normalizeEventData($change);

        if ($existingEvent) {
            // Update existing event
            $existingEvent->update([
                'title' => $eventData['title'],
                'description' => $eventData['description'],
                'starts_at' => $eventData['starts_at'],
                'ends_at' => $eventData['ends_at'],
                'is_all_day' => $eventData['is_all_day'],
                'blocks_booking' => $eventData['blocks_booking'],
                'last_updated_externally' => now(),
                'synced_at' => now(),
            ]);

            $action = 'updated';
        } else {
            // Create new event
            $existingEvent = CalendarEvent::create([
                'calendar_integration_id' => $this->integration->id,
                'external_event_id' => $eventData['id'],
                'title' => $eventData['title'],
                'description' => $eventData['description'],
                'starts_at' => $eventData['starts_at'],
                'ends_at' => $eventData['ends_at'],
                'is_all_day' => $eventData['is_all_day'],
                'blocks_booking' => $eventData['blocks_booking'],
                'synced_at' => now(),
            ]);

            $action = 'created';
        }

        // Check for booking conflicts if this event blocks booking slots
        if ($eventData['blocks_booking'] && $this->integration->auto_block_external_events) {
            $conflicts = $this->checkBookingConflicts($existingEvent, $bookingService);
        }

        return [
            'action' => $action,
            'event' => $existingEvent,
            'conflicts' => $conflicts,
        ];
    }

    /**
     * Process event deletion
     */
    private function processEventDelete(?CalendarEvent $existingEvent, BookingService $bookingService): array
    {
        if (!$existingEvent) {
            return ['action' => 'skipped', 'conflicts' => []];
        }

        // Check if deleting this event resolves any booking conflicts
        $resolvedConflicts = $this->checkResolvedConflicts($existingEvent, $bookingService);

        $existingEvent->delete();

        return [
            'action' => 'deleted',
            'resolved_conflicts' => $resolvedConflicts,
            'conflicts' => [],
        ];
    }

    /**
     * Normalize event data from different providers
     */
    private function normalizeEventData(array $change): array
    {
        $startTime = null;
        $endTime = null;
        $isAllDay = false;

        // Parse timing based on provider format
        if (isset($change['start'])) {
            if (isset($change['start']['dateTime'])) {
                $startTime = Carbon::parse($change['start']['dateTime']);
            } elseif (isset($change['start']['date'])) {
                $startTime = Carbon::parse($change['start']['date'])->startOfDay();
                $isAllDay = true;
            }
        }

        if (isset($change['end'])) {
            if (isset($change['end']['dateTime'])) {
                $endTime = Carbon::parse($change['end']['dateTime']);
            } elseif (isset($change['end']['date'])) {
                $endTime = Carbon::parse($change['end']['date'])->endOfDay();
                $isAllDay = true;
            }
        }

        // Default end time if not provided
        if (!$endTime && $startTime) {
            $endTime = $isAllDay ? $startTime->copy()->endOfDay() : $startTime->copy()->addHour();
        }

        return [
            'id' => $change['id'] ?? $change['external_event_id'],
            'title' => $change['summary'] ?? $change['subject'] ?? $change['title'] ?? 'Untitled Event',
            'description' => $change['description'] ?? $change['body']['content'] ?? null,
            'starts_at' => $startTime,
            'ends_at' => $endTime,
            'is_all_day' => $isAllDay,
            'blocks_booking' => true, // Default to blocking unless specified otherwise
        ];
    }

    /**
     * Check for booking conflicts with the event
     */
    private function checkBookingConflicts(CalendarEvent $event, BookingService $bookingService): array
    {
        $conflicts = [];

        // Find overlapping bookings
        $overlappingBookings = Booking::where('service_id', $this->integration->service_id)
            ->where('status', '!=', 'cancelled')
            ->where(function ($query) use ($event) {
                $query->where(function ($q) use ($event) {
                    $q->where('scheduled_at', '<', $event->ends_at)
                        ->where('ends_at', '>', $event->starts_at);
                });
            })
            ->get();

        foreach ($overlappingBookings as $booking) {
            $conflicts[] = [
                'type' => 'booking_overlap',
                'booking_id' => $booking->id,
                'booking_reference' => $booking->booking_reference,
                'event_id' => $event->id,
                'severity' => $this->calculateConflictSeverity($booking, $event),
                'resolution_options' => $this->getConflictResolutionOptions($booking, $event),
            ];
        }

        return $conflicts;
    }

    /**
     * Check for resolved conflicts when an event is deleted
     */
    private function checkResolvedConflicts(CalendarEvent $event, BookingService $bookingService): array
    {
        // Implementation would check if any previously blocked bookings can now proceed
        return [];
    }

    /**
     * Calculate conflict severity
     */
    private function calculateConflictSeverity(Booking $booking, CalendarEvent $event): string
    {
        $overlapMinutes = min($booking->ends_at, $event->ends_at)
            ->diffInMinutes(max($booking->scheduled_at, $event->starts_at));

        if ($overlapMinutes >= 60) {
            return 'high';
        } elseif ($overlapMinutes >= 30) {
            return 'medium';
        } else {
            return 'low';
        }
    }

    /**
     * Get conflict resolution options
     */
    private function getConflictResolutionOptions(Booking $booking, CalendarEvent $event): array
    {
        return [
            'reschedule_booking',
            'ignore_conflict',
            'cancel_booking',
            'modify_event_blocking',
        ];
    }

    /**
     * Handle detected conflicts
     */
    private function handleConflicts(array $conflicts, BookingService $bookingService): void
    {
        foreach ($conflicts as $conflict) {
            Log::warning('Calendar webhook detected booking conflict', [
                'integration_id' => $this->integration->id,
                'conflict_type' => $conflict['type'],
                'booking_id' => $conflict['booking_id'] ?? null,
                'event_id' => $conflict['event_id'] ?? null,
                'severity' => $conflict['severity'] ?? 'unknown',
            ]);

            // Apply automatic conflict resolution if configured
            $this->applyConflictResolution($conflict, $bookingService);
        }
    }

    /**
     * Apply automatic conflict resolution
     */
    private function applyConflictResolution(array $conflict, BookingService $bookingService): void
    {
        $resolutionStrategy = $this->integration->sync_settings_display['conflict_resolution'] ?? 'manual';

        switch ($resolutionStrategy) {
            case 'cancel_booking':
                $this->cancelConflictingBooking($conflict, $bookingService);
                break;

            case 'ignore_conflict':
                // Do nothing, let both coexist
                break;

            case 'notify_only':
                $this->sendConflictNotification($conflict);
                break;

            default:
                // Manual resolution required
                $this->flagForManualResolution($conflict);
                break;
        }
    }

    /**
     * Cancel conflicting booking
     */
    private function cancelConflictingBooking(array $conflict, BookingService $bookingService): void
    {
        if (!isset($conflict['booking_id'])) {
            return;
        }

        $booking = Booking::find($conflict['booking_id']);
        if (!$booking) {
            return;
        }

        try {
            // Cancel the booking with webhook conflict reason
            $bookingService->cancelBooking($booking, [
                'reason' => 'Calendar conflict detected via webhook',
                'auto_cancelled' => true,
                'conflict_event_id' => $conflict['event_id'] ?? null,
            ]);

            Log::info('Booking automatically cancelled due to calendar conflict', [
                'booking_id' => $booking->id,
                'booking_reference' => $booking->booking_reference,
                'integration_id' => $this->integration->id,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to auto-cancel conflicting booking', [
                'booking_id' => $booking->id,
                'integration_id' => $this->integration->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send conflict notification
     */
    private function sendConflictNotification(array $conflict): void
    {
        // Implementation would send notification to relevant parties
        Log::info('Conflict notification sent', [
            'integration_id' => $this->integration->id,
            'conflict' => $conflict,
        ]);
    }

    /**
     * Flag conflict for manual resolution
     */
    private function flagForManualResolution(array $conflict): void
    {
        // Implementation would create a conflict record for admin review
        Log::info('Conflict flagged for manual resolution', [
            'integration_id' => $this->integration->id,
            'conflict' => $conflict,
        ]);
    }

    /**
     * Update integration sync status
     */
    private function updateIntegrationStatus(array $result): void
    {
        $this->integration->update([
            'last_sync_at' => now(),
            'sync_error_count' => 0, // Reset error count on successful webhook processing
            'last_sync_error' => null,
        ]);
    }

    /**
     * Send notifications if configured
     */
    private function sendNotifications(array $result): void
    {
        $notificationSettings = $this->integration->sync_settings_display['notification_preferences'] ?? [];

        if ($notificationSettings['notify_on_conflicts'] ?? false) {
            if (!empty($result['conflicts'])) {
                // Send conflict notification
                Log::info('Conflict notification triggered by webhook', [
                    'integration_id' => $this->integration->id,
                    'conflicts_count' => count($result['conflicts']),
                ]);
            }
        }

        if ($notificationSettings['notify_on_webhook_processing'] ?? false) {
            // Send webhook processing notification
            Log::info('Webhook processing notification sent', [
                'integration_id' => $this->integration->id,
                'events_processed' => $result['events_processed'],
            ]);
        }
    }

    /**
     * Update sync token for incremental sync
     */
    private function updateSyncToken(string $syncToken): void
    {
        $settings = $this->integration->sync_settings_display ?? [];
        $settings['sync_token'] = $syncToken;

        $this->integration->update([
            'sync_settings' => $settings,
        ]);
    }

    /**
     * Create sync job record for tracking
     */
    private function createSyncJobRecord(): CalendarSyncJob
    {
        return CalendarSyncJob::create([
            'calendar_integration_id' => $this->integration->id,
            'job_type' => 'webhook_sync',
            'status' => 'processing',
            'started_at' => now(),
            'job_data' => [
                'webhook_type' => $this->getWebhookType(),
                'webhook_id' => $this->webhookData['id'] ?? uniqid(),
                'priority' => $this->options['priority'] ?? 'normal',
                'auto_triggered' => true,
            ],
        ]);
    }

    /**
     * Mark job as completed
     */
    private function markJobCompleted(string $status, array $result = []): void
    {
        if ($this->syncJob) {
            $this->syncJob->update([
                'status' => $status === 'success' ? 'completed' : $status,
                'completed_at' => now(),
                'events_processed' => $result['events_processed'] ?? 0,
                'job_data' => array_merge($this->syncJob->job_data ?? [], $result),
            ]);
        }
    }

    /**
     * Handle webhook processing errors
     */
    private function handleWebhookError(Exception $e): void
    {
        Log::error('Calendar webhook processing failed', [
            'integration_id' => $this->integration->id,
            'provider' => $this->integration->provider,
            'error' => $e->getMessage(),
            'attempt' => $this->attempts(),
            'trace' => $e->getTraceAsString(),
        ]);

        // Update sync job with error
        if ($this->syncJob) {
            $this->syncJob->update([
                'status' => 'failed',
                'completed_at' => now(),
                'error_message' => $e->getMessage(),
                'job_data' => array_merge($this->syncJob->job_data ?? [], [
                    'error_class' => get_class($e),
                    'error_code' => $e->getCode(),
                    'attempt' => $this->attempts(),
                ]),
            ]);
        }

        // Update integration error count
        $this->integration->increment('sync_error_count');
        $this->integration->update(['last_sync_error' => $e->getMessage()]);

        // Re-throw exception to trigger job retry mechanism
        throw $e;
    }

    /**
     * Handle job failure after all retries exhausted
     */
    public function failed(Exception $exception): void
    {
        Log::error('Calendar webhook job failed permanently', [
            'integration_id' => $this->integration->id,
            'provider' => $this->integration->provider,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);

        // Mark sync job as permanently failed
        if ($this->syncJob) {
            $this->syncJob->update([
                'status' => 'failed',
                'completed_at' => now(),
                'error_message' => 'Job failed after ' . $this->tries . ' attempts: ' . $exception->getMessage(),
                'job_data' => array_merge($this->syncJob->job_data ?? [], [
                    'permanently_failed' => true,
                    'final_error' => $exception->getMessage(),
                    'total_attempts' => $this->tries,
                ]),
            ]);
        }

        // Disable integration if too many webhook failures
        if ($this->integration->sync_error_count >= 10) {
            Log::warning('Disabling calendar integration due to excessive webhook failures', [
                'integration_id' => $this->integration->id,
                'error_count' => $this->integration->sync_error_count,
            ]);

            $this->integration->update([
                'is_active' => false,
                'last_sync_error' => 'Disabled due to excessive webhook failures',
            ]);

            // Send notification about integration being disabled
            $this->sendIntegrationDisabledNotification();
        }
    }

    /**
     * Get webhook type from data
     */
    private function getWebhookType(): string
    {
        return $this->webhookData['type'] ??
            $this->webhookData['changeType'] ??
            $this->webhookData['headers']['X-Goog-Resource-State'] ??
            'unknown';
    }

    /**
     * Get queue name based on priority
     */
    private function getQueueName(string $priority): string
    {
        return match ($priority) {
            'high', 'urgent' => 'calendar-webhooks-high',
            'low' => 'calendar-webhooks-low',
            default => 'calendar-webhooks'
        };
    }

    /**
     * Get job duration in seconds
     */
    private function getJobDuration(): ?int
    {
        if (!$this->syncJob || !$this->syncJob->started_at) {
            return null;
        }

        return $this->syncJob->started_at->diffInSeconds(now());
    }

    /**
     * Send integration disabled notification
     */
    private function sendIntegrationDisabledNotification(): void
    {
        try {
            $user = $this->integration->user;

            if ($user) {
                // Send email notification
                Log::info('Sending integration disabled notification', [
                    'integration_id' => $this->integration->id,
                    'user_id' => $user->id,
                    'provider' => $this->integration->provider,
                ]);

                // Implementation would send actual email notification
                // Mail::to($user->email)->send(new CalendarIntegrationDisabledMail($this->integration));
            }
        } catch (Exception $e) {
            Log::error('Failed to send integration disabled notification', [
                'integration_id' => $this->integration->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public function retryAfter(): int
    {
        $attempt = $this->attempts();

        // Exponential backoff with jitter for webhook processing
        $baseDelay = 30; // 30 seconds base delay
        $delay = min($baseDelay * pow(2, $attempt - 1), 300); // Max 5 minutes
        $jitter = rand(0, 15); // Add up to 15 seconds jitter

        return $delay + $jitter;
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'calendar-webhook',
            'integration:' . $this->integration->id,
            'provider:' . $this->integration->provider,
            'user:' . $this->integration->user_id,
            'type:' . $this->getWebhookType(),
        ];
    }

    /**
     * Get the middleware the job should pass through.
     */
    public function middleware(): array
    {
        return [
            // Rate limit webhook processing per integration
            new \Illuminate\Queue\Middleware\RateLimited('webhook-processing-integration-' . $this->integration->id),

            // Prevent overlapping webhook processing for same integration
            new \Illuminate\Queue\Middleware\WithoutOverlapping($this->integration->id),
        ];
    }

    /**
     * Determine if the job should be encrypted.
     */
    public function shouldBeEncrypted(): bool
    {
        return true; // Encrypt webhook data for security
    }

    /**
     * Get the display name for the queued job.
     */
    public function displayName(): string
    {
        return "Process Calendar Webhook (Integration: {$this->integration->id}, Provider: {$this->integration->provider})";
    }

    /**
     * Calculate the number of seconds the job can run before timing out.
     */
    public function timeoutAt(): \Carbon\Carbon
    {
        return now()->addMinutes(5); // Hard timeout at 5 minutes
    }

    /**
     * Get unique identifier for this job type
     */
    public function uniqueId(): string
    {
        $webhookId = $this->webhookData['id'] ??
            $this->webhookData['headers']['X-Goog-Message-Number'] ??
            uniqid();

        return "calendar_webhook_{$this->integration->id}_{$webhookId}";
    }

    /**
     * Determine if job should be unique
     */
    public function uniqueFor(): int
    {
        return 300; // 5 minutes - prevent duplicate webhook processing
    }
}
