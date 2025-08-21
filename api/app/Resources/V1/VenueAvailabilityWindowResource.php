<?php

namespace App\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class VenueAvailabilityWindowResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'service_location_id' => $this->service_location_id,

            // Window type and identification
            'window_type' => $this->window_type,
            'window_type_display' => $this->getWindowTypeDisplay(),
            'window_description' => $this->getWindowDescription(),

            // Schedule information
            'day_of_week' => $this->day_of_week,
            'day_name' => $this->getDayName(),
            'specific_date' => $this->specific_date?->format('Y-m-d'),
            'specific_date_formatted' => $this->specific_date?->format('l, F j, Y'),
            'date_range_start' => $this->date_range_start?->format('Y-m-d'),
            'date_range_end' => $this->date_range_end?->format('Y-m-d'),
            'date_range_formatted' => $this->getFormattedDateRange(),

            // Time information
            'earliest_access' => $this->earliest_access?->format('H:i'),
            'latest_departure' => $this->latest_departure?->format('H:i'),
            'time_range_display' => $this->getTimeRangeDisplay(),
            'total_available_hours' => $this->getTotalAvailableHours(),

            // Quiet hours
            'quiet_hours_start' => $this->quiet_hours_start?->format('H:i'),
            'quiet_hours_end' => $this->quiet_hours_end?->format('H:i'),
            'quiet_hours_display' => $this->getQuietHoursDisplay(),
            'has_quiet_hours' => $this->hasQuietHours(),

            // Capacity and limits
            'max_concurrent_events' => $this->max_concurrent_events,
            'capacity_display' => $this->getCapacityDisplay(),

            // Restrictions and notes
            'restrictions' => $this->restrictions ?? [],
            'formatted_restrictions' => $this->getFormattedRestrictions(),
            'notes' => $this->notes,

            // Status and meta information
            'is_active' => $this->is_active,
            'status_display' => $this->getStatusDisplay(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Service location relationship
            'service_location' => $this->whenLoaded('serviceLocation', function () {
                return [
                    'id' => $this->serviceLocation->id,
                    'name' => $this->serviceLocation->name,
                    'type' => $this->serviceLocation->type,
                    'address' => $this->serviceLocation->full_address,
                ];
            }),

            // Availability analysis
            'availability_analysis' => [
                'window_duration_hours' => $this->getTotalAvailableHours(),
                'effective_duration_hours' => $this->getEffectiveDurationHours(),
                'blocked_hours' => $this->getBlockedHours(),
                'utilization_potential' => $this->getUtilizationPotential(),
            ],

            // Schedule patterns
            'schedule_pattern' => [
                'is_recurring' => $this->isRecurring(),
                'is_date_specific' => $this->isDateSpecific(),
                'is_date_range' => $this->isDateRange(),
                'pattern_summary' => $this->getPatternSummary(),
                'next_occurrence' => $this->getNextOccurrence(),
                'occurrence_frequency' => $this->getOccurrenceFrequency(),
            ],

            // Booking constraints
            'booking_constraints' => [
                'allows_overlapping' => $this->allowsOverlapping(),
                'minimum_booking_duration' => $this->getMinimumBookingDuration(),
                'maximum_booking_duration' => $this->getMaximumBookingDuration(),
                'booking_rules' => $this->getBookingRules(),
            ],

            // For admin view only
            'admin_info' => $this->when($this->isAdminView($request), function () {
                return [
                    'creation_info' => [
                        'created_at_formatted' => $this->created_at?->format('Y-m-d H:i:s'),
                        'updated_at_formatted' => $this->updated_at?->format('Y-m-d H:i:s'),
                        'days_since_created' => $this->created_at?->diffInDays(now()),
                    ],
                    'maintenance_info' => [
                        'requires_review' => $this->requiresReview(),
                        'last_conflict_check' => $this->getLastConflictCheck(),
                        'optimization_suggestions' => $this->getOptimizationSuggestions(),
                    ],
                ];
            }),

            // Public display information
            'display_info' => [
                'public_title' => $this->getPublicTitle(),
                'public_description' => $this->getPublicDescription(),
                'availability_summary' => $this->getAvailabilitySummary(),
                'booking_notice' => $this->getBookingNotice(),
            ],
        ];
    }

    /**
     * Get window type display name
     */
    private function getWindowTypeDisplay(): string
    {
        return match ($this->window_type) {
            'regular' => 'Regular Hours',
            'special_event' => 'Special Event',
            'maintenance' => 'Maintenance Window',
            'seasonal' => 'Seasonal Hours',
            default => ucfirst($this->window_type),
        };
    }

    /**
     * Get window description
     */
    private function getWindowDescription(): string
    {
        return match ($this->window_type) {
            'regular' => 'Standard operating hours for this venue',
            'special_event' => 'Special availability for events and occasions',
            'maintenance' => 'Scheduled maintenance - venue unavailable',
            'seasonal' => 'Seasonal availability adjustments',
            default => 'Custom availability window',
        };
    }

    /**
     * Get day name for day_of_week
     */
    private function getDayName(): ?string
    {
        if ($this->day_of_week === null) {
            return null;
        }

        $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        return $days[$this->day_of_week] ?? null;
    }

    /**
     * Get formatted date range
     */
    private function getFormattedDateRange(): ?string
    {
        if (!$this->date_range_start || !$this->date_range_end) {
            return null;
        }

        $start = $this->date_range_start->format('M j, Y');
        $end = $this->date_range_end->format('M j, Y');

        if ($this->date_range_start->isSameDay($this->date_range_end)) {
            return $start;
        }

        if ($this->date_range_start->year === $this->date_range_end->year) {
            if ($this->date_range_start->month === $this->date_range_end->month) {
                return $this->date_range_start->format('M j') . ' - ' . $this->date_range_end->format('j, Y');
            }
            return $this->date_range_start->format('M j') . ' - ' . $end;
        }

        return $start . ' - ' . $end;
    }

    /**
     * Get time range display
     */
    private function getTimeRangeDisplay(): string
    {
        if (!$this->earliest_access || !$this->latest_departure) {
            return 'Time not specified';
        }

        return $this->earliest_access->format('g:i A') . ' - ' . $this->latest_departure->format('g:i A');
    }

    /**
     * Get total available hours
     */
    private function getTotalAvailableHours(): float
    {
        if (!$this->earliest_access || !$this->latest_departure) {
            return 0;
        }

        return $this->latest_departure->diffInMinutes($this->earliest_access) / 60;
    }

    /**
     * Get quiet hours display
     */
    private function getQuietHoursDisplay(): ?string
    {
        if (!$this->hasQuietHours()) {
            return null;
        }

        return $this->quiet_hours_start->format('g:i A') . ' - ' . $this->quiet_hours_end->format('g:i A');
    }

    /**
     * Check if window has quiet hours
     */
    private function hasQuietHours(): bool
    {
        return $this->quiet_hours_start && $this->quiet_hours_end;
    }

    /**
     * Get capacity display
     */
    private function getCapacityDisplay(): string
    {
        if (!$this->max_concurrent_events) {
            return 'No limit specified';
        }

        $events = $this->max_concurrent_events === 1 ? 'event' : 'events';
        return "Maximum {$this->max_concurrent_events} concurrent {$events}";
    }

    /**
     * Get formatted restrictions
     */
    private function getFormattedRestrictions(): array
    {
        if (empty($this->restrictions)) {
            return [];
        }

        return array_map(function ($restriction) {
            return [
                'restriction' => $restriction,
                'icon' => $this->getRestrictionIcon($restriction),
                'severity' => $this->getRestrictionSeverity($restriction),
            ];
        }, $this->restrictions);
    }

    /**
     * Get restriction icon
     */
    private function getRestrictionIcon(string $restriction): string
    {
        $restriction = strtolower($restriction);

        if (str_contains($restriction, 'noise')) return '🔇';
        if (str_contains($restriction, 'alcohol')) return '🍺';
        if (str_contains($restriction, 'smoking')) return '🚭';
        if (str_contains($restriction, 'parking')) return '🚗';
        if (str_contains($restriction, 'pet')) return '🐕';
        if (str_contains($restriction, 'food')) return '🍽️';
        if (str_contains($restriction, 'music')) return '🎵';

        return '⚠️';
    }

    /**
     * Get restriction severity
     */
    private function getRestrictionSeverity(string $restriction): string
    {
        $restriction = strtolower($restriction);

        if (str_contains($restriction, 'prohibited') || str_contains($restriction, 'banned')) {
            return 'high';
        }
        if (str_contains($restriction, 'limited') || str_contains($restriction, 'restricted')) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * Get status display
     */
    private function getStatusDisplay(): string
    {
        if (!$this->is_active) {
            return 'Inactive';
        }

        return match ($this->window_type) {
            'maintenance' => 'Maintenance Scheduled',
            'special_event' => 'Special Event Available',
            default => 'Active',
        };
    }

    /**
     * Get effective duration hours (excluding quiet hours)
     */
    private function getEffectiveDurationHours(): float
    {
        $totalHours = $this->getTotalAvailableHours();
        $quietHours = $this->getQuietHoursDuration();

        return max(0, $totalHours - $quietHours);
    }

    /**
     * Get quiet hours duration
     */
    private function getQuietHoursDuration(): float
    {
        if (!$this->hasQuietHours()) {
            return 0;
        }

        return $this->quiet_hours_end->diffInMinutes($this->quiet_hours_start) / 60;
    }

    /**
     * Get blocked hours
     */
    private function getBlockedHours(): float
    {
        return $this->getQuietHoursDuration();
    }

    /**
     * Get utilization potential
     */
    private function getUtilizationPotential(): string
    {
        $effectiveHours = $this->getEffectiveDurationHours();

        if ($effectiveHours >= 8) return 'High';
        if ($effectiveHours >= 4) return 'Medium';
        if ($effectiveHours >= 2) return 'Low';

        return 'Very Low';
    }

    /**
     * Check if window is recurring
     */
    private function isRecurring(): bool
    {
        return $this->window_type === 'regular' && $this->day_of_week !== null;
    }

    /**
     * Check if window is date specific
     */
    private function isDateSpecific(): bool
    {
        return $this->specific_date !== null;
    }

    /**
     * Check if window is date range
     */
    private function isDateRange(): bool
    {
        return $this->date_range_start !== null && $this->date_range_end !== null;
    }

    /**
     * Get pattern summary
     */
    private function getPatternSummary(): string
    {
        if ($this->isRecurring()) {
            return "Every {$this->getDayName()}";
        }

        if ($this->isDateSpecific()) {
            return "One-time on {$this->specific_date_formatted}";
        }

        if ($this->isDateRange()) {
            return "Date range: {$this->getFormattedDateRange()}";
        }

        return 'Custom schedule';
    }

    /**
     * Get next occurrence
     */
    private function getNextOccurrence(): ?string
    {
        if ($this->isRecurring()) {
            $today = now();
            $targetDay = $this->day_of_week;
            $daysUntil = ($targetDay - $today->dayOfWeek + 7) % 7;

            if ($daysUntil === 0) {
                // Today is the target day
                $nextOccurrence = $today;
            } else {
                $nextOccurrence = $today->addDays($daysUntil);
            }

            return $nextOccurrence->format('Y-m-d');
        }

        if ($this->isDateSpecific() && $this->specific_date->isFuture()) {
            return $this->specific_date->format('Y-m-d');
        }

        if ($this->isDateRange() && $this->date_range_start->isFuture()) {
            return $this->date_range_start->format('Y-m-d');
        }

        return null;
    }

    /**
     * Get occurrence frequency
     */
    private function getOccurrenceFrequency(): string
    {
        if ($this->isRecurring()) {
            return 'Weekly';
        }

        if ($this->isDateSpecific()) {
            return 'One-time';
        }

        if ($this->isDateRange()) {
            $days = $this->date_range_end->diffInDays($this->date_range_start) + 1;
            return "Consecutive {$days} days";
        }

        return 'Custom';
    }

    /**
     * Check if allows overlapping events
     */
    private function allowsOverlapping(): bool
    {
        return $this->max_concurrent_events > 1;
    }

    /**
     * Get minimum booking duration
     */
    private function getMinimumBookingDuration(): string
    {
        // This would be configurable in a full implementation
        return '1 hour';
    }

    /**
     * Get maximum booking duration
     */
    private function getMaximumBookingDuration(): string
    {
        $maxHours = $this->getTotalAvailableHours();
        return intval($maxHours) . ' hours (full window)';
    }

    /**
     * Get booking rules
     */
    private function getBookingRules(): array
    {
        $rules = [];

        if ($this->hasQuietHours()) {
            $rules[] = "Quiet hours: {$this->getQuietHoursDisplay()}";
        }

        if ($this->max_concurrent_events === 1) {
            $rules[] = 'Exclusive bookings only';
        } elseif ($this->max_concurrent_events > 1) {
            $rules[] = "Maximum {$this->max_concurrent_events} concurrent events";
        }

        if (!empty($this->restrictions)) {
            $rules[] = 'Special restrictions apply';
        }

        if ($this->window_type === 'maintenance') {
            $rules[] = 'Venue unavailable during maintenance';
        }

        return $rules;
    }

    /**
     * Check if this is an admin view
     */
    private function isAdminView(Request $request): bool
    {
        return $request->is('*/admin/*') ||
            $request->user()?->hasRole(['super admin', 'admin']);
    }

    /**
     * Check if requires review
     */
    private function requiresReview(): bool
    {
        // Check if window hasn't been updated in a while
        if ($this->updated_at && $this->updated_at->lt(now()->subMonths(3))) {
            return true;
        }

        // Check for conflicting configurations
        if ($this->hasQuietHours() && $this->getQuietHoursDuration() > $this->getTotalAvailableHours() * 0.5) {
            return true;
        }

        return false;
    }

    /**
     * Get last conflict check
     */
    private function getLastConflictCheck(): ?string
    {
        // This would be tracked in database in full implementation
        return $this->updated_at?->format('Y-m-d H:i:s');
    }

    /**
     * Get optimization suggestions
     */
    private function getOptimizationSuggestions(): array
    {
        $suggestions = [];

        // Suggest reducing quiet hours if too restrictive
        if ($this->hasQuietHours() && $this->getQuietHoursDuration() > 4) {
            $suggestions[] = [
                'type' => 'quiet_hours',
                'suggestion' => 'Consider reducing quiet hours duration to increase availability',
                'impact' => 'medium',
            ];
        }

        // Suggest increasing capacity if no concurrent events allowed
        if ($this->max_concurrent_events === 1) {
            $suggestions[] = [
                'type' => 'capacity',
                'suggestion' => 'Consider allowing multiple concurrent events to increase revenue',
                'impact' => 'high',
            ];
        }

        // Suggest regularizing one-off special events
        if ($this->window_type === 'special_event' && $this->isDateSpecific()) {
            $suggestions[] = [
                'type' => 'scheduling',
                'suggestion' => 'If this special event repeats, consider making it a regular window',
                'impact' => 'low',
            ];
        }

        return $suggestions;
    }

    /**
     * Get public title
     */
    private function getPublicTitle(): string
    {
        return match ($this->window_type) {
            'regular' => "Available {$this->getDayName()}s",
            'special_event' => 'Special Event Availability',
            'seasonal' => 'Seasonal Hours',
            'maintenance' => 'Temporarily Unavailable',
            default => 'Availability Window',
        };
    }

    /**
     * Get public description
     */
    private function getPublicDescription(): string
    {
        if ($this->window_type === 'maintenance') {
            return 'This venue is temporarily unavailable for scheduled maintenance.';
        }

        $description = "Available {$this->getTimeRangeDisplay()}";

        if ($this->hasQuietHours()) {
            $description .= " (Quiet hours: {$this->getQuietHoursDisplay()})";
        }

        return $description;
    }

    /**
     * Get availability summary
     */
    private function getAvailabilitySummary(): string
    {
        if (!$this->is_active) {
            return 'Currently unavailable';
        }

        if ($this->window_type === 'maintenance') {
            return 'Maintenance scheduled';
        }

        $effectiveHours = $this->getEffectiveDurationHours();
        $hoursText = $effectiveHours == 1 ? 'hour' : 'hours';

        return sprintf('%.1f %s available', $effectiveHours, $hoursText);
    }

    /**
     * Get booking notice
     */
    private function getBookingNotice(): ?string
    {
        if (!empty($this->restrictions)) {
            return 'Special restrictions apply - please review details';
        }

        if ($this->hasQuietHours()) {
            return 'Quiet hours restrictions apply during specified times';
        }

        if ($this->max_concurrent_events === 1) {
            return 'Exclusive venue booking only';
        }

        return null;
    }
}
