<?php

namespace App\Services\V1\Bookings;

use App\Models\Service;
use App\Models\ServiceLocation;
use App\Models\ServiceAvailabilityWindow;
use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class TimeSlotService
{
    /**
     * Get available time slots for a service on a specific date
     */
    public function getAvailableSlots(
        Service $service,
        Carbon $date,
        ?ServiceLocation $location = null
    ): array {
        // Get all availability windows for this service and date
        $windows = $this->getAvailabilityWindows($service, $date, $location);

        if ($windows->isEmpty()) {
            return [];
        }

        $allSlots = [];

        foreach ($windows as $window) {
            $windowSlots = $window->getAvailableSlots($date);

            foreach ($windowSlots as $slot) {
                // Apply any price modifiers from the window
                $slot['base_price'] = $service->base_price;
                $slot['modified_price'] = $window->calculatePriceForSlot($service->base_price);
                $slot['price_difference'] = $slot['modified_price'] - $slot['base_price'];
                $slot['formatted_price'] = '£' . number_format($slot['modified_price'] / 100, 2);
                $slot['window_id'] = $window->id;
                $slot['window_title'] = $window->title;

                $allSlots[] = $slot;
            }
        }

        // Sort slots by start time
        usort($allSlots, fn($a, $b) => $a['start_datetime']->timestamp <=> $b['start_datetime']->timestamp);

        // Remove any overlapping slots, keeping the one with higher priority
        return $this->resolveOverlappingSlots($allSlots);
    }

    /**
     * Check if a specific time slot is available
     */
    public function isSlotAvailable(
        Service $service,
        Carbon $startTime,
        int $durationMinutes,
        ?ServiceLocation $location = null
    ): bool {
        $endTime = $startTime->clone()->addMinutes($durationMinutes);

        // Find applicable availability window
        $window = $this->findAvailabilityWindow($service, $startTime, $location);

        if (!$window) {
            return false;
        }

        // Check if the slot fits within the window
        $windowStart = Carbon::parse($window->start_time)->setDate(
            $startTime->year,
            $startTime->month,
            $startTime->day
        );

        $windowEnd = Carbon::parse($window->end_time)->setDate(
            $startTime->year,
            $startTime->month,
            $startTime->day
        );

        // Handle overnight windows
        if ($windowEnd->lessThan($windowStart)) {
            $windowEnd->addDay();
        }

        if ($startTime->lessThan($windowStart) || $endTime->greaterThan($windowEnd)) {
            return false;
        }

        // Check capacity
        return $window->getSlotBookingCount($startTime) < $window->max_bookings;
    }

    /**
     * Reserve a time slot (create booking)
     */
    public function reserveSlot(
        Service $service,
        Carbon $startTime,
        int $durationMinutes,
        array $bookingData,
        ?ServiceLocation $location = null
    ): ?Booking {
        if (!$this->isSlotAvailable($service, $startTime, $durationMinutes, $location)) {
            throw new \Exception('Time slot is not available');
        }

        $endTime = $startTime->clone()->addMinutes($durationMinutes);

        // Find the applicable window for pricing
        $window = $this->findAvailabilityWindow($service, $startTime, $location);
        $basePrice = $window ? $window->calculatePriceForSlot($service->base_price) : $service->base_price;

        // Calculate deposit if required
        $depositAmount = null;
        $remainingAmount = null;

        if ($service->requires_deposit) {
            $depositAmount = $service->getDepositAmountAttribute();
            $remainingAmount = $basePrice - $depositAmount;
        }

        $booking = Booking::create(array_merge($bookingData, [
            'service_id' => $service->id,
            'service_location_id' => $location?->id,
            'scheduled_at' => $startTime,
            'ends_at' => $endTime,
            'duration_minutes' => $durationMinutes,
            'base_price' => $basePrice,
            'total_amount' => $basePrice, // Will be updated with add-ons
            'deposit_amount' => $depositAmount,
            'remaining_amount' => $remainingAmount,
        ]));

        return $booking;
    }

    /**
     * Get next available slot for a service
     */
    public function getNextAvailableSlot(
        Service $service,
        ?Carbon $fromDate = null,
        ?ServiceLocation $location = null
    ): ?array {
        $searchDate = $fromDate ?? now()->addDay();
        $maxSearchDays = 30; // Limit search to prevent infinite loops

        for ($i = 0; $i < $maxSearchDays; $i++) {
            $currentDate = $searchDate->clone()->addDays($i);
            $slots = $this->getAvailableSlots($service, $currentDate, $location);

            if (!empty($slots)) {
                return $slots[0]; // Return first available slot
            }
        }

        return null;
    }

    /**
     * Get availability summary for a date range
     */
    public function getAvailabilitySummary(
        Service $service,
        Carbon $startDate,
        Carbon $endDate,
        ?ServiceLocation $location = null
    ): array {
        $summary = [];

        $currentDate = $startDate->clone();
        while ($currentDate->lessThanOrEqualTo($endDate)) {
            $slots = $this->getAvailableSlots($service, $currentDate, $location);

            $summary[] = [
                'date' => $currentDate->format('Y-m-d'),
                'formatted_date' => $currentDate->format('l, F j, Y'),
                'available_slots_count' => count(array_filter($slots, fn($slot) => $slot['is_available'])),
                'total_slots_count' => count($slots),
                'is_fully_booked' => count($slots) > 0 && count(array_filter($slots, fn($slot) => $slot['is_available'])) === 0,
                'has_availability' => count(array_filter($slots, fn($slot) => $slot['is_available'])) > 0,
                'earliest_slot' => !empty($slots) ? $slots[0]['start_time'] : null,
                'latest_slot' => !empty($slots) ? end($slots)['start_time'] : null,
            ];

            $currentDate->addDay();
        }

        return $summary;
    }

    /**
     * Get conflicting bookings for a time slot
     */
    public function getConflictingBookings(
        Service $service,
        Carbon $startTime,
        int $durationMinutes,
        ?ServiceLocation $location = null,
        ?int $excludeBookingId = null
    ): Collection {
        $endTime = $startTime->clone()->addMinutes($durationMinutes);

        $query = Booking::where('service_id', $service->id)
            ->whereIn('status', ['confirmed', 'in_progress'])
            ->where(function ($q) use ($startTime, $endTime) {
                $q->where(function ($sq) use ($startTime, $endTime) {
                    // Booking starts during this slot
                    $sq->where('scheduled_at', '>=', $startTime)
                        ->where('scheduled_at', '<', $endTime);
                })->orWhere(function ($sq) use ($startTime, $endTime) {
                    // Booking ends during this slot
                    $sq->where('ends_at', '>', $startTime)
                        ->where('ends_at', '<=', $endTime);
                })->orWhere(function ($sq) use ($startTime, $endTime) {
                    // Booking encompasses this slot
                    $sq->where('scheduled_at', '<=', $startTime)
                        ->where('ends_at', '>=', $endTime);
                });
            });

        if ($location) {
            $query->where('service_location_id', $location->id);
        }

        if ($excludeBookingId) {
            $query->where('id', '!=', $excludeBookingId);
        }

        return $query->get();
    }

    /**
     * Private helper methods
     */
    private function getAvailabilityWindows(
        Service $service,
        Carbon $date,
        ?ServiceLocation $location = null
    ): Collection {
        $query = ServiceAvailabilityWindow::where('service_id', $service->id)
            ->active()
            ->bookable()
            ->forDate($date);

        if ($location) {
            $query->where(function ($q) use ($location) {
                $q->where('service_location_id', $location->id)
                    ->orWhereNull('service_location_id');
            });
        } else {
            $query->whereNull('service_location_id');
        }

        return $query->get();
    }

    private function findAvailabilityWindow(
        Service $service,
        Carbon $startTime,
        ?ServiceLocation $location = null
    ): ?ServiceAvailabilityWindow {
        $windows = $this->getAvailabilityWindows($service, $startTime, $location);

        foreach ($windows as $window) {
            if ($window->isSlotAvailable($startTime)) {
                return $window;
            }
        }

        return null;
    }

    private function resolveOverlappingSlots(array $slots): array
    {
        if (empty($slots)) {
            return $slots;
        }

        $resolved = [];
        $lastEndTime = null;

        foreach ($slots as $slot) {
            $slotStart = $slot['start_datetime'];

            // If this slot doesn't overlap with the previous one, add it
            if ($lastEndTime === null || $slotStart->greaterThanOrEqualTo($lastEndTime)) {
                $resolved[] = $slot;
                $lastEndTime = $slot['end_datetime'];
            }
            // If there's an overlap, keep the slot with the earlier start time (already sorted)
            // or apply other priority rules here if needed
        }

        return $resolved;
    }

    /**
     * Calculate busy periods for calendar display
     */
    public function getBusyPeriods(
        Service $service,
        Carbon $date,
        ?ServiceLocation $location = null
    ): array {
        $bookings = Booking::where('service_id', $service->id)
            ->whereDate('scheduled_at', $date)
            ->whereIn('status', ['confirmed', 'in_progress'])
            ->when($location, function ($query, $location) {
                return $query->where('service_location_id', $location->id);
            })
            ->orderBy('scheduled_at')
            ->get();

        return $bookings->map(function ($booking) {
            return [
                'start' => $booking->scheduled_at->format('H:i'),
                'end' => $booking->ends_at->format('H:i'),
                'start_datetime' => $booking->scheduled_at,
                'end_datetime' => $booking->ends_at,
                'booking_id' => $booking->id,
                'booking_reference' => $booking->booking_reference,
                'client_name' => $booking->client_name,
                'status' => $booking->status,
            ];
        })->toArray();
    }

    /**
     * Validate booking time constraints
     */
    public function validateBookingTime(
        Service $service,
        Carbon $requestedTime,
        ?ServiceLocation $location = null
    ): array {
        $errors = [];
        $now = now();

        // Check if time is in the past
        if ($requestedTime->isPast()) {
            $errors[] = 'Cannot book appointments in the past';
        }

        // Check minimum advance booking time
        $minAdvanceHours = $service->min_advance_booking_hours;
        if ($requestedTime->lessThan($now->addHours($minAdvanceHours))) {
            $errors[] = "Minimum {$minAdvanceHours} hours advance booking required";
        }

        // Check maximum advance booking time
        $maxAdvanceDays = $service->max_advance_booking_days;
        if ($requestedTime->greaterThan($now->addDays($maxAdvanceDays))) {
            $errors[] = "Cannot book more than {$maxAdvanceDays} days in advance";
        }

        // Check if service is active
        if (!$service->isAvailableForBooking()) {
            $errors[] = 'Service is currently not available for booking';
        }

        // Check location availability if specified
        if ($location && !$location->is_active) {
            $errors[] = 'Selected location is not available';
        }

        return $errors;
    }
}

