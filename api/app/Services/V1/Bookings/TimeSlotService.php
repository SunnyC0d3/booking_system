<?php

namespace App\Services\V1\Bookings;

use App\Constants\BookingStatuses;
use App\Models\BookingCapacitySlot;
use App\Models\CalendarIntegration;
use App\Models\Service;
use App\Models\ServiceLocation;
use App\Models\ServiceAvailabilityWindow;
use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TimeSlotService
{
    public function getAvailableSlots(
        Service $service,
        Carbon $startDate,
        Carbon $endDate,
        ?ServiceLocation $location = null,
        int $durationMinutes = null
    ): Collection {
        $durationMinutes = $durationMinutes ?? $service->duration_minutes;

        $cacheKey = "available_slots_{$service->id}_{$location?->id}_{$startDate->format('Y-m-d')}_{$endDate->format('Y-m-d')}_{$durationMinutes}";

        return Cache::remember($cacheKey, 300, function () use ($service, $startDate, $endDate, $location, $durationMinutes) {
            return $this->generateAvailableSlots($service, $startDate, $endDate, $location, $durationMinutes);
        });
    }

    private function generateAvailableSlots(
        Service $service,
        Carbon $startDate,
        Carbon $endDate,
        ?ServiceLocation $location,
        int $durationMinutes
    ): Collection {
        $availableSlots = collect();

        // Get all availability windows for the service
        $availabilityWindows = $this->getAvailabilityWindows($service, $location);

        // Get all exceptions for the date range
        $exceptions = $this->getAvailabilityExceptions($service, $startDate, $endDate, $location);

        // Get existing bookings for the date range
        $existingBookings = $this->getExistingBookings($service, $startDate, $endDate, $location);

        // Get calendar integrations that block time
        $blockedTimes = $this->getExternalCalendarBlocks($service, $startDate, $endDate);

        // Generate slots for each day
        $period = CarbonPeriod::create($startDate, $endDate);

        foreach ($period as $date) {
            $daySlots = $this->generateDaySlots(
                $service,
                $date,
                $availabilityWindows,
                $exceptions,
                $existingBookings,
                $blockedTimes,
                $location,
                $durationMinutes
            );

            $availableSlots = $availableSlots->merge($daySlots);
        }

        return $availableSlots->sortBy('start_time');
    }

    private function generateDaySlots(
        Service $service,
        Carbon $date,
        Collection $availabilityWindows,
        Collection $exceptions,
        Collection $existingBookings,
        Collection $blockedTimes,
        ?ServiceLocation $location,
        int $durationMinutes
    ): Collection {
        $daySlots = collect();
        $dayOfWeek = $date->dayOfWeek;

        // Check if this date has any exceptions
        $dayExceptions = $exceptions->where('exception_date', $date->format('Y-m-d'));

        // If day is completely blocked, return empty
        if ($dayExceptions->where('exception_type', 'blocked')->isNotEmpty()) {
            return $daySlots;
        }

        // Get applicable availability windows for this day
        $applicableWindows = $availabilityWindows->filter(function ($window) use ($date, $dayOfWeek) {
            return $this->isWindowApplicable($window, $date, $dayOfWeek);
        });

        if ($applicableWindows->isEmpty()) {
            return $daySlots;
        }

        foreach ($applicableWindows as $window) {
            $windowSlots = $this->generateWindowSlots(
                $service,
                $window,
                $date,
                $dayExceptions,
                $existingBookings,
                $blockedTimes,
                $location,
                $durationMinutes
            );

            $daySlots = $daySlots->merge($windowSlots);
        }

        return $daySlots;
    }

    private function generateWindowSlots(
        Service $service,
        ServiceAvailabilityWindow $window,
        Carbon $date,
        Collection $dayExceptions,
        Collection $existingBookings,
        Collection $blockedTimes,
        ?ServiceLocation $location,
        int $durationMinutes
    ): Collection {
        $slots = collect();

        // Get window times, potentially overridden by exceptions
        $windowTimes = $this->getEffectiveWindowTimes($window, $dayExceptions, $date);

        if (!$windowTimes) {
            return $slots;
        }

        $slotDuration = $window->slot_duration_minutes ?: $durationMinutes;
        $breakDuration = $window->break_duration_minutes ?: 0;
        $bufferDuration = $window->booking_buffer_minutes ?: 0;
        $maxConcurrent = $window->max_concurrent_bookings ?: 1;

        $currentTime = $windowTimes['start'];
        $endTime = $windowTimes['end'];

        while ($currentTime->clone()->addMinutes($durationMinutes)->lte($endTime)) {
            $slotEndTime = $currentTime->clone()->addMinutes($durationMinutes);

            // Check if this slot is available
            if ($this->isSlotAvailable(
                $service,
                $currentTime,
                $slotEndTime,
                $existingBookings,
                $blockedTimes,
                $location,
                $maxConcurrent
            )) {
                $slots->push([
                    'start_time' => $currentTime->clone(),
                    'end_time' => $slotEndTime->clone(),
                    'duration_minutes' => $durationMinutes,
                    'is_available' => true,
                    'max_capacity' => $maxConcurrent,
                    'current_bookings' => $this->countBookingsInSlot($currentTime, $slotEndTime, $existingBookings),
                    'price_modifier' => $this->getPriceModifier($window, $dayExceptions, $date),
                    'location_id' => $location?->id,
                ]);
            }

            // Move to next slot
            $currentTime->addMinutes($slotDuration + $breakDuration + $bufferDuration);
        }

        return $slots;
    }

    private function isWindowApplicable(ServiceAvailabilityWindow $window, Carbon $date, int $dayOfWeek): bool
    {
        // Check if window is active
        if (!$window->is_active || !$window->is_bookable) {
            return false;
        }

        // Check date range
        if ($window->start_date && $date->lt($window->start_date)) {
            return false;
        }

        if ($window->end_date && $date->gt($window->end_date)) {
            return false;
        }

        // Check day of week for weekly pattern
        if ($window->pattern === 'weekly' && $window->day_of_week !== $dayOfWeek) {
            return false;
        }

        // Check advance booking constraints
        $now = Carbon::now();
        $minAdvanceTime = $now->clone()->addHours($window->min_advance_booking_hours ?: 0);
        $maxAdvanceTime = $now->clone()->addDays($window->max_advance_booking_days ?: 365);

        if ($date->lt($minAdvanceTime->startOfDay()) || $date->gt($maxAdvanceTime->endOfDay())) {
            return false;
        }

        return true;
    }

    private function getEffectiveWindowTimes(
        ServiceAvailabilityWindow $window,
        Collection $dayExceptions,
        Carbon $date
    ): ?array {
        $startTime = $date->clone()->setTimeFromTimeString($window->start_time->format('H:i:s'));
        $endTime = $date->clone()->setTimeFromTimeString($window->end_time->format('H:i:s'));

        // Check for custom hours exception
        $customHoursException = $dayExceptions->where('exception_type', 'custom_hours')->first();

        if ($customHoursException && $customHoursException->start_time && $customHoursException->end_time) {
            $startTime = $date->clone()->setTimeFromTimeString($customHoursException->start_time);
            $endTime = $date->clone()->setTimeFromTimeString($customHoursException->end_time);
        }

        // Validate times
        if ($startTime->gte($endTime)) {
            return null;
        }

        return [
            'start' => $startTime,
            'end' => $endTime
        ];
    }

    private function isSlotAvailable(
        Service $service,
        Carbon $slotStart,
        Carbon $slotEnd,
        Collection $existingBookings,
        Collection $blockedTimes,
        ?ServiceLocation $location,
        int $maxConcurrent
    ): bool {
        // Check if slot is in the past
        if ($slotStart->lt(Carbon::now())) {
            return false;
        }

        // Check against existing bookings
        $conflictingBookings = $existingBookings->filter(function ($booking) use ($slotStart, $slotEnd) {
            $bookingStart = Carbon::parse($booking->scheduled_at);
            $bookingEnd = Carbon::parse($booking->ends_at);

            return $this->timesOverlap($slotStart, $slotEnd, $bookingStart, $bookingEnd);
        });

        // Check capacity
        if ($conflictingBookings->count() >= $maxConcurrent) {
            return false;
        }

        // Check against external calendar blocks
        $isBlocked = $blockedTimes->first(function ($block) use ($slotStart, $slotEnd) {
            return $this->timesOverlap($slotStart, $slotEnd, $block['start'], $block['end']);
        });

        if ($isBlocked) {
            return false;
        }

        // Check capacity slots table for manual blocks
        $capacitySlot = BookingCapacitySlot::where('service_id', $service->id)
            ->where('service_location_id', $location?->id)
            ->where('slot_datetime', $slotStart)
            ->first();

        if ($capacitySlot && ($capacitySlot->is_blocked || $capacitySlot->available_slots <= 0)) {
            return false;
        }

        return true;
    }

    private function timesOverlap(Carbon $start1, Carbon $end1, Carbon $start2, Carbon $end2): bool
    {
        return $start1->lt($end2) && $end1->gt($start2);
    }

    private function countBookingsInSlot(Carbon $slotStart, Carbon $slotEnd, Collection $existingBookings): int
    {
        return $existingBookings->filter(function ($booking) use ($slotStart, $slotEnd) {
            $bookingStart = Carbon::parse($booking->scheduled_at);
            $bookingEnd = Carbon::parse($booking->ends_at);

            return $this->timesOverlap($slotStart, $slotEnd, $bookingStart, $bookingEnd);
        })->count();
    }

    private function getPriceModifier(
        ServiceAvailabilityWindow $window,
        Collection $dayExceptions,
        Carbon $date
    ): array {
        // Check for special pricing exception first
        $pricingException = $dayExceptions->where('exception_type', 'special_pricing')->first();

        if ($pricingException) {
            return [
                'amount' => $pricingException->price_modifier,
                'type' => $pricingException->price_modifier_type,
                'reason' => $pricingException->reason
            ];
        }

        // Use window pricing
        if ($window->price_modifier) {
            return [
                'amount' => $window->price_modifier,
                'type' => $window->price_modifier_type,
                'reason' => $window->title
            ];
        }

        return [
            'amount' => 0,
            'type' => 'fixed',
            'reason' => null
        ];
    }

    private function getAvailabilityWindows(Service $service, ?ServiceLocation $location): Collection
    {
        return ServiceAvailabilityWindow::where('service_id', $service->id)
            ->when($location, function ($query) use ($location) {
                $query->where(function ($q) use ($location) {
                    $q->where('service_location_id', $location->id)
                        ->orWhereNull('service_location_id');
                });
            })
            ->active()
            ->bookable()
            ->orderBy('start_time')
            ->get();
    }

    private function getAvailabilityExceptions(
        Service $service,
        Carbon $startDate,
        Carbon $endDate,
        ?ServiceLocation $location
    ): Collection {
        return ServiceAvailabilityException::where('service_id', $service->id)
            ->when($location, function ($query) use ($location) {
                $query->where(function ($q) use ($location) {
                    $q->where('service_location_id', $location->id)
                        ->orWhereNull('service_location_id');
                });
            })
            ->whereBetween('exception_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->where('is_active', true)
            ->get();
    }

    private function getExistingBookings(
        Service $service,
        Carbon $startDate,
        Carbon $endDate,
        ?ServiceLocation $location
    ): Collection {
        return Booking::where('service_id', $service->id)
            ->when($location, function ($query) use ($location) {
                $query->where('service_location_id', $location->id);
            })
            ->whereBetween('scheduled_at', [$startDate->startOfDay(), $endDate->endOfDay()])
            ->whereNotIn('status', [BookingStatuses::CANCELLED, BookingStatuses::NO_SHOW])
            ->get();
    }

    private function getExternalCalendarBlocks(Service $service, Carbon $startDate, Carbon $endDate): Collection
    {
        $blocks = collect();

        // Get calendar integrations for this service
        $integrations = CalendarIntegration::where('service_id', $service->id)
            ->where('is_active', true)
            ->where('auto_block_external_events', true)
            ->get();

        foreach ($integrations as $integration) {
            // This would integrate with external calendar APIs
            // For now, return empty collection
            // TODO: Implement Google Calendar, Outlook API integration
        }

        return $blocks;
    }

    public function validateBookingTime(
        Service $service,
        Carbon $requestedTime,
        ?ServiceLocation $location = null,
        int $durationMinutes = null
    ): array {
        $errors = [];
        $durationMinutes = $durationMinutes ?? $service->duration_minutes;
        $endTime = $requestedTime->clone()->addMinutes($durationMinutes);

        // Check if time is in the past
        if ($requestedTime->lt(Carbon::now())) {
            $errors[] = 'Booking time cannot be in the past';
        }

        // Check advance booking constraints
        $now = Carbon::now();
        $minAdvanceHours = $service->min_advance_booking_hours ?? 24;
        $maxAdvanceDays = $service->max_advance_booking_days ?? 365;

        if ($requestedTime->lt($now->clone()->addHours($minAdvanceHours))) {
            $errors[] = "Bookings must be made at least {$minAdvanceHours} hours in advance";
        }

        if ($requestedTime->gt($now->clone()->addDays($maxAdvanceDays))) {
            $errors[] = "Bookings cannot be made more than {$maxAdvanceDays} days in advance";
        }

        // Check if service has availability windows for this time
        $availableSlots = $this->getAvailableSlots(
            $service,
            $requestedTime->clone()->startOfDay(),
            $requestedTime->clone()->endOfDay(),
            $location,
            $durationMinutes
        );

        $slotExists = $availableSlots->first(function ($slot) use ($requestedTime) {
            return $slot['start_time']->equalTo($requestedTime);
        });

        if (!$slotExists) {
            $errors[] = 'The requested time slot is not available';
        }

        // Check daily booking limits
        if ($service->max_bookings_per_day) {
            $dailyBookings = Booking::where('service_id', $service->id)
                ->whereDate('scheduled_at', $requestedTime->format('Y-m-d'))
                ->whereNotIn('status', [BookingStatuses::CANCELLED, BookingStatuses::NO_SHOW])
                ->count();

            if ($dailyBookings >= $service->max_bookings_per_day) {
                $errors[] = 'Maximum daily bookings limit reached for this service';
            }
        }

        // Check weekly booking limits
        if ($service->max_bookings_per_week) {
            $weekStart = $requestedTime->clone()->startOfWeek();
            $weekEnd = $requestedTime->clone()->endOfWeek();

            $weeklyBookings = Booking::where('service_id', $service->id)
                ->whereBetween('scheduled_at', [$weekStart, $weekEnd])
                ->whereNotIn('status', [BookingStatuses::CANCELLED, BookingStatuses::NO_SHOW])
                ->count();

            if ($weeklyBookings >= $service->max_bookings_per_week) {
                $errors[] = 'Maximum weekly bookings limit reached for this service';
            }
        }

        return $errors;
    }

    public function reserveTimeSlot(
        Service $service,
        Carbon $slotTime,
        int $durationMinutes,
        ?ServiceLocation $location = null
    ): bool {
        // Create or update capacity slot
        $capacitySlot = BookingCapacitySlot::firstOrCreate([
            'service_id' => $service->id,
            'service_location_id' => $location?->id,
            'slot_datetime' => $slotTime,
        ], [
            'max_capacity' => 1,
            'current_bookings' => 0,
        ]);

        if ($capacitySlot->available_slots > 0) {
            $capacitySlot->increment('current_bookings');

            Log::info('Time slot reserved', [
                'service_id' => $service->id,
                'slot_time' => $slotTime->toISOString(),
                'location_id' => $location?->id,
                'remaining_capacity' => $capacitySlot->available_slots - 1
            ]);

            return true;
        }

        return false;
    }

    public function releaseTimeSlot(
        Service $service,
        Carbon $slotTime,
        ?ServiceLocation $location = null
    ): void {
        $capacitySlot = BookingCapacitySlot::where([
            'service_id' => $service->id,
            'service_location_id' => $location?->id,
            'slot_datetime' => $slotTime,
        ])->first();

        if ($capacitySlot && $capacitySlot->current_bookings > 0) {
            $capacitySlot->decrement('current_bookings');

            Log::info('Time slot released', [
                'service_id' => $service->id,
                'slot_time' => $slotTime->toISOString(),
                'location_id' => $location?->id,
                'available_capacity' => $capacitySlot->available_slots + 1
            ]);
        }
    }

    public function clearCache(Service $service, ?ServiceLocation $location = null): void
    {
        $patterns = [
            "available_slots_{$service->id}_{$location?->id}_*",
            "service_availability_{$service->id}_*"
        ];

        foreach ($patterns as $pattern) {
            Cache::forget($pattern);
        }

        Log::info('Availability cache cleared', [
            'service_id' => $service->id,
            'location_id' => $location?->id
        ]);
    }
}

