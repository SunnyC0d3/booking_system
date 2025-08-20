<?php

namespace App\Services\V1\Calendar;

use App\Constants\CalendarProviders;
use App\Models\CalendarIntegration;
use App\Models\Booking;
use App\Models\Service;
use App\Models\User;
use App\Jobs\Calendar\SyncCalendarEvents;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

class CalendarSyncService
{
    private GoogleCalendarService $googleService;
    private ICalService $icalService;

    public function __construct(
        GoogleCalendarService $googleService,
        ICalService $icalService
    ) {
        $this->googleService = $googleService;
        $this->icalService = $icalService;
    }

    /**
     * Sync booking to all active calendar integrations
     */
    public function syncBookingToCalendars(Booking $booking): array
    {
        try {
            // Verify user has permission to sync their bookings
            $currentUser = auth()->user();
            if ($currentUser && $currentUser->id !== $booking->user_id && !$currentUser->hasPermission('manage_all_bookings')) {
                Log::warning('Unauthorized booking calendar sync attempt', [
                    'user_id' => $currentUser->id,
                    'booking_user_id' => $booking->user_id,
                ]);
                return ['success' => false, 'error' => 'Unauthorized'];
            }

            $results = ['synced' => 0, 'failed' => 0, 'errors' => []];

            // Get active integrations for this user and service
            $integrations = CalendarIntegration::where('user_id', $booking->user_id)
                ->where('is_active', true)
                ->where('sync_bookings', true)
                ->where(function ($query) use ($booking) {
                    $query->where('service_id', $booking->service_id)
                        ->orWhereNull('service_id');
                })
                ->get();

            foreach ($integrations as $integration) {
                try {
                    $success = $this->syncBookingToIntegration($booking, $integration);

                    if ($success) {
                        $results['synced']++;
                    } else {
                        $results['failed']++;
                        $results['errors'][] = "Failed to sync to {$integration->provider}";
                    }

                } catch (Exception $e) {
                    $results['failed']++;
                    $results['errors'][] = "Error syncing to {$integration->provider}: " . $e->getMessage();

                    Log::error('Calendar sync failed for integration', [
                        'booking_id' => $booking->id,
                        'integration_id' => $integration->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            Log::info('Booking calendar sync completed', [
                'booking_id' => $booking->id,
                'synced' => $results['synced'],
                'failed' => $results['failed'],
            ]);

            return $results;

        } catch (Exception $e) {
            Log::error('Booking calendar sync failed', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Sync single booking to specific integration
     */
    public function syncBookingToIntegration(Booking $booking, CalendarIntegration $integration): bool
    {
        try {
            $provider = $this->getProviderService($integration->provider);

            // Check if event already exists
            $existingEventId = $booking->calendar_events()
                ->where('calendar_integration_id', $integration->id)
                ->value('external_event_id');

            if ($existingEventId) {
                // Update existing event
                return $provider->updateEvent($integration, $booking, $existingEventId);
            } else {
                // Create new event
                $eventId = $provider->createEvent($integration, $booking);

                if ($eventId) {
                    // Store event reference
                    $booking->calendar_events()->create([
                        'calendar_integration_id' => $integration->id,
                        'external_event_id' => $eventId,
                        'title' => $integration->getCalendarEventTitle($booking),
                        'starts_at' => $booking->scheduled_at,
                        'ends_at' => $booking->ends_at,
                        'synced_at' => now(),
                    ]);

                    return true;
                }
            }

            return false;

        } catch (Exception $e) {
            Log::error('Failed to sync booking to integration', [
                'booking_id' => $booking->id,
                'integration_id' => $integration->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Remove booking from all calendars
     */
    public function removeBookingFromCalendars(Booking $booking): array
    {
        try {
            // Verify user has permission
            $currentUser = auth()->user();
            if ($currentUser && $currentUser->id !== $booking->user_id && !$currentUser->hasPermission('manage_all_bookings')) {
                return ['success' => false, 'error' => 'Unauthorized'];
            }

            $results = ['removed' => 0, 'failed' => 0, 'errors' => []];

            $calendarEvents = $booking->calendar_events()->get();

            foreach ($calendarEvents as $calendarEvent) {
                try {
                    $integration = $calendarEvent->calendarIntegration;
                    $provider = $this->getProviderService($integration->provider);

                    $success = $provider->deleteEvent($integration, $calendarEvent->external_event_id);

                    if ($success) {
                        $calendarEvent->delete();
                        $results['removed']++;
                    } else {
                        $results['failed']++;
                        $results['errors'][] = "Failed to remove from {$integration->provider}";
                    }

                } catch (Exception $e) {
                    $results['failed']++;
                    $results['errors'][] = "Error removing from calendar: " . $e->getMessage();
                }
            }

            return $results;

        } catch (Exception $e) {
            Log::error('Failed to remove booking from calendars', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Check availability across all integrated calendars
     */
    public function checkAvailabilityAcrossCalendars(
        User $user,
        Service $service,
        Carbon $startTime,
        Carbon $endTime
    ): array {
        try {
            // Verify permission
            $currentUser = auth()->user();
            if ($currentUser && $currentUser->id !== $user->id && !$currentUser->hasPermission('view_all_calendar_integrations')) {
                return ['available' => true, 'conflicts' => [], 'error' => 'Unauthorized'];
            }

            $conflicts = [];
            $integrations = CalendarIntegration::where('user_id', $user->id)
                ->where('is_active', true)
                ->where('auto_block_external_events', true)
                ->where(function ($query) use ($service) {
                    $query->where('service_id', $service->id)
                        ->orWhereNull('service_id');
                })
                ->get();

            foreach ($integrations as $integration) {
                try {
                    $provider = $this->getProviderService($integration->provider);
                    $isAvailable = $provider->isTimeSlotAvailable($integration, $startTime, $endTime);

                    if (!$isAvailable) {
                        $events = $provider->getEvents($integration, $startTime, $endTime);
                        $conflicts[] = [
                            'provider' => $integration->provider,
                            'calendar_name' => $integration->calendar_name,
                            'conflicting_events' => $events,
                        ];
                    }

                } catch (Exception $e) {
                    Log::warning('Calendar availability check failed', [
                        'integration_id' => $integration->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return [
                'available' => empty($conflicts),
                'conflicts' => $conflicts,
                'checked_calendars' => $integrations->count(),
            ];

        } catch (Exception $e) {
            Log::error('Availability check failed', [
                'user_id' => $user->id,
                'service_id' => $service->id,
                'error' => $e->getMessage(),
            ]);

            return ['available' => true, 'conflicts' => [], 'error' => $e->getMessage()];
        }
    }

    /**
     * Process scheduled syncs for all integrations
     */
    public function processScheduledSyncs(): array
    {
        $results = ['processed' => 0, 'queued' => 0, 'failed' => 0];

        try {
            // Get integrations that need syncing
            $integrations = CalendarIntegration::where('is_active', true)
                ->where(function ($query) {
                    $query->whereNull('last_sync_at')
                        ->orWhere(function ($q) {
                            $q->whereRaw('last_sync_at < DATE_SUB(NOW(), INTERVAL JSON_EXTRACT(sync_settings, "$.sync_frequency") MINUTE)');
                        });
                })
                ->limit(50) // Process in batches
                ->get();

            foreach ($integrations as $integration) {
                try {
                    // Queue sync job for each integration
                    SyncCalendarEvents::dispatch($integration)
                        ->onQueue('calendar-sync');

                    $results['queued']++;

                } catch (Exception $e) {
                    $results['failed']++;
                    Log::error('Failed to queue calendar sync', [
                        'integration_id' => $integration->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $results['processed'] = $integrations->count();

            Log::info('Scheduled calendar syncs processed', $results);

        } catch (Exception $e) {
            Log::error('Failed to process scheduled syncs', [
                'error' => $e->getMessage(),
            ]);
        }

        return $results;
    }

    /**
     * Sync external events from calendar to block availability
     */
    public function syncExternalEvents(CalendarIntegration $integration): array
    {
        try {
            // Verify user permission
            $currentUser = auth()->user();
            if ($currentUser && $currentUser->id !== $integration->user_id && !$currentUser->hasPermission('manage_all_calendar_integrations')) {
                return ['success' => false, 'error' => 'Unauthorized'];
            }

            $provider = $this->getProviderService($integration->provider);
            $results = ['synced' => 0, 'updated' => 0, 'deleted' => 0, 'errors' => []];

            // Get sync date range
            $settings = $integration->sync_settings_display;
            $startDate = now()->subDays($settings['sync_past_days'] ?? 7);
            $endDate = now()->addDays($settings['sync_future_days'] ?? 90);

            // Fetch events from external calendar
            $externalEvents = $provider->getEvents($integration, $startDate, $endDate);

            // Process each external event
            foreach ($externalEvents as $eventData) {
                try {
                    $this->processExternalEvent($integration, $eventData);
                    $results['synced']++;

                } catch (Exception $e) {
                    $results['errors'][] = "Failed to process event: " . $e->getMessage();
                }
            }

            // Update last sync timestamp
            $integration->markSyncCompleted();

            Log::info('External events synced', [
                'integration_id' => $integration->id,
                'results' => $results,
            ]);

            return $results;

        } catch (Exception $e) {
            Log::error('External events sync failed', [
                'integration_id' => $integration->id,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get provider service instance
     */
    private function getProviderService(string $provider)
    {
        return match ($provider) {
            CalendarProviders::GOOGLE => $this->googleService,
            CalendarProviders::ICAL => $this->icalService,
            default => throw new Exception('Unsupported calendar provider: ' . $provider)
        };
    }

    /**
     * Process single external event
     */
    private function processExternalEvent(CalendarIntegration $integration, array $eventData): void
    {
        $integration->calendarEvents()->updateOrCreate(
            [
                'calendar_integration_id' => $integration->id,
                'external_event_id' => $eventData['id'],
            ],
            [
                'title' => $eventData['title'],
                'starts_at' => Carbon::parse($eventData['start']),
                'ends_at' => Carbon::parse($eventData['end']),
                'is_all_day' => $eventData['all_day'],
                'blocks_booking' => true,
                'synced_at' => now(),
            ]
        );
    }

    /**
     * Cleanup old external events
     */
    public function cleanupOldEvents(): int
    {
        $deleted = 0;

        try {
            // Delete events older than 30 days
            $cutoffDate = now()->subDays(30);

            $deleted = DB::table('calendar_events')
                ->where('ends_at', '<', $cutoffDate)
                ->delete();

            Log::info('Old calendar events cleaned up', [
                'deleted_count' => $deleted,
                'cutoff_date' => $cutoffDate->toDateString(),
            ]);

        } catch (Exception $e) {
            Log::error('Failed to cleanup old calendar events', [
                'error' => $e->getMessage(),
            ]);
        }

        return $deleted;
    }

    /**
     * Get sync status for all user integrations
     */
    public function getSyncStatus(int $userId): array
    {
        // Verify permission
        $currentUser = auth()->user();
        if ($currentUser && $currentUser->id !== $userId && !$currentUser->hasPermission('view_all_calendar_integrations')) {
            return ['error' => 'Unauthorized'];
        }

        $integrations = CalendarIntegration::where('user_id', $userId)
            ->where('is_active', true)
            ->get();

        return $integrations->map(function ($integration) {
            return [
                'id' => $integration->id,
                'provider' => $integration->provider,
                'calendar_name' => $integration->calendar_name,
                'last_sync_at' => $integration->last_sync_at?->toISOString(),
                'sync_error_count' => $integration->sync_error_count,
                'last_sync_error' => $integration->last_sync_error,
                'is_healthy' => $integration->sync_error_count < 3,
                'next_sync_at' => $this->calculateNextSync($integration)?->toISOString(),
            ];
        })->toArray();
    }

    /**
     * Calculate next sync time for integration
     */
    private function calculateNextSync(CalendarIntegration $integration): ?Carbon
    {
        $lastSync = $integration->last_sync_at ?? $integration->created_at;
        $frequency = $integration->sync_settings_display['sync_frequency'] ?? 30;

        return $lastSync->addMinutes($frequency);
    }
}
