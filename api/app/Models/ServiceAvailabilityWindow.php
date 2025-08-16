<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class ServiceAvailabilityWindow extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'service_id',
        'service_location_id',
        'type',
        'pattern',
        'day_of_week',
        'start_date',
        'end_date',
        'start_time',
        'end_time',
        'max_bookings',
        'slot_duration_minutes',
        'break_duration_minutes',
        'min_advance_booking_hours',
        'max_advance_booking_days',
        'is_active',
        'is_bookable',
        'price_modifier',
        'price_modifier_type',
        'title',
        'description',
        'metadata',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
        'start_time' => 'datetime:H:i:s',
        'end_time' => 'datetime:H:i:s',
        'max_bookings' => 'integer',
        'slot_duration_minutes' => 'integer',
        'break_duration_minutes' => 'integer',
        'min_advance_booking_hours' => 'integer',
        'max_advance_booking_days' => 'integer',
        'is_active' => 'boolean',
        'is_bookable' => 'boolean',
        'price_modifier' => 'integer',
        'metadata' => 'array',
    ];

    // Relationships
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function serviceLocation(): BelongsTo
    {
        return $this->belongsTo(ServiceLocation::class);
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeBookable(Builder $query): Builder
    {
        return $query->where('is_bookable', true);
    }

    public function scopeRegular(Builder $query): Builder
    {
        return $query->where('type', 'regular');
    }

    public function scopeException(Builder $query): Builder
    {
        return $query->where('type', 'exception');
    }

    public function scopeBlocked(Builder $query): Builder
    {
        return $query->where('type', 'blocked');
    }

    public function scopeWeekly(Builder $query): Builder
    {
        return $query->where('pattern', 'weekly');
    }

    public function scopeForDate(Builder $query, Carbon $date): Builder
    {
        return $query->where(function ($q) use ($date) {
            $q->where(function ($sq) use ($date) {
                // Weekly pattern matching day of week
                $sq->where('pattern', 'weekly')
                    ->where('day_of_week', $date->dayOfWeek);
            })->orWhere(function ($sq) use ($date) {
                // Specific date
                $sq->where('pattern', 'specific_date')
                    ->where('start_date', $date->format('Y-m-d'));
            })->orWhere(function ($sq) use ($date) {
                // Date range
                $sq->where('pattern', 'date_range')
                    ->where('start_date', '<=', $date->format('Y-m-d'))
                    ->where('end_date', '>=', $date->format('Y-m-d'));
            });
        });
    }

    public function scopeForDayOfWeek(Builder $query, int $dayOfWeek): Builder
    {
        return $query->where('day_of_week', $dayOfWeek);
    }

    // Helper methods
    public function getDayNameAttribute(): ?string
    {
        if (is_null($this->day_of_week)) {
            return null;
        }

        return Carbon::create()->dayOfWeek($this->day_of_week)->format('l');
    }

    public function getFormattedTimeRangeAttribute(): string
    {
        return Carbon::parse($this->start_time)->format('H:i') . ' - ' .
            Carbon::parse($this->end_time)->format('H:i');
    }

    public function getFormattedPriceModifierAttribute(): ?string
    {
        if (is_null($this->price_modifier)) {
            return null;
        }

        $amount = abs($this->price_modifier);
        $sign = $this->price_modifier >= 0 ? '+' : '-';

        if ($this->price_modifier_type === 'percentage') {
            return $sign . number_format($amount, 2) . '%';
        }

        return $sign . '£' . number_format($amount / 100, 2);
    }

    public function getDurationMinutes(): int
    {
        $start = Carbon::parse($this->start_time);
        $end = Carbon::parse($this->end_time);

        // Handle overnight windows (end time next day)
        if ($end->lessThan($start)) {
            $end->addDay();
        }

        return $end->diffInMinutes($start);
    }

    public function getSlotDurationMinutes(): int
    {
        return $this->slot_duration_minutes ?? $this->service->duration_minutes;
    }

    public function getTotalSlotDurationMinutes(): int
    {
        return $this->getSlotDurationMinutes() + $this->break_duration_minutes;
    }

    public function getMaxSlotsCount(): int
    {
        $windowDuration = $this->getDurationMinutes();
        $slotDuration = $this->getTotalSlotDurationMinutes();

        if ($slotDuration <= 0) {
            return 0;
        }

        return floor($windowDuration / $slotDuration);
    }

    public function isValidForDate(Carbon $date): bool
    {
        if (!$this->is_active || !$this->is_bookable) {
            return false;
        }

        return match($this->pattern) {
            'weekly' => $date->dayOfWeek === $this->day_of_week,
            'specific_date' => $date->isSameDay($this->start_date),
            'date_range' => $date->isBetween($this->start_date, $this->end_date, true),
            'daily' => true,
            default => false,
        };
    }

    public function getAvailableSlots(Carbon $date): array
    {
        if (!$this->isValidForDate($date)) {
            return [];
        }

        $slots = [];
        $slotDuration = $this->getSlotDurationMinutes();
        $totalDuration = $this->getTotalSlotDurationMinutes();

        $currentTime = Carbon::parse($this->start_time)
            ->setDate($date->year, $date->month, $date->day);

        $endTime = Carbon::parse($this->end_time)
            ->setDate($date->year, $date->month, $date->day);

        // Handle overnight windows
        if ($endTime->lessThan($currentTime)) {
            $endTime->addDay();
        }

        while ($currentTime->clone()->addMinutes($slotDuration)->lessThanOrEqualTo($endTime)) {
            $slotEnd = $currentTime->clone()->addMinutes($slotDuration);

            $slots[] = [
                'start_time' => $currentTime->format('H:i'),
                'end_time' => $slotEnd->format('H:i'),
                'start_datetime' => $currentTime->clone(),
                'end_datetime' => $slotEnd->clone(),
                'duration_minutes' => $slotDuration,
                'is_available' => $this->isSlotAvailable($currentTime),
                'booking_count' => $this->getSlotBookingCount($currentTime),
                'max_bookings' => $this->max_bookings,
            ];

            $currentTime->addMinutes($totalDuration);
        }

        return $slots;
    }

    public function isSlotAvailable(Carbon $slotStart): bool
    {
        $slotEnd = $slotStart->clone()->addMinutes($this->getSlotDurationMinutes());

        // Check if slot is in the past
        if ($slotEnd->isPast()) {
            return false;
        }

        // Check minimum advance booking
        $minAdvanceHours = $this->min_advance_booking_hours ?? $this->service->min_advance_booking_hours;
        if ($slotStart->lessThan(now()->addHours($minAdvanceHours))) {
            return false;
        }

        // Check maximum advance booking
        $maxAdvanceDays = $this->max_advance_booking_days ?? $this->service->max_advance_booking_days;
        if ($slotStart->greaterThan(now()->addDays($maxAdvanceDays))) {
            return false;
        }

        // Check capacity
        return $this->getSlotBookingCount($slotStart) < $this->max_bookings;
    }

    public function getSlotBookingCount(Carbon $slotStart): int
    {
        $slotEnd = $slotStart->clone()->addMinutes($this->getSlotDurationMinutes());

        $query = Booking::where('service_id', $this->service_id)
            ->whereIn('status', ['confirmed', 'in_progress'])
            ->where(function ($q) use ($slotStart, $slotEnd) {
                $q->where(function ($sq) use ($slotStart, $slotEnd) {
                    // Booking starts during this slot
                    $sq->where('scheduled_at', '>=', $slotStart)
                        ->where('scheduled_at', '<', $slotEnd);
                })->orWhere(function ($sq) use ($slotStart, $slotEnd) {
                    // Booking ends during this slot
                    $sq->where('ends_at', '>', $slotStart)
                        ->where('ends_at', '<=', $slotEnd);
                })->orWhere(function ($sq) use ($slotStart, $slotEnd) {
                    // Booking encompasses this slot
                    $sq->where('scheduled_at', '<=', $slotStart)
                        ->where('ends_at', '>=', $slotEnd);
                });
            });

        if ($this->service_location_id) {
            $query->where('service_location_id', $this->service_location_id);
        }

        return $query->count();
    }

    public function calculatePriceForSlot(int $basePrice): int
    {
        if (is_null($this->price_modifier)) {
            return $basePrice;
        }

        if ($this->price_modifier_type === 'percentage') {
            return $basePrice + (int) round($basePrice * ($this->price_modifier / 10000));
        }

        return $basePrice + $this->price_modifier;
    }

    public function getTypeDisplayNameAttribute(): string
    {
        return match($this->type) {
            'regular' => 'Regular Hours',
            'exception' => 'Exception',
            'special_hours' => 'Special Hours',
            'blocked' => 'Blocked Time',
            default => ucfirst($this->type)
        };
    }

    public function getPatternDisplayNameAttribute(): string
    {
        return match($this->pattern) {
            'weekly' => 'Weekly',
            'daily' => 'Daily',
            'date_range' => 'Date Range',
            'specific_date' => 'Specific Date',
            default => ucfirst($this->pattern)
        };
    }
}
