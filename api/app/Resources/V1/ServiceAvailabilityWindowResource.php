<?php

namespace App\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class ServiceAvailabilityWindowResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'service_id' => $this->service_id,
            'service_location_id' => $this->service_location_id,

            // Window type and pattern
            'type' => $this->type,
            'pattern' => $this->pattern,
            'type_display' => $this->getTypeDisplayName(),
            'pattern_display' => $this->getPatternDisplayName(),

            // Day and date information
            'day_of_week' => $this->day_of_week,
            'day_name' => $this->getDayName(),
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,

            // Time slots
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'formatted_time_range' => $this->formatTimeRange(),

            // Capacity and booking rules
            'max_bookings' => $this->max_bookings,
            'slot_duration_minutes' => $this->slot_duration_minutes,
            'break_duration_minutes' => $this->break_duration_minutes,
            'min_advance_booking_hours' => $this->min_advance_booking_hours,
            'max_advance_booking_days' => $this->max_advance_booking_days,

            // Status
            'is_active' => $this->is_active,
            'is_bookable' => $this->is_bookable,

            // Pricing modifications
            'price_modifier' => $this->price_modifier,
            'price_modifier_type' => $this->price_modifier_type,
            'formatted_price_modifier' => $this->getFormattedPriceModifier(),

            // Display information
            'title' => $this->title,
            'description' => $this->description,
            'metadata' => $this->metadata,

            // Service relationship
            'service' => $this->whenLoaded('service', function () {
                return [
                    'id' => $this->service->id,
                    'name' => $this->service->name,
                    'category' => $this->service->category,
                ];
            }),

            // Location relationship
            'location' => $this->whenLoaded('serviceLocation', function () {
                return [
                    'id' => $this->serviceLocation->id,
                    'name' => $this->serviceLocation->name,
                    'type' => $this->serviceLocation->type,
                ];
            }),

            // Time information
            'duration_info' => [
                'total_duration_minutes' => $this->getTotalDurationMinutes(),
                'formatted_duration' => $this->formatDuration($this->getTotalDurationMinutes()),
                'break_time' => $this->break_duration_minutes,
                'formatted_break' => $this->formatDuration($this->break_duration_minutes),
                'effective_time_per_slot' => $this->slot_duration_minutes ?: $this->service?->duration_minutes,
            ],

            // Availability status
            'availability_status' => [
                'is_active' => $this->is_active,
                'is_bookable' => $this->is_bookable,
                'is_current' => $this->isCurrent(),
                'is_future' => $this->isFuture(),
                'is_expired' => $this->isExpired(),
                'status_display' => $this->getStatusDisplay(),
            ],

            // Capacity information
            'capacity_info' => [
                'max_concurrent_bookings' => $this->max_bookings,
                'allows_overlap' => false, // Based on business rules
                'booking_buffer' => $this->break_duration_minutes,
                'advance_booking_window' => [
                    'min_hours' => $this->min_advance_booking_hours,
                    'max_days' => $this->max_advance_booking_days,
                ],
            ],

            // Timestamps
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'created_at_human' => $this->created_at?->diffForHumans(),
            'updated_at_human' => $this->updated_at?->diffForHumans(),
        ];
    }

    /**
     * Get the day name from day of week number
     */
    private function getDayName(): ?string
    {
        if ($this->day_of_week === null) {
            return null;
        }

        $days = [
            0 => 'Sunday',
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
        ];

        return $days[$this->day_of_week] ?? 'Unknown';
    }

    /**
     * Format the time range
     */
    private function formatTimeRange(): string
    {
        if (!$this->start_time || !$this->end_time) {
            return 'Time not set';
        }

        $start = Carbon::parse($this->start_time)->format('H:i');
        $end = Carbon::parse($this->end_time)->format('H:i');

        return "{$start} - {$end}";
    }

    /**
     * Get total duration in minutes
     */
    private function getTotalDurationMinutes(): int
    {
        if (!$this->start_time || !$this->end_time) {
            return 0;
        }

        $start = Carbon::parse($this->start_time);
        $end = Carbon::parse($this->end_time);

        return $end->diffInMinutes($start);
    }

    /**
     * Format duration in human readable format
     */
    private function formatDuration(int $minutes): string
    {
        if ($minutes <= 0) {
            return '0 minutes';
        }

        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;

        if ($hours > 0 && $remainingMinutes > 0) {
            return "{$hours}h {$remainingMinutes}m";
        } elseif ($hours > 0) {
            return "{$hours}h";
        } else {
            return "{$remainingMinutes}m";
        }
    }

    /**
     * Get type display name
     */
    private function getTypeDisplayName(): string
    {
        return match($this->type) {
            'regular' => 'Regular Hours',
            'exception' => 'Exception',
            'special_hours' => 'Special Hours',
            'blocked' => 'Blocked Time',
            default => ucfirst($this->type)
        };
    }

    /**
     * Get pattern display name
     */
    private function getPatternDisplayName(): string
    {
        return match($this->pattern) {
            'weekly' => 'Weekly Recurring',
            'daily' => 'Daily',
            'date_range' => 'Date Range',
            'specific_date' => 'Specific Date',
            default => ucfirst(str_replace('_', ' ', $this->pattern))
        };
    }

    /**
     * Get formatted price modifier
     */
    private function getFormattedPriceModifier(): ?string
    {
        if (!$this->price_modifier) {
            return null;
        }

        if ($this->price_modifier_type === 'percentage') {
            $sign = $this->price_modifier > 0 ? '+' : '';
            return "{$sign}{$this->price_modifier}%";
        } else {
            $amount = abs($this->price_modifier) / 100;
            $sign = $this->price_modifier > 0 ? '+' : '-';
            return "{$sign}£" . number_format($amount, 2);
        }
    }

    /**
     * Check if window is currently active
     */
    private function isCurrent(): bool
    {
        $now = now();

        // For weekly patterns, check if today matches
        if ($this->pattern === 'weekly' && $this->day_of_week !== null) {
            return $now->dayOfWeek === $this->day_of_week && $this->isTimeWithinWindow($now);
        }

        // For date-based patterns
        if ($this->start_date && $this->end_date) {
            return $now->between($this->start_date, $this->end_date);
        }

        if ($this->start_date) {
            return $now->isSameDay($this->start_date);
        }

        return false;
    }

    /**
     * Check if window is in the future
     */
    private function isFuture(): bool
    {
        $now = now();

        if ($this->start_date) {
            return $this->start_date->isFuture();
        }

        // For weekly patterns, always consider as future if active
        if ($this->pattern === 'weekly' && $this->is_active) {
            return true;
        }

        return false;
    }

    /**
     * Check if window has expired
     */
    private function isExpired(): bool
    {
        if (!$this->is_active) {
            return true;
        }

        if ($this->end_date) {
            return $this->end_date->isPast();
        }

        if ($this->start_date && $this->pattern === 'specific_date') {
            return $this->start_date->isPast();
        }

        return false;
    }

    /**
     * Get status display
     */
    private function getStatusDisplay(): string
    {
        if (!$this->is_active) {
            return 'Inactive';
        }

        if (!$this->is_bookable) {
            return 'Not Bookable';
        }

        if ($this->isExpired()) {
            return 'Expired';
        }

        if ($this->isCurrent()) {
            return 'Active Now';
        }

        if ($this->isFuture()) {
            return 'Upcoming';
        }

        return 'Available';
    }

    /**
     * Check if current time is within this window
     */
    private function isTimeWithinWindow(Carbon $dateTime): bool
    {
        if (!$this->start_time || !$this->end_time) {
            return false;
        }

        $startTime = Carbon::parse($this->start_time);
        $endTime = Carbon::parse($this->end_time);
        $currentTime = Carbon::parse($dateTime->format('H:i:s'));

        return $currentTime->between($startTime, $endTime);
    }

    /**
     * Get available slots for a specific date
     */
    public function getAvailableSlotsForDate(Carbon $date): array
    {
        if (!$this->isAvailableForDate($date)) {
            return [];
        }

        $slots = [];
        $slotDuration = $this->slot_duration_minutes ?: ($this->service?->duration_minutes ?? 60);
        $breakDuration = $this->break_duration_minutes ?: 0;

        $startTime = Carbon::parse($date->format('Y-m-d') . ' ' . $this->start_time);
        $endTime = Carbon::parse($date->format('Y-m-d') . ' ' . $this->end_time);

        $currentSlot = $startTime->clone();

        while ($currentSlot->clone()->addMinutes($slotDuration)->lte($endTime)) {
            $slots[] = [
                'start_time' => $currentSlot->clone(),
                'end_time' => $currentSlot->clone()->addMinutes($slotDuration),
                'duration_minutes' => $slotDuration,
                'is_available' => true, // Would need to check against existing bookings
            ];

            $currentSlot->addMinutes($slotDuration + $breakDuration);
        }

        return $slots;
    }

    /**
     * Check if this window is available for a specific date
     */
    private function isAvailableForDate(Carbon $date): bool
    {
        if (!$this->is_active || !$this->is_bookable) {
            return false;
        }

        switch ($this->pattern) {
            case 'weekly':
                return $this->day_of_week === $date->dayOfWeek;

            case 'specific_date':
                return $this->start_date && $date->isSameDay($this->start_date);

            case 'date_range':
                return $this->start_date && $this->end_date &&
                    $date->between($this->start_date, $this->end_date);

            case 'daily':
                return true; // Available every day

            default:
                return false;
        }
    }
}
