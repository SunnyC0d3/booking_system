<?php

namespace App\Resources\V1;

use App\Constants\BookingStatuses;
use App\Constants\CalendarProviders;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class CalendarEventResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'external_event_id' => $this->external_event_id,

            // Calendar integration context
            'calendar_integration' => [
                'id' => $this->calendarIntegration->id,
                'provider' => $this->calendarIntegration->provider,
                'provider_display_name' => $this->getProviderDisplayName(),
                'calendar_name' => $this->calendarIntegration->calendar_name,
                'calendar_id' => $this->calendarIntegration->calendar_id,
            ],

            // Event basic information
            'title' => $this->getFilteredTitle($request),
            'description' => $this->getFilteredDescription($request),
            'location' => $this->getFilteredLocation($request),

            // Timing information
            'timing' => [
                'starts_at' => $this->starts_at->toISOString(),
                'ends_at' => $this->ends_at->toISOString(),
                'is_all_day' => $this->is_all_day,
                'duration_minutes' => $this->getDurationMinutes(),
                'timezone' => $this->getEventTimezone(),
                'formatted_date' => $this->getFormattedDate(),
                'formatted_time' => $this->getFormattedTime(),
                'formatted_duration' => $this->getFormattedDuration(),
                'relative_time' => $this->getRelativeTime(),
            ],

            // Event classification and status
            'classification' => [
                'event_type' => $this->getEventType(),
                'source' => $this->getEventSource(),
                'is_booking_related' => $this->isBookingRelated(),
                'is_external_event' => $this->isExternalEvent(),
                'is_recurring' => $this->isRecurring(),
                'is_private' => $this->isPrivateEvent(),
                'priority_level' => $this->getPriorityLevel(),
            ],

            // Booking information (if applicable)
            'booking_context' => $this->when($this->isBookingRelated(), function () use ($request) {
                return $this->getBookingContext($request);
            }),

            // Blocking and availability impact
            'availability_impact' => [
                'blocks_booking' => $this->blocks_booking,
                'block_type' => $this->block_type ?? 'full',
                'affects_availability' => $this->affectsAvailability(),
                'conflict_severity' => $this->getConflictSeverity(),
                'time_slots_affected' => $this->getAffectedTimeSlots(),
                'buffer_time_before' => $this->getBufferTimeBefore(),
                'buffer_time_after' => $this->getBufferTimeAfter(),
            ],

            // Sync and modification tracking
            'sync_info' => [
                'synced_at' => $this->synced_at->toISOString(),
                'last_updated_externally' => $this->last_updated_externally?->toISOString(),
                'sync_source' => $this->getSyncSource(),
                'needs_resync' => $this->needsResync(),
                'sync_conflicts' => $this->getSyncConflicts(),
                'last_sync_attempt' => $this->getLastSyncAttempt(),
                'sync_error_count' => $this->getSyncErrorCount(),
            ],

            // Event metadata and properties
            'metadata' => [
                'attendee_count' => $this->getAttendeeCount(),
                'has_reminders' => $this->hasReminders(),
                'reminder_settings' => $this->getReminderSettings(),
                'calendar_color' => $this->getCalendarColor(),
                'event_url' => $this->getEventUrl(),
                'meeting_link' => $this->getMeetingLink(),
                'organizer_info' => $this->getOrganizerInfo($request),
            ],

            // Conflict detection and resolution
            'conflicts' => $this->when($this->hasConflicts(), function () {
                return $this->getConflictDetails();
            }),

            // User permissions and actions
            'permissions' => [
                'can_view_details' => $this->canViewDetails($request->user()),
                'can_modify' => $this->canModify($request->user()),
                'can_delete' => $this->canDelete($request->user()),
                'can_override_block' => $this->canOverrideBlock($request->user()),
                'can_create_exception' => $this->canCreateException($request->user()),
            ],

            // Related events and patterns
            'relationships' => [
                'related_events' => $this->when($this->hasRelatedEvents(), function () {
                    return $this->getRelatedEvents();
                }),
                'recurring_pattern' => $this->when($this->isRecurring(), function () {
                    return $this->getRecurringPattern();
                }),
                'series_info' => $this->when($this->isPartOfSeries(), function () {
                    return $this->getSeriesInfo();
                }),
            ],

            // Display and UI helpers
            'display' => [
                'css_classes' => $this->getCssClasses(),
                'status_badge' => $this->getStatusBadge(),
                'icon' => $this->getEventIcon(),
                'color_scheme' => $this->getColorScheme(),
                'urgency_indicator' => $this->getUrgencyIndicator(),
                'tooltip_text' => $this->getTooltipText($request),
            ],

            // Time-based flags
            'time_flags' => [
                'is_past' => $this->isPast(),
                'is_current' => $this->isCurrent(),
                'is_upcoming' => $this->isUpcoming(),
                'is_today' => $this->isToday(),
                'is_this_week' => $this->isThisWeek(),
                'starts_soon' => $this->startsSoon(),
                'ends_soon' => $this->endsSoon(),
                'is_long_event' => $this->isLongEvent(),
            ],

            // Timestamps for sorting and filtering
            'timestamps' => [
                'created_at' => $this->created_at->toISOString(),
                'updated_at' => $this->updated_at->toISOString(),
                'sort_timestamp' => $this->starts_at->timestamp,
                'filter_date' => $this->starts_at->toDateString(),
                'filter_month' => $this->starts_at->format('Y-m'),
                'filter_week' => $this->starts_at->format('Y-W'),
            ],
        ];
    }

    /**
     * Get provider display name
     */
    private function getProviderDisplayName(): string
    {
        return match ($this->calendarIntegration->provider) {
            CalendarProviders::GOOGLE => 'Google Calendar',
            CalendarProviders::OUTLOOK => 'Outlook Calendar',
            CalendarProviders::APPLE => 'Apple Calendar',
            CalendarProviders::ICAL => 'iCal/WebCal',
            default => ucfirst($this->calendarIntegration->provider) . ' Calendar'
        };
    }

    /**
     * Get filtered title based on privacy settings
     */
    private function getFilteredTitle($request): string
    {
        $user = $request->user();
        $title = $this->title ?? 'Untitled Event';

        // If user can't view details, return generic title
        if (!$this->canViewDetails($user)) {
            return $this->isBookingRelated() ? 'Booking Event' : 'Busy';
        }

        // Apply privacy filtering based on settings
        if ($this->isPrivateEvent() && !$this->isOwnEvent($user)) {
            return 'Private Event';
        }

        return $title;
    }

    /**
     * Get filtered description based on privacy settings
     */
    private function getFilteredDescription($request): ?string
    {
        $user = $request->user();

        if (!$this->canViewDetails($user) || $this->isPrivateEvent()) {
            return null;
        }

        return $this->description;
    }

    /**
     * Get filtered location based on privacy settings
     */
    private function getFilteredLocation($request): ?string
    {
        $user = $request->user();

        if (!$this->canViewDetails($user) || $this->isPrivateEvent()) {
            return null;
        }

        // Check if integration allows location sharing
        $integration = $this->calendarIntegration;
        $settings = $integration->sync_settings_display ?? [];

        if (!($settings['include_location'] ?? true)) {
            return null;
        }

        return $this->location ?? null;
    }

    /**
     * Get duration in minutes
     */
    private function getDurationMinutes(): int
    {
        return $this->starts_at->diffInMinutes($this->ends_at);
    }

    /**
     * Get event timezone
     */
    private function getEventTimezone(): string
    {
        return $this->timezone ?? config('app.timezone');
    }

    /**
     * Get formatted date
     */
    private function getFormattedDate(): string
    {
        if ($this->is_all_day) {
            return $this->starts_at->format('l, F j, Y');
        }

        return $this->starts_at->format('l, F j, Y');
    }

    /**
     * Get formatted time
     */
    private function getFormattedTime(): ?string
    {
        if ($this->is_all_day) {
            return 'All Day';
        }

        $start = $this->starts_at->format('g:i A');
        $end = $this->ends_at->format('g:i A');

        return "{$start} - {$end}";
    }

    /**
     * Get formatted duration
     */
    private function getFormattedDuration(): string
    {
        if ($this->is_all_day) {
            return 'All Day';
        }

        $minutes = $this->getDurationMinutes();

        if ($minutes < 60) {
            return "{$minutes} minutes";
        }

        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;

        if ($remainingMinutes === 0) {
            return $hours === 1 ? '1 hour' : "{$hours} hours";
        }

        return "{$hours}h {$remainingMinutes}m";
    }

    /**
     * Get relative time description
     */
    private function getRelativeTime(): string
    {
        if ($this->isPast()) {
            return $this->starts_at->diffForHumans();
        }

        if ($this->isCurrent()) {
            return 'Happening now';
        }

        return $this->starts_at->diffForHumans();
    }

    /**
     * Get event type classification
     */
    private function getEventType(): string
    {
        if ($this->isBookingRelated()) {
            return 'booking';
        }

        if ($this->isRecurring()) {
            return 'recurring';
        }

        if ($this->is_all_day) {
            return 'all_day';
        }

        return 'standard';
    }

    /**
     * Get event source
     */
    private function getEventSource(): string
    {
        if ($this->isBookingRelated()) {
            return 'internal_booking';
        }

        return 'external_calendar';
    }

    /**
     * Check if event is booking related
     */
    private function isBookingRelated(): bool
    {
        // Check if this event was created from a booking
        return !empty($this->booking_id) ||
            str_contains(strtolower($this->title ?? ''), 'booking');
    }

    /**
     * Check if event is external
     */
    private function isExternalEvent(): bool
    {
        return !$this->isBookingRelated();
    }

    /**
     * Check if event is recurring
     */
    private function isRecurring(): bool
    {
        return !empty($this->recurring_pattern) ||
            !empty($this->external_recurring_id);
    }

    /**
     * Check if event is private
     */
    private function isPrivateEvent(): bool
    {
        return $this->visibility === 'private' ||
            str_contains(strtolower($this->title ?? ''), 'private');
    }

    /**
     * Get priority level
     */
    private function getPriorityLevel(): string
    {
        if ($this->isBookingRelated()) {
            return 'high';
        }

        if ($this->blocks_booking) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * Get booking context if applicable
     */
    private function getBookingContext($request): ?array
    {
        if (!$this->booking_id) {
            return null;
        }

        $booking = $this->booking;
        if (!$booking) {
            return null;
        }

        $user = $request->user();
        $canViewBookingDetails = $user && (
                $user->hasPermission('view_all_bookings') ||
                $booking->user_id === $user->id
            );

        if (!$canViewBookingDetails) {
            return [
                'booking_reference' => $booking->booking_reference,
                'status' => $booking->status,
                'service_name' => $booking->service->name ?? 'Unknown Service',
            ];
        }

        return [
            'booking_id' => $booking->id,
            'booking_reference' => $booking->booking_reference,
            'status' => $booking->status,
            'client_name' => $booking->client_name,
            'client_email' => $booking->client_email,
            'service' => [
                'id' => $booking->service->id,
                'name' => $booking->service->name,
                'category' => $booking->service->category,
            ],
            'location' => $booking->serviceLocation ? [
                'id' => $booking->serviceLocation->id,
                'name' => $booking->serviceLocation->name,
                'address' => $booking->serviceLocation->full_address,
            ] : null,
            'total_amount' => $booking->total_amount,
            'formatted_amount' => '£' . number_format($booking->total_amount / 100, 2),
        ];
    }

    /**
     * Check if affects availability
     */
    private function affectsAvailability(): bool
    {
        return $this->blocks_booking &&
            $this->starts_at->gte(now()) &&
            $this->calendarIntegration->auto_block_external_events;
    }

    /**
     * Get conflict severity
     */
    private function getConflictSeverity(): string
    {
        if (!$this->blocks_booking) {
            return 'none';
        }

        if ($this->hasDirectBookingConflict()) {
            return 'high';
        }

        if ($this->hasOverlapWithBookings()) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * Get affected time slots
     */
    private function getAffectedTimeSlots(): array
    {
        if (!$this->blocks_booking) {
            return [];
        }

        // Calculate 15-minute slots affected by this event
        $slots = [];
        $current = $this->starts_at->copy()->startOfHour();
        $end = $this->ends_at->copy();

        while ($current->lt($end)) {
            $slotEnd = $current->copy()->addMinutes(15);

            if ($slotEnd->gt($this->starts_at) && $current->lt($this->ends_at)) {
                $slots[] = [
                    'start' => $current->toTimeString('minute'),
                    'end' => $slotEnd->toTimeString('minute'),
                    'date' => $current->toDateString(),
                ];
            }

            $current->addMinutes(15);
        }

        return $slots;
    }

    /**
     * Get buffer time before event
     */
    private function getBufferTimeBefore(): int
    {
        // Standard buffer for different event types
        if ($this->isBookingRelated()) {
            return 30; // 30 minutes buffer for bookings
        }

        return 15; // 15 minutes for external events
    }

    /**
     * Get buffer time after event
     */
    private function getBufferTimeAfter(): int
    {
        if ($this->isBookingRelated()) {
            return 15; // 15 minutes cleanup time
        }

        return 0;
    }

    /**
     * Get sync source
     */
    private function getSyncSource(): string
    {
        return $this->isBookingRelated() ? 'booking_system' : 'external_calendar';
    }

    /**
     * Check if needs resync
     */
    private function needsResync(): bool
    {
        if (!$this->last_updated_externally) {
            return false;
        }

        return $this->last_updated_externally->gt($this->synced_at);
    }

    /**
     * Get sync conflicts
     */
    private function getSyncConflicts(): array
    {
        $conflicts = [];

        if ($this->needsResync()) {
            $conflicts[] = 'outdated_local_copy';
        }

        if ($this->hasDirectBookingConflict()) {
            $conflicts[] = 'booking_overlap';
        }

        return $conflicts;
    }

    /**
     * Get last sync attempt
     */
    private function getLastSyncAttempt(): ?string
    {
        $integration = $this->calendarIntegration;
        return $integration->last_sync_at?->toISOString();
    }

    /**
     * Get sync error count
     */
    private function getSyncErrorCount(): int
    {
        return $this->calendarIntegration->sync_error_count;
    }

    /**
     * Get attendee count
     */
    private function getAttendeeCount(): int
    {
        return $this->attendee_count ?? 1;
    }

    /**
     * Check if has reminders
     */
    private function hasReminders(): bool
    {
        return !empty($this->reminder_settings);
    }

    /**
     * Get reminder settings
     */
    private function getReminderSettings(): array
    {
        return $this->reminder_settings ?? [];
    }

    /**
     * Get calendar color
     */
    private function getCalendarColor(): string
    {
        $settings = $this->calendarIntegration->sync_settings_display ?? [];
        return $settings['calendar_color'] ?? '#4285F4';
    }

    /**
     * Get event URL
     */
    private function getEventUrl(): ?string
    {
        if (!$this->external_event_id) {
            return null;
        }

        $provider = $this->calendarIntegration->provider;
        $eventId = $this->external_event_id;
        $calendarId = $this->calendarIntegration->calendar_id;

        return match ($provider) {
            CalendarProviders::GOOGLE => "https://calendar.google.com/calendar/event?eid={$eventId}",
            CalendarProviders::OUTLOOK => "https://outlook.live.com/calendar/",
            default => null
        };
    }

    /**
     * Get meeting link
     */
    private function getMeetingLink(): ?string
    {
        return $this->meeting_link ?? null;
    }

    /**
     * Get organizer info
     */
    private function getOrganizerInfo($request): ?array
    {
        if (!$this->canViewDetails($request->user())) {
            return null;
        }

        return $this->organizer_info ?? null;
    }

    /**
     * Check if has conflicts
     */
    private function hasConflicts(): bool
    {
        return $this->hasDirectBookingConflict() ||
            $this->hasOverlapWithBookings() ||
            $this->needsResync();
    }

    /**
     * Get conflict details
     */
    private function getConflictDetails(): array
    {
        $conflicts = [];

        if ($this->hasDirectBookingConflict()) {
            $conflicts[] = [
                'type' => 'booking_overlap',
                'severity' => 'high',
                'message' => 'Overlaps with existing booking',
                'affected_bookings' => $this->getConflictingBookings(),
            ];
        }

        if ($this->needsResync()) {
            $conflicts[] = [
                'type' => 'sync_outdated',
                'severity' => 'medium',
                'message' => 'Event updated externally',
                'last_external_update' => $this->last_updated_externally->toISOString(),
            ];
        }

        return $conflicts;
    }

    /**
     * Permission checks
     */
    private function canViewDetails($user): bool
    {
        if (!$user) {
            return false;
        }

        return $user->hasPermission('view_calendar_events') ||
            $this->isOwnEvent($user) ||
            $user->hasPermission('view_all_calendar_events');
    }

    private function canModify($user): bool
    {
        if (!$user) {
            return false;
        }

        return $user->hasPermission('manage_calendar_events') ||
            ($this->isOwnEvent($user) && $user->hasPermission('manage_own_calendar_events'));
    }

    private function canDelete($user): bool
    {
        return $this->canModify($user) && !$this->isBookingRelated();
    }

    private function canOverrideBlock($user): bool
    {
        return $user && $user->hasPermission('override_calendar_blocks');
    }

    private function canCreateException($user): bool
    {
        return $this->canModify($user) && $this->isRecurring();
    }

    /**
     * Check if user owns this event
     */
    private function isOwnEvent($user): bool
    {
        return $user && $this->calendarIntegration->user_id === $user->id;
    }

    /**
     * Get CSS classes for display
     */
    private function getCssClasses(): array
    {
        $classes = ['calendar-event'];

        $classes[] = 'event-' . $this->getEventType();
        $classes[] = 'priority-' . $this->getPriorityLevel();
        $classes[] = 'provider-' . $this->calendarIntegration->provider;

        if ($this->blocks_booking) {
            $classes[] = 'blocks-booking';
        }

        if ($this->hasConflicts()) {
            $classes[] = 'has-conflicts';
        }

        if ($this->isPast()) {
            $classes[] = 'past-event';
        } elseif ($this->isCurrent()) {
            $classes[] = 'current-event';
        } else {
            $classes[] = 'future-event';
        }

        return $classes;
    }

    /**
     * Get status badge info
     */
    private function getStatusBadge(): array
    {
        if ($this->hasConflicts()) {
            return ['text' => 'Conflict', 'color' => 'red'];
        }

        if ($this->isBookingRelated()) {
            return ['text' => 'Booking', 'color' => 'blue'];
        }

        if ($this->blocks_booking) {
            return ['text' => 'Busy', 'color' => 'orange'];
        }

        return ['text' => 'Available', 'color' => 'green'];
    }

    /**
     * Get event icon
     */
    private function getEventIcon(): string
    {
        if ($this->isBookingRelated()) {
            return 'calendar-check';
        }

        if ($this->blocks_booking) {
            return 'calendar-x';
        }

        return 'calendar';
    }

    /**
     * Get color scheme
     */
    private function getColorScheme(): array
    {
        $baseColor = $this->getCalendarColor();

        return [
            'primary' => $baseColor,
            'background' => $baseColor . '20', // 20% opacity
            'border' => $baseColor,
            'text' => $this->getContrastTextColor($baseColor),
        ];
    }

    /**
     * Get urgency indicator
     */
    private function getUrgencyIndicator(): ?string
    {
        if ($this->startsSoon() && $this->isBookingRelated()) {
            return 'starting-soon';
        }

        if ($this->hasConflicts()) {
            return 'needs-attention';
        }

        return null;
    }

    /**
     * Get tooltip text
     */
    private function getTooltipText($request): string
    {
        $title = $this->getFilteredTitle($request);
        $time = $this->getFormattedTime();
        $duration = $this->getFormattedDuration();

        return "{$title}\n{$time} ({$duration})";
    }

    /**
     * Time-based flag methods
     */
    private function isPast(): bool
    {
        return $this->ends_at->lt(now());
    }

    private function isCurrent(): bool
    {
        return $this->starts_at->lte(now()) && $this->ends_at->gt(now());
    }

    private function isUpcoming(): bool
    {
        return $this->starts_at->gt(now());
    }

    private function isToday(): bool
    {
        return $this->starts_at->isToday();
    }

    private function isThisWeek(): bool
    {
        return $this->starts_at->isCurrentWeek();
    }

    private function startsSoon(): bool
    {
        return $this->starts_at->isFuture() && $this->starts_at->lt(now()->addHours(2));
    }

    private function endsSoon(): bool
    {
        return $this->ends_at->isFuture() && $this->ends_at->lt(now()->addHours(1));
    }

    private function isLongEvent(): bool
    {
        return $this->getDurationMinutes() > 240; // More than 4 hours
    }

    /**
     * Helper methods
     */
    private function hasDirectBookingConflict(): bool
    {
        // This would check against actual bookings in the database
        return false; // Placeholder
    }

    private function hasOverlapWithBookings(): bool
    {
        // This would check for overlapping bookings
        return false; // Placeholder
    }

    private function getConflictingBookings(): array
    {
        // This would return actual conflicting bookings
        return []; // Placeholder
    }

    private function hasRelatedEvents(): bool
    {
        return $this->isRecurring() || $this->isPartOfSeries();
    }

    private function getRelatedEvents(): array
    {
        // Return related events in the series
        return []; // Placeholder
    }

    private function getRecurringPattern(): ?array
    {
        return $this->recurring_pattern ?? null;
    }

    private function isPartOfSeries(): bool
    {
        return !empty($this->external_recurring_id);
    }

    private function getSeriesInfo(): ?array
    {
        return $this->series_info ?? null;
    }

    private function getContrastTextColor(string $backgroundColor): string
    {
        // Simple contrast calculation
        $hex = ltrim($backgroundColor, '#');
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        $brightness = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;

        return $brightness > 155 ? '#000000' : '#FFFFFF';
    }
}
