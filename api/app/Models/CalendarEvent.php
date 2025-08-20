<?php

namespace App\Models;

use App\Constants\CalendarProviders;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class CalendarEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'calendar_integration_id',
        'external_event_id',
        'title',
        'description',
        'starts_at',
        'ends_at',
        'is_all_day',
        'blocks_booking',
        'block_type',
        'last_updated_externally',
        'synced_at',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_all_day' => 'boolean',
        'blocks_booking' => 'boolean',
        'last_updated_externally' => 'datetime',
        'synced_at' => 'datetime',
    ];

    protected $dates = [
        'starts_at',
        'ends_at',
        'last_updated_externally',
        'synced_at',
    ];

    // Relationships

    /**
     * Get the calendar integration that owns this event
     */
    public function calendarIntegration(): BelongsTo
    {
        return $this->belongsTo(CalendarIntegration::class);
    }

    /**
     * Get the booking associated with this event (if any)
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    // Scopes

    /**
     * Scope to filter events that block booking
     */
    public function scopeBlocksBooking(Builder $query): Builder
    {
        return $query->where('blocks_booking', true);
    }

    /**
     * Scope to filter events within a date range
     */
    public function scopeInDateRange(Builder $query, Carbon $startDate, Carbon $endDate): Builder
    {
        return $query->where(function ($q) use ($startDate, $endDate) {
            $q->where('starts_at', '<', $endDate)
                ->where('ends_at', '>', $startDate);
        });
    }

    /**
     * Scope to filter upcoming events
     */
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('starts_at', '>', now());
    }

    /**
     * Scope to filter past events
     */
    public function scopePast(Builder $query): Builder
    {
        return $query->where('ends_at', '<', now());
    }

    /**
     * Scope to filter current events
     */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('starts_at', '<=', now())
            ->where('ends_at', '>', now());
    }

    /**
     * Scope to filter events for a specific provider
     */
    public function scopeForProvider(Builder $query, string $provider): Builder
    {
        return $query->whereHas('calendarIntegration', function ($q) use ($provider) {
            $q->where('provider', $provider);
        });
    }

    /**
     * Scope to filter events that need resyncing
     */
    public function scopeNeedsResync(Builder $query): Builder
    {
        return $query->whereNotNull('last_updated_externally')
            ->whereColumn('last_updated_externally', '>', 'synced_at');
    }

    /**
     * Scope to filter events by sync status
     */
    public function scopeSyncedRecently(Builder $query, int $hours = 24): Builder
    {
        return $query->where('synced_at', '>=', now()->subHours($hours));
    }

    /**
     * Scope to filter all-day events
     */
    public function scopeAllDay(Builder $query): Builder
    {
        return $query->where('is_all_day', true);
    }

    /**
     * Scope to filter timed events
     */
    public function scopeTimed(Builder $query): Builder
    {
        return $query->where('is_all_day', false);
    }

    // Accessor Methods

    /**
     * Get the duration of the event in minutes
     */
    public function getDurationMinutesAttribute(): int
    {
        return $this->starts_at->diffInMinutes($this->ends_at);
    }

    /**
     * Get the event duration in human readable format
     */
    public function getFormattedDurationAttribute(): string
    {
        if ($this->is_all_day) {
            return 'All Day';
        }

        $minutes = $this->duration_minutes;

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
     * Get the provider display name
     */
    public function getProviderDisplayNameAttribute(): string
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
     * Check if event is happening today
     */
    public function getIsTodayAttribute(): bool
    {
        return $this->starts_at->isToday() ||
            ($this->is_all_day && $this->starts_at->toDateString() === now()->toDateString());
    }

    /**
     * Check if event is happening this week
     */
    public function getIsThisWeekAttribute(): bool
    {
        return $this->starts_at->isCurrentWeek();
    }

    /**
     * Check if event starts soon (within next 2 hours)
     */
    public function getStartsSoonAttribute(): bool
    {
        return $this->starts_at->isFuture() &&
            $this->starts_at->lt(now()->addHours(2));
    }

    /**
     * Check if event ends soon (within next hour)
     */
    public function getEndsSoonAttribute(): bool
    {
        return $this->ends_at->isFuture() &&
            $this->ends_at->lt(now()->addHours(1));
    }

    /**
     * Check if this is a long event (more than 4 hours)
     */
    public function getIsLongEventAttribute(): bool
    {
        return $this->duration_minutes > 240;
    }

    // Status Check Methods

    /**
     * Check if event is in the past
     */
    public function isPast(): bool
    {
        return $this->ends_at->isPast();
    }

    /**
     * Check if event is currently happening
     */
    public function isCurrent(): bool
    {
        return $this->starts_at->isPast() && $this->ends_at->isFuture();
    }

    /**
     * Check if event is upcoming
     */
    public function isUpcoming(): bool
    {
        return $this->starts_at->isFuture();
    }

    /**
     * Check if event needs to be resynced
     */
    public function needsResync(): bool
    {
        return $this->last_updated_externally &&
            $this->last_updated_externally->gt($this->synced_at);
    }

    /**
     * Check if event was synced recently
     */
    public function isSyncedRecently(int $hours = 1): bool
    {
        return $this->synced_at && $this->synced_at->gt(now()->subHours($hours));
    }

    // Conflict Detection Methods

    /**
     * Check if event overlaps with a given time range
     */
    public function overlapsWithTimeRange(Carbon $startTime, Carbon $endTime): bool
    {
        return $this->starts_at->lt($endTime) && $this->ends_at->gt($startTime);
    }

    /**
     * Get overlapping bookings for this event
     */
    public function getOverlappingBookings(): \Illuminate\Database\Eloquent\Collection
    {
        if (!$this->blocks_booking) {
            return collect();
        }

        $serviceId = $this->calendarIntegration->service_id;

        $query = Booking::where('status', '!=', 'cancelled')
            ->where(function ($q) {
                $q->where('scheduled_at', '<', $this->ends_at)
                    ->where('ends_at', '>', $this->starts_at);
            });

        // If integration is service-specific, filter by service
        if ($serviceId) {
            $query->where('service_id', $serviceId);
        }

        return $query->get();
    }

    /**
     * Check if event has booking conflicts
     */
    public function hasBookingConflicts(): bool
    {
        return $this->getOverlappingBookings()->isNotEmpty();
    }

    /**
     * Get conflict severity level
     */
    public function getConflictSeverity(): string
    {
        if (!$this->blocks_booking) {
            return 'none';
        }

        $overlappingBookings = $this->getOverlappingBookings();

        if ($overlappingBookings->isEmpty()) {
            return 'none';
        }

        // Check for complete overlaps (high severity)
        foreach ($overlappingBookings as $booking) {
            $overlapStart = max($this->starts_at, $booking->scheduled_at);
            $overlapEnd = min($this->ends_at, $booking->ends_at);
            $overlapMinutes = $overlapStart->diffInMinutes($overlapEnd);

            if ($overlapMinutes >= 60) {
                return 'high';
            }
        }

        return 'medium';
    }

    // Time Slot Methods

    /**
     * Get affected 15-minute time slots
     */
    public function getAffectedTimeSlots(): array
    {
        if (!$this->blocks_booking) {
            return [];
        }

        $slots = [];
        $current = $this->starts_at->copy()->startOfHour();
        $end = $this->ends_at->copy();

        while ($current->lt($end)) {
            $slotEnd = $current->copy()->addMinutes(15);

            if ($slotEnd->gt($this->starts_at) && $current->lt($this->ends_at)) {
                $slots[] = [
                    'start' => $current->format('H:i'),
                    'end' => $slotEnd->format('H:i'),
                    'date' => $current->toDateString(),
                    'datetime_start' => $current->toISOString(),
                    'datetime_end' => $slotEnd->toISOString(),
                ];
            }

            $current->addMinutes(15);
        }

        return $slots;
    }

    /**
     * Check if event blocks a specific time slot
     */
    public function blocksTimeSlot(Carbon $slotStart, Carbon $slotEnd): bool
    {
        if (!$this->blocks_booking) {
            return false;
        }

        return $this->overlapsWithTimeRange($slotStart, $slotEnd);
    }

    // Utility Methods

    /**
     * Mark event as synced
     */
    public function markAsSynced(): void
    {
        $this->update(['synced_at' => now()]);
    }

    /**
     * Update external modification time
     */
    public function markAsExternallyUpdated(): void
    {
        $this->update(['last_updated_externally' => now()]);
    }

    /**
     * Get event URL for external calendar
     */
    public function getExternalUrl(): ?string
    {
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
     * Get display color for the event
     */
    public function getDisplayColor(): string
    {
        $settings = $this->calendarIntegration->sync_settings_display ?? [];
        return $settings['calendar_color'] ?? '#4285F4';
    }

    /**
     * Get CSS classes for display
     */
    public function getCssClasses(): array
    {
        $classes = ['calendar-event'];

        $classes[] = 'provider-' . $this->calendarIntegration->provider;
        $classes[] = $this->is_all_day ? 'all-day' : 'timed';
        $classes[] = $this->blocks_booking ? 'blocks-booking' : 'allows-booking';

        if ($this->isPast()) {
            $classes[] = 'past-event';
        } elseif ($this->isCurrent()) {
            $classes[] = 'current-event';
        } else {
            $classes[] = 'future-event';
        }

        if ($this->hasBookingConflicts()) {
            $classes[] = 'has-conflicts';
            $classes[] = 'conflict-' . $this->getConflictSeverity();
        }

        if ($this->needsResync()) {
            $classes[] = 'needs-resync';
        }

        return $classes;
    }

    /**
     * Get formatted time range
     */
    public function getTimeRange(): string
    {
        if ($this->is_all_day) {
            return 'All Day';
        }

        $start = $this->starts_at->format('g:i A');
        $end = $this->ends_at->format('g:i A');

        return "{$start} - {$end}";
    }

    /**
     * Get event summary for display
     */
    public function getSummary(): string
    {
        $parts = [$this->title];

        if (!$this->is_all_day) {
            $parts[] = $this->getTimeRange();
        }

        if ($this->blocks_booking && $this->hasBookingConflicts()) {
            $parts[] = '⚠️ Conflicts';
        }

        return implode(' • ', $parts);
    }

    // Static Helper Methods

    /**
     * Find events that overlap with a time range
     */
    public static function findOverlapping(Carbon $startTime, Carbon $endTime, ?int $integrationId = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = static::where(function ($q) use ($startTime, $endTime) {
            $q->where('starts_at', '<', $endTime)
                ->where('ends_at', '>', $startTime);
        });

        if ($integrationId) {
            $query->where('calendar_integration_id', $integrationId);
        }

        return $query->get();
    }

    /**
     * Get events for a specific date
     */
    public static function forDate(Carbon $date, ?int $integrationId = null): \Illuminate\Database\Eloquent\Collection
    {
        $startOfDay = $date->copy()->startOfDay();
        $endOfDay = $date->copy()->endOfDay();

        return static::findOverlapping($startOfDay, $endOfDay, $integrationId);
    }

    /**
     * Clean up old events
     */
    public static function cleanupOldEvents(int $daysToKeep = 90): int
    {
        $cutoffDate = now()->subDays($daysToKeep);

        return static::where('ends_at', '<', $cutoffDate)->delete();
    }
}
