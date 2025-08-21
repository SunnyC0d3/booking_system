<?php

namespace App\Services\V1\Venues;

use App\Models\VenueAvailabilityWindow;
use App\Models\ServiceLocation;
use App\Models\Booking;
use App\Models\User;
use App\Constants\BookingStatuses;
use App\Traits\V1\ApiResponses;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VenueAvailabilityService
{
    use ApiResponses;

    /**
     * Create new venue availability window
     */
    public function createAvailabilityWindow(array $data): VenueAvailabilityWindow
    {
        try {
            // Validate service location exists
            $serviceLocation = ServiceLocation::findOrFail($data['service_location_id']);

            return DB::transaction(function () use ($data, $serviceLocation) {
                // Process and validate availability data
                $processedData = $this->processAvailabilityData($data);

                // Validate business rules
                $this->validateAvailabilityRules($processedData);

                // Create availability window
                $window = VenueAvailabilityWindow::create($processedData);

                Log::info('Venue availability window created', [
                    'window_id' => $window->id,
                    'service_location_id' => $serviceLocation->id,
                    'window_type' => $window->window_type,
                ]);

                // Clear related caches
                $this->clearAvailabilityCaches($serviceLocation);

                return $window->fresh();
            });

        } catch (Exception $e) {
            Log::error('Failed to create venue availability window', [
                'service_location_id' => $data['service_location_id'] ?? null,
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            throw $e;
        }
    }

    /**
     * Update venue availability window
     */
    public function updateAvailabilityWindow(VenueAvailabilityWindow $window, array $data): VenueAvailabilityWindow
    {
        try {
            return DB::transaction(function () use ($window, $data) {
                // Process and validate update data
                $processedData = $this->processAvailabilityData($data);

                // Validate business rules
                $this->validateAvailabilityRules($processedData, $window->id);

                // Track changes for logging
                $originalData = $window->toArray();

                // Update availability window
                $window->update($processedData);

                // Log significant changes
                $this->logAvailabilityChanges($window, $originalData, $processedData);

                // Clear related caches
                $this->clearAvailabilityCaches($window->serviceLocation);

                return $window->fresh();
            });

        } catch (Exception $e) {
            Log::error('Failed to update venue availability window', [
                'window_id' => $window->id,
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            throw $e;
        }
    }

    /**
     * Delete venue availability window
     */
    public function deleteAvailabilityWindow(VenueAvailabilityWindow $window): bool
    {
        try {
            return DB::transaction(function () use ($window) {
                $serviceLocationId = $window->service_location_id;
                $windowId = $window->id;

                // Perform deletion
                $deleted = $window->delete();

                if ($deleted) {
                    Log::info('Venue availability window deleted', [
                        'window_id' => $windowId,
                        'service_location_id' => $serviceLocationId,
                    ]);

                    // Clear related caches
                    $serviceLocation = ServiceLocation::find($serviceLocationId);
                    if ($serviceLocation) {
                        $this->clearAvailabilityCaches($serviceLocation);
                    }
                }

                return $deleted;
            });

        } catch (Exception $e) {
            Log::error('Failed to delete venue availability window', [
                'window_id' => $window->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Check for overlapping availability windows
     */
    public function checkOverlappingWindows(
        ServiceLocation $location,
        array $data,
        ?int $excludeWindowId = null
    ): array {
        try {
            $conflicts = [];
            $windowType = $data['window_type'];

            $query = VenueAvailabilityWindow::where('service_location_id', $location->id)
                ->where('is_active', true);

            if ($excludeWindowId) {
                $query->where('id', '!=', $excludeWindowId);
            }

            $existingWindows = $query->get();

            foreach ($existingWindows as $existingWindow) {
                $overlap = $this->checkWindowOverlap($data, $existingWindow->toArray());

                if ($overlap['has_overlap']) {
                    $conflicts[] = [
                        'window_id' => $existingWindow->id,
                        'window_type' => $existingWindow->window_type,
                        'overlap_details' => $overlap,
                        'severity' => $this->determineConflictSeverity($windowType, $existingWindow->window_type),
                    ];
                }
            }

            return $conflicts;

        } catch (Exception $e) {
            Log::error('Failed to check overlapping windows', [
                'location_id' => $location->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Assess impact on existing bookings
     */
    public function assessBookingImpact(VenueAvailabilityWindow $window, array $newData): array
    {
        try {
            $affectedBookings = [];
            $hasConflicts = false;

            // Get bookings that might be affected by this change
            $potentiallyAffectedBookings = $this->getPotentiallyAffectedBookings($window, $newData);

            foreach ($potentiallyAffectedBookings as $booking) {
                $impact = $this->analyzeBookingImpact($booking, $window, $newData);

                if ($impact['has_impact']) {
                    $affectedBookings[] = $impact;
                    if ($impact['severity'] === 'high') {
                        $hasConflicts = true;
                    }
                }
            }

            return [
                'has_conflicts' => $hasConflicts,
                'affected_bookings' => $affectedBookings,
                'total_affected' => count($affectedBookings),
                'high_impact_count' => count(array_filter($affectedBookings, fn($b) => $b['severity'] === 'high')),
            ];

        } catch (Exception $e) {
            Log::error('Failed to assess booking impact', [
                'window_id' => $window->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Assess deletion impact on existing bookings
     */
    public function assessDeletionImpact(VenueAvailabilityWindow $window): array
    {
        try {
            $affectedBookings = [];
            $hasConflicts = false;

            // Find bookings that depend on this availability window
            $dependentBookings = $this->findDependentBookings($window);

            foreach ($dependentBookings as $booking) {
                $impact = [
                    'booking_id' => $booking->id,
                    'booking_reference' => $booking->booking_reference,
                    'scheduled_at' => $booking->scheduled_at->toISOString(),
                    'status' => $booking->status,
                    'impact_type' => 'availability_removed',
                    'severity' => 'high',
                    'recommended_action' => 'manual_review',
                ];

                $affectedBookings[] = $impact;
                $hasConflicts = true;
            }

            return [
                'has_conflicts' => $hasConflicts,
                'affected_bookings' => $affectedBookings,
                'total_affected' => count($affectedBookings),
            ];

        } catch (Exception $e) {
            Log::error('Failed to assess deletion impact', [
                'window_id' => $window->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle affected bookings when availability changes
     */
    public function handleAffectedBookings(array $affectedBookings, User $admin): void
    {
        try {
            foreach ($affectedBookings as $bookingImpact) {
                $booking = Booking::find($bookingImpact['booking_id']);

                if (!$booking) {
                    continue;
                }

                switch ($bookingImpact['recommended_action']) {
                    case 'auto_reschedule':
                        $this->attemptAutoReschedule($booking, $admin);
                        break;

                    case 'manual_review':
                        $this->flagForManualReview($booking, $bookingImpact, $admin);
                        break;

                    case 'cancel_booking':
                        $this->handleForcedCancellation($booking, $bookingImpact, $admin);
                        break;

                    default:
                        $this->flagForManualReview($booking, $bookingImpact, $admin);
                        break;
                }
            }

        } catch (Exception $e) {
            Log::error('Failed to handle affected bookings', [
                'affected_count' => count($affectedBookings),
                'admin_id' => $admin->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Get window conflicts for display
     */
    public function getWindowConflicts(VenueAvailabilityWindow $window): array
    {
        try {
            return [
                'scheduling_conflicts' => $this->findSchedulingConflicts($window),
                'booking_conflicts' => $this->findBookingConflicts($window),
                'capacity_issues' => $this->findCapacityIssues($window),
            ];

        } catch (Exception $e) {
            Log::error('Failed to get window conflicts', [
                'window_id' => $window->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'scheduling_conflicts' => [],
                'booking_conflicts' => [],
                'capacity_issues' => [],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get window usage statistics
     */
    public function getWindowUsageStats(VenueAvailabilityWindow $window): array
    {
        try {
            $dateRange = $this->getStatsDateRange();

            return [
                'total_bookings' => $this->getWindowBookingCount($window, $dateRange),
                'utilization_rate' => $this->calculateUtilizationRate($window, $dateRange),
                'average_event_duration' => $this->getAverageEventDuration($window, $dateRange),
                'peak_usage_times' => $this->getPeakUsageTimes($window, $dateRange),
                'revenue_generated' => $this->getWindowRevenue($window, $dateRange),
                'common_issues' => $this->getCommonIssues($window, $dateRange),
            ];

        } catch (Exception $e) {
            Log::error('Failed to get window usage stats', [
                'window_id' => $window->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'total_bookings' => 0,
                'utilization_rate' => 0,
                'average_event_duration' => 0,
                'peak_usage_times' => [],
                'revenue_generated' => 0,
                'common_issues' => [],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get available time slots for booking
     */
    public function getAvailableSlots(
        ServiceLocation $location,
        Carbon $startDate,
        Carbon $endDate,
        int $durationMinutes = 240,
        array $options = []
    ): SupportCollection {
        try {
            $cacheKey = "venue_slots_{$location->id}_{$startDate->format('Y-m-d')}_{$endDate->format('Y-m-d')}_{$durationMinutes}";

            return Cache::remember($cacheKey, 600, function () use ($location, $startDate, $endDate, $durationMinutes, $options) {
                return $this->generateAvailableSlots($location, $startDate, $endDate, $durationMinutes, $options);
            });

        } catch (Exception $e) {
            Log::error('Failed to get available slots', [
                'location_id' => $location->id,
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'error' => $e->getMessage(),
            ]);

            return collect();
        }
    }

    /**
     * Generate public availability calendar
     */
    public function generatePublicAvailabilityCalendar(
        ServiceLocation $location,
        Carbon $startDate,
        Carbon $endDate,
        int $eventDuration = 240
    ): array {
        try {
            $calendar = [];
            $current = $startDate->copy();

            while ($current->lte($endDate)) {
                $dayAvailability = $this->getLocationDayAvailability($location, $current, $eventDuration);

                $calendar[$current->toDateString()] = [
                    'date' => $current->toDateString(),
                    'day_name' => $current->format('l'),
                    'is_available' => $dayAvailability['is_available'],
                    'available_slots' => $dayAvailability['slots'],
                    'total_slots' => count($dayAvailability['slots']),
                    'restrictions' => $dayAvailability['restrictions'],
                    'notes' => $dayAvailability['notes'],
                ];

                $current->addDay();
            }

            return [
                'location_id' => $location->id,
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'event_duration_minutes' => $eventDuration,
                'calendar' => $calendar,
                'summary' => $this->generateCalendarSummary($calendar),
            ];

        } catch (Exception $e) {
            Log::error('Failed to generate public availability calendar', [
                'location_id' => $location->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    // ================================
    // PRIVATE HELPER METHODS
    // ================================

    /**
     * Process and validate availability data
     */
    private function processAvailabilityData(array $data): array
    {
        // Validate window type
        if (!in_array($data['window_type'], ['regular', 'special_event', 'maintenance', 'seasonal'])) {
            throw new Exception('Invalid window type');
        }

        // Process time fields
        if (isset($data['earliest_access']) && is_string($data['earliest_access'])) {
            $data['earliest_access'] = Carbon::createFromFormat('H:i', $data['earliest_access']);
        }

        if (isset($data['latest_departure']) && is_string($data['latest_departure'])) {
            $data['latest_departure'] = Carbon::createFromFormat('H:i', $data['latest_departure']);
        }

        if (isset($data['quiet_hours_start']) && is_string($data['quiet_hours_start'])) {
            $data['quiet_hours_start'] = Carbon::createFromFormat('H:i', $data['quiet_hours_start']);
        }

        if (isset($data['quiet_hours_end']) && is_string($data['quiet_hours_end'])) {
            $data['quiet_hours_end'] = Carbon::createFromFormat('H:i', $data['quiet_hours_end']);
        }

        // Process restrictions array
        if (isset($data['restrictions']) && is_string($data['restrictions'])) {
            $data['restrictions'] = json_decode($data['restrictions'], true);
        }

        // Validate numeric values
        if (isset($data['max_concurrent_events']) && $data['max_concurrent_events'] < 1) {
            throw new Exception('Maximum concurrent events must be at least 1');
        }

        return $data;
    }

    /**
     * Validate availability business rules
     */
    private function validateAvailabilityRules(array $data, ?int $excludeId = null): void
    {
        // Validate time constraints
        if (isset($data['earliest_access']) && isset($data['latest_departure'])) {
            if ($data['earliest_access']->gte($data['latest_departure'])) {
                throw new Exception('Earliest access time must be before latest departure time');
            }
        }

        // Validate quiet hours
        if (isset($data['quiet_hours_start']) && isset($data['quiet_hours_end'])) {
            if ($data['quiet_hours_start']->gte($data['quiet_hours_end'])) {
                throw new Exception('Quiet hours start must be before quiet hours end');
            }
        }

        // Validate date ranges
        if (isset($data['date_range_start']) && isset($data['date_range_end'])) {
            $startDate = Carbon::parse($data['date_range_start']);
            $endDate = Carbon::parse($data['date_range_end']);

            if ($startDate->gte($endDate)) {
                throw new Exception('Date range start must be before date range end');
            }
        }

        // Validate day of week for regular windows
        if ($data['window_type'] === 'regular' && isset($data['day_of_week'])) {
            if ($data['day_of_week'] < 0 || $data['day_of_week'] > 6) {
                throw new Exception('Day of week must be between 0 (Sunday) and 6 (Saturday)');
            }
        }
    }

    /**
     * Check if two availability windows overlap
     */
    private function checkWindowOverlap(array $newWindow, array $existingWindow): array
    {
        $hasOverlap = false;
        $overlapType = '';
        $overlapDetails = [];

        // Check for different types of overlaps based on window types
        if ($newWindow['window_type'] === 'regular' && $existingWindow['window_type'] === 'regular') {
            // Regular window overlap - check day of week and times
            if (($newWindow['day_of_week'] ?? null) === ($existingWindow['day_of_week'] ?? null)) {
                $timeOverlap = $this->checkTimeOverlap($newWindow, $existingWindow);
                if ($timeOverlap) {
                    $hasOverlap = true;
                    $overlapType = 'time_overlap';
                    $overlapDetails['time_conflict'] = true;
                }
            }
        } elseif ($newWindow['window_type'] === 'specific_date' || $existingWindow['window_type'] === 'specific_date') {
            // Specific date overlap
            $dateOverlap = $this->checkDateOverlap($newWindow, $existingWindow);
            if ($dateOverlap) {
                $hasOverlap = true;
                $overlapType = 'date_overlap';
                $overlapDetails['date_conflict'] = true;
            }
        }

        return [
            'has_overlap' => $hasOverlap,
            'overlap_type' => $overlapType,
            'details' => $overlapDetails,
        ];
    }

    /**
     * Check time overlap between windows
     */
    private function checkTimeOverlap(array $window1, array $window2): bool
    {
        $start1 = $window1['earliest_access'] ?? null;
        $end1 = $window1['latest_departure'] ?? null;
        $start2 = $window2['earliest_access'] ?? null;
        $end2 = $window2['latest_departure'] ?? null;

        if (!$start1 || !$end1 || !$start2 || !$end2) {
            return false;
        }

        // Convert to comparable format if they're strings
        if (is_string($start1)) $start1 = Carbon::createFromFormat('H:i:s', $start1);
        if (is_string($end1)) $end1 = Carbon::createFromFormat('H:i:s', $end1);
        if (is_string($start2)) $start2 = Carbon::createFromFormat('H:i:s', $start2);
        if (is_string($end2)) $end2 = Carbon::createFromFormat('H:i:s', $end2);

        return $start1->lt($end2) && $start2->lt($end1);
    }

    /**
     * Check date overlap between windows
     */
    private function checkDateOverlap(array $window1, array $window2): bool
    {
        // Implementation for date range overlap checking
        $start1 = $window1['date_range_start'] ?? $window1['specific_date'] ?? null;
        $end1 = $window1['date_range_end'] ?? $window1['specific_date'] ?? null;
        $start2 = $window2['date_range_start'] ?? $window2['specific_date'] ?? null;
        $end2 = $window2['date_range_end'] ?? $window2['specific_date'] ?? null;

        if (!$start1 || !$end1 || !$start2 || !$end2) {
            return false;
        }

        $start1 = Carbon::parse($start1);
        $end1 = Carbon::parse($end1);
        $start2 = Carbon::parse($start2);
        $end2 = Carbon::parse($end2);

        return $start1->lte($end2) && $start2->lte($end1);
    }

    /**
     * Determine conflict severity
     */
    private function determineConflictSeverity(string $newType, string $existingType): string
    {
        // Maintenance windows have highest priority
        if ($newType === 'maintenance' || $existingType === 'maintenance') {
            return 'critical';
        }

        // Special events vs regular
        if (($newType === 'special_event' && $existingType === 'regular') ||
            ($newType === 'regular' && $existingType === 'special_event')) {
            return 'medium';
        }

        // Same types
        return 'high';
    }

    /**
     * Get bookings potentially affected by window changes
     */
    private function getPotentiallyAffectedBookings(VenueAvailabilityWindow $window, array $newData): Collection
    {
        $query = Booking::where('service_location_id', $window->service_location_id)
            ->whereNotIn('status', [BookingStatuses::CANCELLED, BookingStatuses::COMPLETED]);

        // Add date filtering based on window type
        if ($window->window_type === 'regular') {
            // For regular windows, check recurring pattern
            $dayOfWeek = $newData['day_of_week'] ?? $window->day_of_week;
            if ($dayOfWeek !== null) {
                $query->whereRaw('DAYOFWEEK(scheduled_at) - 1 = ?', [$dayOfWeek]);
            }
        } elseif ($window->specific_date) {
            // For specific date windows
            $query->whereDate('scheduled_at', $window->specific_date);
        }

        return $query->get();
    }

    /**
     * Analyze booking impact for a specific booking
     */
    private function analyzeBookingImpact(Booking $booking, VenueAvailabilityWindow $window, array $newData): array
    {
        $impact = [
            'booking_id' => $booking->id,
            'booking_reference' => $booking->booking_reference,
            'scheduled_at' => $booking->scheduled_at->toISOString(),
            'has_impact' => false,
            'impact_type' => '',
            'severity' => 'low',
            'recommended_action' => 'none',
            'details' => [],
        ];

        // Check if booking falls outside new time restrictions
        $timeImpact = $this->checkBookingTimeImpact($booking, $newData);
        if ($timeImpact['has_impact']) {
            $impact['has_impact'] = true;
            $impact['impact_type'] = 'time_restriction';
            $impact['severity'] = 'high';
            $impact['recommended_action'] = 'manual_review';
            $impact['details'] = array_merge($impact['details'], $timeImpact['details']);
        }

        return $impact;
    }

    /**
     * Check if booking is affected by time changes
     */
    private function checkBookingTimeImpact(Booking $booking, array $newData): array
    {
        $impact = ['has_impact' => false, 'details' => []];

        // Check earliest access restriction
        if (isset($newData['earliest_access'])) {
            $earliestAccess = $newData['earliest_access'];
            $bookingTime = $booking->scheduled_at->format('H:i:s');

            if ($bookingTime < $earliestAccess->format('H:i:s')) {
                $impact['has_impact'] = true;
                $impact['details'][] = 'Booking starts before earliest access time';
            }
        }

        // Check latest departure restriction
        if (isset($newData['latest_departure'])) {
            $latestDeparture = $newData['latest_departure'];
            $bookingEndTime = $booking->ends_at->format('H:i:s');

            if ($bookingEndTime > $latestDeparture->format('H:i:s')) {
                $impact['has_impact'] = true;
                $impact['details'][] = 'Booking ends after latest departure time';
            }
        }

        return $impact;
    }

    /**
     * Find bookings that depend on a specific availability window
     */
    private function findDependentBookings(VenueAvailabilityWindow $window): Collection
    {
        $query = Booking::where('service_location_id', $window->service_location_id)
            ->whereIn('status', [BookingStatuses::PENDING, BookingStatuses::CONFIRMED])
            ->where('scheduled_at', '>', now());

        // Filter based on window type and schedule
        if ($window->window_type === 'regular' && $window->day_of_week !== null) {
            $query->whereRaw('DAYOFWEEK(scheduled_at) - 1 = ?', [$window->day_of_week]);
        } elseif ($window->specific_date) {
            $query->whereDate('scheduled_at', $window->specific_date);
        } elseif ($window->date_range_start && $window->date_range_end) {
            $query->whereBetween('scheduled_at', [$window->date_range_start, $window->date_range_end]);
        }

        return $query->get();
    }

    /**
     * Attempt to automatically reschedule a booking
     */
    private function attemptAutoReschedule(Booking $booking, User $admin): void
    {
        try {
            // Find alternative time slots
            $alternatives = $this->findAlternativeSlots($booking);

            if ($alternatives->isNotEmpty()) {
                $newSlot = $alternatives->first();

                // Update booking to new time
                $booking->update([
                    'scheduled_at' => $newSlot['start_time'],
                    'ends_at' => $newSlot['end_time'],
                ]);

                Log::info('Booking auto-rescheduled due to availability change', [
                    'booking_id' => $booking->id,
                    'original_time' => $booking->getOriginal('scheduled_at'),
                    'new_time' => $booking->scheduled_at,
                    'admin_id' => $admin->id,
                ]);

                // TODO: Send notification to customer about rescheduling
            } else {
                $this->flagForManualReview($booking, ['reason' => 'No suitable alternative slots found'], $admin);
            }

        } catch (Exception $e) {
            Log::error('Failed to auto-reschedule booking', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);

            $this->flagForManualReview($booking, ['reason' => 'Auto-reschedule failed'], $admin);
        }
    }

    /**
     * Flag booking for manual review
     */
    private function flagForManualReview(Booking $booking, array $impact, User $admin): void
    {
        // Add a note or status flag for manual review
        Log::warning('Booking flagged for manual review', [
            'booking_id' => $booking->id,
            'reason' => $impact['reason'] ?? 'Availability conflict',
            'admin_id' => $admin->id,
        ]);

        // TODO: Create a task or notification for admin team to review
    }

    /**
     * Handle forced cancellation of booking
     */
    private function handleForcedCancellation(Booking $booking, array $impact, User $admin): void
    {
        try {
            $booking->update([
                'status' => BookingStatuses::CANCELLED,
                'cancellation_reason' => 'Venue availability conflict - ' . ($impact['reason'] ?? 'Unknown'),
            ]);

            Log::info('Booking force-cancelled due to availability conflict', [
                'booking_id' => $booking->id,
                'reason' => $impact['reason'] ?? 'Venue availability conflict',
                'admin_id' => $admin->id,
            ]);

            // TODO: Process refund and send customer notification

        } catch (Exception $e) {
            Log::error('Failed to force-cancel booking', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Find scheduling conflicts for a window
     */
    private function findSchedulingConflicts(VenueAvailabilityWindow $window): array
    {
        $conflicts = [];

        // Check for overlapping windows
        $overlapping = VenueAvailabilityWindow::where('service_location_id', $window->service_location_id)
            ->where('id', '!=', $window->id)
            ->where('is_active', true)
            ->get();

        foreach ($overlapping as $other) {
            $overlap = $this->checkWindowOverlap($window->toArray(), $other->toArray());

            if ($overlap['has_overlap']) {
                $conflicts[] = [
                    'conflict_with' => $other->id,
                    'conflict_type' => $overlap['overlap_type'],
                    'severity' => $this->determineConflictSeverity($window->window_type, $other->window_type),
                    'details' => $overlap['details'],
                ];
            }
        }

        return $conflicts;
    }

    /**
     * Find booking conflicts for a window
     */
    private function findBookingConflicts(VenueAvailabilityWindow $window): array
    {
        $conflicts = [];

        // Find bookings that conflict with this availability window
        $conflictingBookings = $this->findDependentBookings($window);

        foreach ($conflictingBookings as $booking) {
            $conflict = $this->analyzeBookingWindowConflict($booking, $window);

            if ($conflict['has_conflict']) {
                $conflicts[] = $conflict;
            }
        }

        return $conflicts;
    }

    /**
     * Find capacity issues for a window
     */
    private function findCapacityIssues(VenueAvailabilityWindow $window): array
    {
        $issues = [];

        if ($window->max_concurrent_events) {
            // Check for times when concurrent events exceed limit
            $overCapacityPeriods = $this->findOverCapacityPeriods($window);

            foreach ($overCapacityPeriods as $period) {
                $issues[] = [
                    'issue_type' => 'over_capacity',
                    'period' => $period,
                    'severity' => 'high',
                ];
            }
        }

        return $issues;
    }

    /**
     * Analyze booking conflict with availability window
     */
    private function analyzeBookingWindowConflict(Booking $booking, VenueAvailabilityWindow $window): array
    {
        $conflict = [
            'booking_id' => $booking->id,
            'booking_reference' => $booking->booking_reference,
            'has_conflict' => false,
            'conflict_type' => '',
            'severity' => 'low',
        ];

        // Check time restrictions
        if ($window->earliest_access && $window->latest_departure) {
            $bookingStart = $booking->scheduled_at->format('H:i:s');
            $bookingEnd = $booking->ends_at->format('H:i:s');
            $windowStart = $window->earliest_access->format('H:i:s');
            $windowEnd = $window->latest_departure->format('H:i:s');

            if ($bookingStart < $windowStart || $bookingEnd > $windowEnd) {
                $conflict['has_conflict'] = true;
                $conflict['conflict_type'] = 'time_restriction';
                $conflict['severity'] = 'high';
            }
        }

        // Check quiet hours
        if ($window->quiet_hours_start && $window->quiet_hours_end) {
            $quietStart = $window->quiet_hours_start->format('H:i:s');
            $quietEnd = $window->quiet_hours_end->format('H:i:s');
            $bookingStart = $booking->scheduled_at->format('H:i:s');
            $bookingEnd = $booking->ends_at->format('H:i:s');

            if (($bookingStart >= $quietStart && $bookingStart <= $quietEnd) ||
                ($bookingEnd >= $quietStart && $bookingEnd <= $quietEnd)) {
                $conflict['has_conflict'] = true;
                $conflict['conflict_type'] = 'quiet_hours';
                $conflict['severity'] = 'medium';
            }
        }

        return $conflict;
    }

    /**
     * Find periods where capacity is exceeded
     */
    private function findOverCapacityPeriods(VenueAvailabilityWindow $window): array
    {
        $periods = [];

        if (!$window->max_concurrent_events) {
            return $periods;
        }

        // Get all bookings for this location within the window's timeframe
        $bookings = $this->getWindowBookings($window);

        // Group bookings by overlapping time periods
        $timeSlots = $this->groupBookingsByTimeOverlap($bookings);

        foreach ($timeSlots as $slot) {
            if (count($slot['bookings']) > $window->max_concurrent_events) {
                $periods[] = [
                    'start_time' => $slot['start_time'],
                    'end_time' => $slot['end_time'],
                    'concurrent_bookings' => count($slot['bookings']),
                    'max_allowed' => $window->max_concurrent_events,
                    'excess' => count($slot['bookings']) - $window->max_concurrent_events,
                ];
            }
        }

        return $periods;
    }

    /**
     * Get bookings that fall within a window's scope
     */
    private function getWindowBookings(VenueAvailabilityWindow $window): Collection
    {
        $query = Booking::where('service_location_id', $window->service_location_id)
            ->whereNotIn('status', [BookingStatuses::CANCELLED]);

        // Filter by window type and schedule
        if ($window->window_type === 'regular' && $window->day_of_week !== null) {
            $query->whereRaw('DAYOFWEEK(scheduled_at) - 1 = ?', [$window->day_of_week]);
        } elseif ($window->specific_date) {
            $query->whereDate('scheduled_at', $window->specific_date);
        } elseif ($window->date_range_start && $window->date_range_end) {
            $query->whereBetween('scheduled_at', [$window->date_range_start, $window->date_range_end]);
        }

        return $query->get();
    }

    /**
     * Group bookings by time overlap to find concurrent events
     */
    private function groupBookingsByTimeOverlap(Collection $bookings): array
    {
        $timeSlots = [];

        foreach ($bookings as $booking) {
            $placed = false;

            foreach ($timeSlots as &$slot) {
                // Check if this booking overlaps with any booking in this slot
                $overlaps = false;
                foreach ($slot['bookings'] as $slotBooking) {
                    if ($this->bookingsOverlap($booking, $slotBooking)) {
                        $overlaps = true;
                        break;
                    }
                }

                if ($overlaps) {
                    $slot['bookings'][] = $booking;
                    $slot['start_time'] = min($slot['start_time'], $booking->scheduled_at);
                    $slot['end_time'] = max($slot['end_time'], $booking->ends_at);
                    $placed = true;
                    break;
                }
            }

            if (!$placed) {
                $timeSlots[] = [
                    'start_time' => $booking->scheduled_at,
                    'end_time' => $booking->ends_at,
                    'bookings' => [$booking],
                ];
            }
        }

        return $timeSlots;
    }

    /**
     * Check if two bookings overlap in time
     */
    private function bookingsOverlap(Booking $booking1, Booking $booking2): bool
    {
        return $booking1->scheduled_at->lt($booking2->ends_at) &&
            $booking2->scheduled_at->lt($booking1->ends_at);
    }

    /**
     * Get statistics date range (last 30 days by default)
     */
    private function getStatsDateRange(): array
    {
        return [
            'start' => now()->subDays(30)->startOfDay(),
            'end' => now()->endOfDay(),
        ];
    }

    /**
     * Get booking count for a window within date range
     */
    private function getWindowBookingCount(VenueAvailabilityWindow $window, array $dateRange): int
    {
        return $this->getWindowBookings($window)
            ->whereBetween('scheduled_at', [$dateRange['start'], $dateRange['end']])
            ->count();
    }

    /**
     * Calculate utilization rate for a window
     */
    private function calculateUtilizationRate(VenueAvailabilityWindow $window, array $dateRange): float
    {
        $totalPossibleSlots = $this->calculateTotalPossibleSlots($window, $dateRange);
        $actualBookings = $this->getWindowBookingCount($window, $dateRange);

        return $totalPossibleSlots > 0 ? round(($actualBookings / $totalPossibleSlots) * 100, 1) : 0;
    }

    /**
     * Calculate total possible booking slots for a window
     */
    private function calculateTotalPossibleSlots(VenueAvailabilityWindow $window, array $dateRange): int
    {
        if ($window->window_type === 'regular' && $window->day_of_week !== null) {
            // Count occurrences of this day of week in the date range
            $start = $dateRange['start']->copy();
            $end = $dateRange['end']->copy();
            $count = 0;

            while ($start->lte($end)) {
                if ($start->dayOfWeek === $window->day_of_week) {
                    $count++;
                }
                $start->addDay();
            }

            return $count;
        }

        // For other window types, return simplified calculation
        return $dateRange['end']->diffInDays($dateRange['start']);
    }

    /**
     * Get average event duration for window bookings
     */
    private function getAverageEventDuration(VenueAvailabilityWindow $window, array $dateRange): int
    {
        $bookings = $this->getWindowBookings($window)
            ->whereBetween('scheduled_at', [$dateRange['start'], $dateRange['end']]);

        if ($bookings->isEmpty()) {
            return 0;
        }

        $totalDuration = $bookings->sum(function ($booking) {
            return $booking->ends_at->diffInMinutes($booking->scheduled_at);
        });

        return intval($totalDuration / $bookings->count());
    }

    /**
     * Get peak usage times for a window
     */
    private function getPeakUsageTimes(VenueAvailabilityWindow $window, array $dateRange): array
    {
        $bookings = $this->getWindowBookings($window)
            ->whereBetween('scheduled_at', [$dateRange['start'], $dateRange['end']]);

        $timeSlots = [];

        foreach ($bookings as $booking) {
            $hour = $booking->scheduled_at->format('H:00');
            if (!isset($timeSlots[$hour])) {
                $timeSlots[$hour] = 0;
            }
            $timeSlots[$hour]++;
        }

        arsort($timeSlots);

        return array_slice($timeSlots, 0, 3, true); // Top 3 peak times
    }

    /**
     * Get revenue generated through this window
     */
    private function getWindowRevenue(VenueAvailabilityWindow $window, array $dateRange): array
    {
        $bookings = $this->getWindowBookings($window)
            ->whereBetween('scheduled_at', [$dateRange['start'], $dateRange['end']])
            ->where('status', BookingStatuses::COMPLETED);

        $totalRevenue = $bookings->sum('total_amount');

        return [
            'total_revenue' => $totalRevenue,
            'formatted_revenue' => '£' . number_format($totalRevenue / 100, 2),
            'booking_count' => $bookings->count(),
            'average_booking_value' => $bookings->count() > 0 ? $totalRevenue / $bookings->count() : 0,
        ];
    }

    /**
     * Get common issues for a window
     */
    private function getCommonIssues(VenueAvailabilityWindow $window, array $dateRange): array
    {
        $issues = [];

        // Check for frequent cancellations
        $cancellations = $this->getWindowBookings($window)
            ->whereBetween('scheduled_at', [$dateRange['start'], $dateRange['end']])
            ->where('status', BookingStatuses::CANCELLED)
            ->count();

        if ($cancellations > 0) {
            $issues[] = [
                'type' => 'cancellations',
                'count' => $cancellations,
                'description' => "High cancellation rate ({$cancellations} cancellations)",
            ];
        }

        // Check for capacity conflicts
        $capacityIssues = $this->findCapacityIssues($window);
        if (!empty($capacityIssues)) {
            $issues[] = [
                'type' => 'capacity_conflicts',
                'count' => count($capacityIssues),
                'description' => 'Capacity exceeded on multiple occasions',
            ];
        }

        return $issues;
    }

    /**
     * Generate available slots for a location and date range
     */
    private function generateAvailableSlots(
        ServiceLocation $location,
        Carbon $startDate,
        Carbon $endDate,
        int $durationMinutes,
        array $options
    ): SupportCollection {
        $slots = collect();

        // Get all availability windows for this location
        $availabilityWindows = VenueAvailabilityWindow::where('service_location_id', $location->id)
            ->where('is_active', true)
            ->get();

        // Get existing bookings to exclude
        $existingBookings = Booking::where('service_location_id', $location->id)
            ->whereBetween('scheduled_at', [$startDate, $endDate])
            ->whereNotIn('status', [BookingStatuses::CANCELLED])
            ->get();

        $current = $startDate->copy();

        while ($current->lte($endDate)) {
            $daySlots = $this->generateDaySlots($location, $current, $durationMinutes, $availabilityWindows, $existingBookings);
            $slots = $slots->concat($daySlots);
            $current->addDay();
        }

        return $slots->sortBy('start_time');
    }

    /**
     * Generate available slots for a specific day
     */
    private function generateDaySlots(
        ServiceLocation $location,
        Carbon $date,
        int $durationMinutes,
        Collection $availabilityWindows,
        Collection $existingBookings
    ): SupportCollection {
        $slots = collect();

        // Find applicable availability windows for this day
        $dayWindows = $availabilityWindows->filter(function ($window) use ($date) {
            return $this->windowAppliesToDate($window, $date);
        });

        if ($dayWindows->isEmpty()) {
            return $slots;
        }

        foreach ($dayWindows as $window) {
            $windowSlots = $this->generateWindowSlots($window, $date, $durationMinutes, $existingBookings);
            $slots = $slots->concat($windowSlots);
        }

        return $slots->unique('start_time');
    }

    /**
     * Check if an availability window applies to a specific date
     */
    private function windowAppliesToDate(VenueAvailabilityWindow $window, Carbon $date): bool
    {
        switch ($window->window_type) {
            case 'regular':
                return $window->day_of_week === $date->dayOfWeek;

            case 'specific_date':
                return $window->specific_date && $date->isSameDay($window->specific_date);

            case 'seasonal':
            case 'special_event':
                if ($window->date_range_start && $window->date_range_end) {
                    return $date->between($window->date_range_start, $window->date_range_end);
                }
                return false;

            case 'maintenance':
                // Maintenance windows block availability
                return false;

            default:
                return false;
        }
    }

    /**
     * Generate slots for a specific window and date
     */
    private function generateWindowSlots(
        VenueAvailabilityWindow $window,
        Carbon $date,
        int $durationMinutes,
        Collection $existingBookings
    ): SupportCollection {
        $slots = collect();

        if (!$window->earliest_access || !$window->latest_departure) {
            return $slots;
        }

        $slotStart = $date->copy()->setTimeFromTimeString($window->earliest_access->format('H:i:s'));
        $windowEnd = $date->copy()->setTimeFromTimeString($window->latest_departure->format('H:i:s'));

        // Generate 30-minute slots
        while ($slotStart->copy()->addMinutes($durationMinutes)->lte($windowEnd)) {
            $slotEnd = $slotStart->copy()->addMinutes($durationMinutes);

            // Check if slot conflicts with existing bookings
            $hasConflict = $existingBookings->filter(function ($booking) use ($slotStart, $slotEnd) {
                return $slotStart->lt($booking->ends_at) && $slotEnd->gt($booking->scheduled_at);
            })->isNotEmpty();

            // Check quiet hours restriction
            $inQuietHours = $this->slotInQuietHours($slotStart, $slotEnd, $window);

            if (!$hasConflict && !$inQuietHours) {
                $slots->push([
                    'start_time' => $slotStart->copy(),
                    'end_time' => $slotEnd->copy(),
                    'duration_minutes' => $durationMinutes,
                    'window_id' => $window->id,
                    'window_type' => $window->window_type,
                    'restrictions' => $window->restrictions ?? [],
                ]);
            }

            $slotStart->addMinutes(30); // 30-minute intervals
        }

        return $slots;
    }

    /**
     * Check if a slot falls within quiet hours
     */
    private function slotInQuietHours(Carbon $slotStart, Carbon $slotEnd, VenueAvailabilityWindow $window): bool
    {
        if (!$window->quiet_hours_start || !$window->quiet_hours_end) {
            return false;
        }

        $quietStart = $slotStart->copy()->setTimeFromTimeString($window->quiet_hours_start->format('H:i:s'));
        $quietEnd = $slotStart->copy()->setTimeFromTimeString($window->quiet_hours_end->format('H:i:s'));

        return $slotStart->lt($quietEnd) && $slotEnd->gt($quietStart);
    }

    /**
     * Get location day availability for public display
     */
    private function getLocationDayAvailability(ServiceLocation $location, Carbon $date, int $eventDuration): array
    {
        $availableSlots = $this->getAvailableSlots($location, $date, $date->copy()->endOfDay(), $eventDuration);

        return [
            'is_available' => $availableSlots->isNotEmpty(),
            'slots' => $availableSlots->map(function ($slot) {
                return [
                    'start_time' => $slot['start_time']->format('H:i'),
                    'end_time' => $slot['end_time']->format('H:i'),
                    'duration_minutes' => $slot['duration_minutes'],
                ];
            })->toArray(),
            'restrictions' => $this->getDayRestrictions($location, $date),
            'notes' => $this->getDayNotes($location, $date),
        ];
    }

    /**
     * Get restrictions for a specific day
     */
    private function getDayRestrictions(ServiceLocation $location, Carbon $date): array
    {
        $restrictions = [];

        $dayWindows = VenueAvailabilityWindow::where('service_location_id', $location->id)
            ->where('is_active', true)
            ->get()
            ->filter(function ($window) use ($date) {
                return $this->windowAppliesToDate($window, $date);
            });

        foreach ($dayWindows as $window) {
            if ($window->restrictions) {
                $restrictions = array_merge($restrictions, $window->restrictions);
            }

            if ($window->quiet_hours_start && $window->quiet_hours_end) {
                $restrictions[] = "Quiet hours: {$window->quiet_hours_start->format('H:i')} - {$window->quiet_hours_end->format('H:i')}";
            }
        }

        return array_unique($restrictions);
    }

    /**
     * Get notes for a specific day
     */
    private function getDayNotes(ServiceLocation $location, Carbon $date): array
    {
        $notes = [];

        $dayWindows = VenueAvailabilityWindow::where('service_location_id', $location->id)
            ->where('is_active', true)
            ->get()
            ->filter(function ($window) use ($date) {
                return $this->windowAppliesToDate($window, $date);
            });

        foreach ($dayWindows as $window) {
            if ($window->notes) {
                $notes[] = $window->notes;
            }
        }

        return $notes;
    }

    /**
     * Generate calendar summary
     */
    private function generateCalendarSummary(array $calendar): array
    {
        $totalDays = count($calendar);
        $availableDays = count(array_filter($calendar, fn($day) => $day['is_available']));
        $totalSlots = array_sum(array_column($calendar, 'total_slots'));

        return [
            'total_days' => $totalDays,
            'available_days' => $availableDays,
            'availability_rate' => $totalDays > 0 ? round(($availableDays / $totalDays) * 100, 1) : 0,
            'total_available_slots' => $totalSlots,
            'average_slots_per_day' => $availableDays > 0 ? round($totalSlots / $availableDays, 1) : 0,
        ];
    }

    /**
     * Find alternative slots for rescheduling
     */
    private function findAlternativeSlots(Booking $booking): SupportCollection
    {
        $originalDate = $booking->scheduled_at;
        $duration = $booking->ends_at->diffInMinutes($booking->scheduled_at);

        // Look for slots within 7 days of original booking
        $searchStart = $originalDate->copy()->subDays(3);
        $searchEnd = $originalDate->copy()->addDays(7);

        return $this->getAvailableSlots($booking->serviceLocation, $searchStart, $searchEnd, $duration);
    }

    /**
     * Clear availability-related caches
     */
    private function clearAvailabilityCaches(ServiceLocation $serviceLocation): void
    {
        $cacheKeys = [
            "venue_availability_{$serviceLocation->id}",
            "venue_slots_{$serviceLocation->id}_*",
            "venue_calendar_{$serviceLocation->id}_*",
        ];

        foreach ($cacheKeys as $pattern) {
            if (str_contains($pattern, '*')) {
                // Clear pattern-based cache keys
                $prefix = str_replace('*', '', $pattern);
                Cache::flush(); // For simplicity, flush all cache. In production, use more targeted approach
            } else {
                Cache::forget($pattern);
            }
        }
    }

    /**
     * Log significant availability changes
     */
    private function logAvailabilityChanges(VenueAvailabilityWindow $window, array $original, array $updated): void
    {
        $significantFields = [
            'window_type', 'earliest_access', 'latest_departure',
            'max_concurrent_events', 'is_active'
        ];

        $changes = [];
        foreach ($significantFields as $field) {
            if (isset($updated[$field]) && ($original[$field] ?? null) !== $updated[$field]) {
                $changes[$field] = [
                    'from' => $original[$field] ?? null,
                    'to' => $updated[$field],
                ];
            }
        }

        if (!empty($changes)) {
            Log::info('Significant availability window changes', [
                'window_id' => $window->id,
                'changes' => $changes,
            ]);
        }
    }
}
