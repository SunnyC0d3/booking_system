<?php

namespace App\Services\V1\Calendar;

use App\Models\CalendarEvent;
use App\Models\CalendarIntegration;
use App\Models\Booking;
use App\Models\User;
use App\Traits\V1\ApiResponses;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class CalendarEventService
{
    use ApiResponses;

    /**
     * Get calendar events for user
     */
    public function getUserEvents(
        int $userId,
        Carbon $startDate,
        Carbon $endDate,
        ?int $integrationId = null
    ): Collection {
        // Verify permission
        $currentUser = auth()->user();
        if ($currentUser && $currentUser->id !== $userId && !$currentUser->hasPermission('view_all_calendar_integrations')) {
            Log::warning('Unauthorized calendar events access attempt', [
                'user_id' => $currentUser->id,
                'requested_user_id' => $userId,
            ]);
            return collect();
        }

        try {
            $query = CalendarEvent::whereHas('calendarIntegration', function ($q) use ($userId) {
                $q->where('user_id', $userId)->where('is_active', true);
            })
                ->whereBetween('starts_at', [$startDate->startOfDay(), $endDate->endOfDay()]);

            if ($integrationId) {
                $query->where('calendar_integration_id', $integrationId);
            }

            return $query->with(['calendarIntegration'])
                ->orderBy('starts_at')
                ->get();

        } catch (Exception $e) {
            Log::error('Failed to get user calendar events', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return collect();
        }
    }

    /**
     * Create calendar event
     */
    public function createEvent(array $data): ?CalendarEvent
    {
        try {
            // Verify user owns the integration
            $integration = CalendarIntegration::findOrFail($data['calendar_integration_id']);
            $currentUser = auth()->user();

            if ($currentUser && $currentUser->id !== $integration->user_id && !$currentUser->hasPermission('manage_all_calendar_integrations')) {
                Log::warning('Unauthorized calendar event creation attempt', [
                    'user_id' => $currentUser->id,
                    'integration_user_id' => $integration->user_id,
                ]);
                return null;
            }

            return DB::transaction(function () use ($data) {
                // Validate event doesn't conflict with existing events
                $this->validateEventConflicts($data);

                $event = CalendarEvent::create([
                    'calendar_integration_id' => $data['calendar_integration_id'],
                    'external_event_id' => $data['external_event_id'],
                    'title' => $data['title'],
                    'description' => $data['description'] ?? null,
                    'starts_at' => Carbon::parse($data['starts_at']),
                    'ends_at' => Carbon::parse($data['ends_at']),
                    'is_all_day' => $data['is_all_day'] ?? false,
                    'blocks_booking' => $data['blocks_booking'] ?? true,
                    'block_type' => $data['block_type'] ?? 'full',
                    'last_updated_externally' => $data['last_updated_externally'] ?? null,
                    'synced_at' => now(),
                ]);

                Log::info('Calendar event created', [
                    'event_id' => $event->id,
                    'integration_id' => $event->calendar_integration_id,
                    'title' => $event->title,
                ]);

                return $event;
            });

        } catch (Exception $e) {
            Log::error('Failed to create calendar event', [
                'data' => $data,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Update calendar event
     */
    public function updateEvent(CalendarEvent $event, array $data): bool
    {
        try {
            // Verify user owns the integration
            $currentUser = auth()->user();
            if ($currentUser && $currentUser->id !== $event->calendarIntegration->user_id && !$currentUser->hasPermission('manage_all_calendar_integrations')) {
                Log::warning('Unauthorized calendar event update attempt', [
                    'user_id' => $currentUser->id,
                    'integration_user_id' => $event->calendarIntegration->user_id,
                ]);
                return false;
            }

            return DB::transaction(function () use ($event, $data) {
                // Validate event doesn't conflict (excluding current event)
                $this->validateEventConflicts($data, $event->id);

                $updateData = [
                    'title' => $data['title'] ?? $event->title,
                    'description' => $data['description'] ?? $event->description,
                    'starts_at' => isset($data['starts_at']) ? Carbon::parse($data['starts_at']) : $event->starts_at,
                    'ends_at' => isset($data['ends_at']) ? Carbon::parse($data['ends_at']) : $event->ends_at,
                    'is_all_day' => $data['is_all_day'] ?? $event->is_all_day,
                    'blocks_booking' => $data['blocks_booking'] ?? $event->blocks_booking,
                    'block_type' => $data['block_type'] ?? $event->block_type,
                    'last_updated_externally' => $data['last_updated_externally'] ?? $event->last_updated_externally,
                    'synced_at' => now(),
                ];

                $event->update($updateData);

                Log::info('Calendar event updated', [
                    'event_id' => $event->id,
                    'updated_fields' => array_keys($data),
                ]);

                return true;
            });

        } catch (Exception $e) {
            Log::error('Failed to update calendar event', [
                'event_id' => $event->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Delete calendar event
     */
    public function deleteEvent(CalendarEvent $event): bool
    {
        try {
            // Verify user owns the integration
            $currentUser = auth()->user();
            if ($currentUser && $currentUser->id !== $event->calendarIntegration->user_id && !$currentUser->hasPermission('manage_all_calendar_integrations')) {
                Log::warning('Unauthorized calendar event deletion attempt', [
                    'user_id' => $currentUser->id,
                    'integration_user_id' => $event->calendarIntegration->user_id,
                ]);
                return false;
            }

            $event->delete();

            Log::info('Calendar event deleted', [
                'event_id' => $event->id,
                'title' => $event->title,
            ]);

            return true;

        } catch (Exception $e) {
            Log::error('Failed to delete calendar event', [
                'event_id' => $event->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Bulk delete events by integration
     */
    public function bulkDeleteEventsByIntegration(CalendarIntegration $integration): int
    {
        try {
            // Verify user owns the integration
            $currentUser = auth()->user();
            if ($currentUser && $currentUser->id !== $integration->user_id && !$currentUser->hasPermission('manage_all_calendar_integrations')) {
                Log::warning('Unauthorized bulk calendar event deletion attempt', [
                    'user_id' => $currentUser->id,
                    'integration_user_id' => $integration->user_id,
                ]);
                return 0;
            }

            $deletedCount = $integration->calendarEvents()->delete();

            Log::info('Bulk calendar events deleted', [
                'integration_id' => $integration->id,
                'deleted_count' => $deletedCount,
            ]);

            return $deletedCount;

        } catch (Exception $e) {
            Log::error('Failed to bulk delete calendar events', [
                'integration_id' => $integration->id,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * Find conflicting events
     */
    public function findConflictingEvents(
        int $integrationId,
        Carbon $startTime,
        Carbon $endTime,
        ?int $excludeEventId = null
    ): Collection {
        try {
            // Verify user owns the integration
            $integration = CalendarIntegration::findOrFail($integrationId);
            $currentUser = auth()->user();

            if ($currentUser && $currentUser->id !== $integration->user_id && !$currentUser->hasPermission('view_all_calendar_integrations')) {
                return collect();
            }

            $query = CalendarEvent::where('calendar_integration_id', $integrationId)
                ->where('blocks_booking', true)
                ->where(function ($q) use ($startTime, $endTime) {
                    $q->where(function ($query) use ($startTime, $endTime) {
                        // Event starts before our end time and ends after our start time
                        $query->where('starts_at', '<', $endTime)
                            ->where('ends_at', '>', $startTime);
                    });
                });

            if ($excludeEventId) {
                $query->where('id', '!=', $excludeEventId);
            }

            return $query->orderBy('starts_at')->get();

        } catch (Exception $e) {
            Log::error('Failed to find conflicting events', [
                'integration_id' => $integrationId,
                'error' => $e->getMessage(),
            ]);

            return collect();
        }
    }

    /**
     * Get availability gaps between events
     */
    public function getAvailabilityGaps(
        int $integrationId,
        Carbon $startDate,
        Carbon $endDate,
        int $minGapMinutes = 30
    ): array {
        try {
            // Verify user permission
            $integration = CalendarIntegration::findOrFail($integrationId);
            $currentUser = auth()->user();

            if ($currentUser && $currentUser->id !== $integration->user_id && !$currentUser->hasPermission('view_all_calendar_integrations')) {
                return [];
            }

            $events = CalendarEvent::where('calendar_integration_id', $integrationId)
                ->where('blocks_booking', true)
                ->where('is_all_day', false)
                ->whereBetween('starts_at', [$startDate, $endDate])
                ->orderBy('starts_at')
                ->get();

            $gaps = [];
            $currentTime = $startDate->clone();

            foreach ($events as $event) {
                $eventStart = $event->starts_at;

                // Check gap before this event
                if ($currentTime->lt($eventStart)) {
                    $gapMinutes = $currentTime->diffInMinutes($eventStart);

                    if ($gapMinutes >= $minGapMinutes) {
                        $gaps[] = [
                            'start' => $currentTime->toISOString(),
                            'end' => $eventStart->toISOString(),
                            'duration_minutes' => $gapMinutes,
                        ];
                    }
                }

                // Move current time to end of this event
                $currentTime = $event->ends_at->gt($currentTime) ? $event->ends_at : $currentTime;
            }

            // Check gap after last event until end date
            if ($currentTime->lt($endDate)) {
                $gapMinutes = $currentTime->diffInMinutes($endDate);

                if ($gapMinutes >= $minGapMinutes) {
                    $gaps[] = [
                        'start' => $currentTime->toISOString(),
                        'end' => $endDate->toISOString(),
                        'duration_minutes' => $gapMinutes,
                    ];
                }
            }

            return $gaps;

        } catch (Exception $e) {
            Log::error('Failed to get availability gaps', [
                'integration_id' => $integrationId,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Search events by criteria
     */
    public function searchEvents(array $criteria): Collection
    {
        try {
            // Verify user permission
            $currentUser = auth()->user();
            if (!$currentUser) {
                return collect();
            }

            $query = CalendarEvent::query();

            // Filter by user's integrations only (unless admin)
            if (!$currentUser->hasPermission('view_all_calendar_integrations')) {
                $query->whereHas('calendarIntegration', function ($q) use ($currentUser) {
                    $q->where('user_id', $currentUser->id);
                });
            }

            // Apply search criteria
            if (!empty($criteria['title'])) {
                $query->where('title', 'LIKE', '%' . $criteria['title'] . '%');
            }

            if (!empty($criteria['description'])) {
                $query->where('description', 'LIKE', '%' . $criteria['description'] . '%');
            }

            if (!empty($criteria['start_date'])) {
                $query->where('starts_at', '>=', Carbon::parse($criteria['start_date']));
            }

            if (!empty($criteria['end_date'])) {
                $query->where('ends_at', '<=', Carbon::parse($criteria['end_date']));
            }

            if (isset($criteria['blocks_booking'])) {
                $query->where('blocks_booking', $criteria['blocks_booking']);
            }

            if (isset($criteria['is_all_day'])) {
                $query->where('is_all_day', $criteria['is_all_day']);
            }

            if (!empty($criteria['integration_id'])) {
                $query->where('calendar_integration_id', $criteria['integration_id']);
            }

            if (!empty($criteria['block_type'])) {
                $query->where('block_type', $criteria['block_type']);
            }

            return $query->with(['calendarIntegration'])
                ->orderBy('starts_at')
                ->limit($criteria['limit'] ?? 100)
                ->get();

        } catch (Exception $e) {
            Log::error('Failed to search calendar events', [
                'criteria' => $criteria,
                'error' => $e->getMessage(),
            ]);

            return collect();
        }
    }

    /**
     * Get event statistics for integration
     */
    public function getEventStats(int $integrationId, ?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        try {
            // Verify user permission
            $integration = CalendarIntegration::findOrFail($integrationId);
            $currentUser = auth()->user();

            if ($currentUser && $currentUser->id !== $integration->user_id && !$currentUser->hasPermission('view_all_calendar_integrations')) {
                return ['error' => 'Unauthorized'];
            }

            $startDate = $startDate ?? now()->startOfMonth();
            $endDate = $endDate ?? now()->endOfMonth();

            $query = CalendarEvent::where('calendar_integration_id', $integrationId)
                ->whereBetween('starts_at', [$startDate, $endDate]);

            $stats = [
                'total_events' => $query->count(),
                'blocking_events' => $query->clone()->where('blocks_booking', true)->count(),
                'all_day_events' => $query->clone()->where('is_all_day', true)->count(),
                'upcoming_events' => $query->clone()->where('starts_at', '>', now())->count(),
                'past_events' => $query->clone()->where('ends_at', '<', now())->count(),
                'total_blocked_hours' => $this->calculateBlockedHours($integrationId, $startDate, $endDate),
                'busiest_day' => $this->getBusiestDay($integrationId, $startDate, $endDate),
                'average_event_duration' => $this->getAverageEventDuration($integrationId, $startDate, $endDate),
            ];

            return $stats;

        } catch (Exception $e) {
            Log::error('Failed to get event statistics', [
                'integration_id' => $integrationId,
                'error' => $e->getMessage(),
            ]);

            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Validate event doesn't conflict with existing events
     */
    private function validateEventConflicts(array $data, ?int $excludeEventId = null): void
    {
        $startTime = Carbon::parse($data['starts_at']);
        $endTime = Carbon::parse($data['ends_at']);

        $conflicts = $this->findConflictingEvents(
            $data['calendar_integration_id'],
            $startTime,
            $endTime,
            $excludeEventId
        );

        if ($conflicts->isNotEmpty()) {
            $conflictTitles = $conflicts->pluck('title')->implode(', ');
            throw new Exception("Event conflicts with existing events: {$conflictTitles}");
        }
    }

    /**
     * Calculate total blocked hours
     */
    private function calculateBlockedHours(int $integrationId, Carbon $startDate, Carbon $endDate): float
    {
        $events = CalendarEvent::where('calendar_integration_id', $integrationId)
            ->where('blocks_booking', true)
            ->where('is_all_day', false)
            ->whereBetween('starts_at', [$startDate, $endDate])
            ->get();

        $totalMinutes = $events->sum(function ($event) {
            return $event->starts_at->diffInMinutes($event->ends_at);
        });

        return round($totalMinutes / 60, 2);
    }

    /**
     * Get busiest day of the period
     */
    private function getBusiestDay(int $integrationId, Carbon $startDate, Carbon $endDate): ?string
    {
        $events = CalendarEvent::where('calendar_integration_id', $integrationId)
            ->whereBetween('starts_at', [$startDate, $endDate])
            ->get()
            ->groupBy(function ($event) {
                return $event->starts_at->format('Y-m-d');
            });

        if ($events->isEmpty()) {
            return null;
        }

        $busiestDay = $events->sortByDesc(function ($dayEvents) {
            return $dayEvents->count();
        })->keys()->first();

        return $busiestDay;
    }

    /**
     * Get average event duration in minutes
     */
    private function getAverageEventDuration(int $integrationId, Carbon $startDate, Carbon $endDate): float
    {
        $events = CalendarEvent::where('calendar_integration_id', $integrationId)
            ->where('is_all_day', false)
            ->whereBetween('starts_at', [$startDate, $endDate])
            ->get();

        if ($events->isEmpty()) {
            return 0;
        }

        $totalMinutes = $events->sum(function ($event) {
            return $event->starts_at->diffInMinutes($event->ends_at);
        });

        return round($totalMinutes / $events->count(), 0);
    }
}
