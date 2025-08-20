<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class BookingCapacitySlot extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_id',
        'service_location_id',
        'slot_datetime',
        'max_capacity',
        'current_bookings',
        'blocked_slots',
        'is_blocked',
        'block_reason',
        'metadata',
    ];

    protected $casts = [
        'slot_datetime' => 'datetime',
        'max_capacity' => 'integer',
        'current_bookings' => 'integer',
        'blocked_slots' => 'integer',
        'is_blocked' => 'boolean',
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

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'scheduled_at', 'slot_datetime')
            ->where('service_id', $this->service_id)
            ->when($this->service_location_id, function ($query) {
                $query->where('service_location_id', $this->service_location_id);
            });
    }

    // Scopes
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('is_blocked', false)
            ->whereRaw('(max_capacity - current_bookings - blocked_slots) > 0');
    }

    public function scopeBlocked(Builder $query): Builder
    {
        return $query->where('is_blocked', true);
    }

    public function scopeFullyBooked(Builder $query): Builder
    {
        return $query->whereRaw('current_bookings >= max_capacity - blocked_slots');
    }

    public function scopeForService(Builder $query, int $serviceId): Builder
    {
        return $query->where('service_id', $serviceId);
    }

    public function scopeForLocation(Builder $query, ?int $locationId): Builder
    {
        if ($locationId) {
            return $query->where('service_location_id', $locationId);
        }

        return $query->whereNull('service_location_id');
    }

    public function scopeForDate(Builder $query, Carbon $date): Builder
    {
        return $query->whereDate('slot_datetime', $date);
    }

    public function scopeForDateRange(Builder $query, Carbon $startDate, Carbon $endDate): Builder
    {
        return $query->whereBetween('slot_datetime', [$startDate, $endDate]);
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('slot_datetime', '>', now());
    }

    public function scopePast(Builder $query): Builder
    {
        return $query->where('slot_datetime', '<', now());
    }

    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('slot_datetime', today());
    }

    public function scopeThisWeek(Builder $query): Builder
    {
        return $query->whereBetween('slot_datetime', [
            now()->startOfWeek(),
            now()->endOfWeek()
        ]);
    }

    // Accessors & Mutators
    public function getAvailableSlotsAttribute(): int
    {
        return max(0, $this->max_capacity - $this->current_bookings - $this->blocked_slots);
    }

    public function getUtilizationPercentageAttribute(): float
    {
        if ($this->max_capacity === 0) {
            return 0;
        }

        return round(($this->current_bookings / $this->max_capacity) * 100, 1);
    }

    public function getStatusAttribute(): string
    {
        if ($this->is_blocked) {
            return 'blocked';
        }

        if ($this->available_slots <= 0) {
            return 'full';
        }

        if ($this->current_bookings === 0) {
            return 'available';
        }

        return 'partial';
    }

    public function getStatusDisplayAttribute(): string
    {
        return match ($this->status) {
            'blocked' => 'Blocked',
            'full' => 'Fully Booked',
            'available' => 'Available',
            'partial' => 'Partially Booked',
            default => 'Unknown'
        };
    }

    public function getFormattedSlotTimeAttribute(): string
    {
        return $this->slot_datetime->format('M j, Y g:i A');
    }

    public function getFormattedDateAttribute(): string
    {
        return $this->slot_datetime->format('M j, Y');
    }

    public function getFormattedTimeAttribute(): string
    {
        return $this->slot_datetime->format('g:i A');
    }

    public function getCapacitySummaryAttribute(): string
    {
        return "{$this->current_bookings}/{$this->max_capacity}";
    }

    // Helper methods
    public function isAvailable(int $requiredSlots = 1): bool
    {
        return !$this->is_blocked && $this->available_slots >= $requiredSlots;
    }

    public function isFullyBooked(): bool
    {
        return $this->current_bookings >= ($this->max_capacity - $this->blocked_slots);
    }

    public function isBlocked(): bool
    {
        return $this->is_blocked;
    }

    public function isPast(): bool
    {
        return $this->slot_datetime->isPast();
    }

    public function isToday(): bool
    {
        return $this->slot_datetime->isToday();
    }

    public function isUpcoming(): bool
    {
        return $this->slot_datetime->isFuture();
    }

    public function canBook(int $requiredSlots = 1): bool
    {
        return $this->isAvailable($requiredSlots) && $this->isUpcoming();
    }

    public function reserveSlots(int $count = 1): bool
    {
        if (!$this->canBook($count)) {
            return false;
        }

        $this->increment('current_bookings', $count);
        return true;
    }

    public function releaseSlots(int $count = 1): bool
    {
        if ($this->current_bookings < $count) {
            return false;
        }

        $this->decrement('current_bookings', $count);
        return true;
    }

    public function blockSlots(int $count, string $reason = null): bool
    {
        if ($this->available_slots < $count) {
            return false;
        }

        $this->increment('blocked_slots', $count);

        if ($reason) {
            $this->update(['block_reason' => $reason]);
        }

        return true;
    }

    public function unblockSlots(int $count): bool
    {
        if ($this->blocked_slots < $count) {
            return false;
        }

        $this->decrement('blocked_slots', $count);
        return true;
    }

    public function blockCompletely(string $reason = null): void
    {
        $this->update([
            'is_blocked' => true,
            'block_reason' => $reason ?? 'Slot blocked by administrator'
        ]);
    }

    public function unblock(): void
    {
        $this->update([
            'is_blocked' => false,
            'block_reason' => null
        ]);
    }

    public function adjustCapacity(int $newCapacity): bool
    {
        if ($newCapacity < $this->current_bookings) {
            return false; // Cannot reduce below current bookings
        }

        $this->update(['max_capacity' => $newCapacity]);
        return true;
    }

    public function getBookingsList(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->bookings()
            ->with(['user', 'service'])
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->orderBy('scheduled_at')
            ->get();
    }

    public function getTimeUntilSlot(): \Carbon\CarbonInterval
    {
        return now()->diffAsCarbonInterval($this->slot_datetime);
    }

    public function getWarnings(): array
    {
        $warnings = [];

        if ($this->isFullyBooked()) {
            $warnings[] = 'This slot is fully booked';
        }

        if ($this->isPast()) {
            $warnings[] = 'This slot is in the past';
        }

        if ($this->is_blocked) {
            $warnings[] = 'This slot is blocked: ' . ($this->block_reason ?? 'No reason provided');
        }

        if ($this->utilization_percentage > 80) {
            $warnings[] = 'This slot is nearly full';
        }

        return $warnings;
    }

    // Static helper methods
    public static function findOrCreateSlot(
        int $serviceId,
        Carbon $datetime,
        ?int $locationId = null,
        int $maxCapacity = 1
    ): self {
        return self::firstOrCreate([
            'service_id' => $serviceId,
            'service_location_id' => $locationId,
            'slot_datetime' => $datetime,
        ], [
            'max_capacity' => $maxCapacity,
            'current_bookings' => 0,
            'blocked_slots' => 0,
            'is_blocked' => false,
        ]);
    }

    public static function getAvailabilityForDate(
        int $serviceId,
        Carbon $date,
        ?int $locationId = null
    ): \Illuminate\Database\Eloquent\Collection {
        return self::forService($serviceId)
            ->forLocation($locationId)
            ->forDate($date)
            ->orderBy('slot_datetime')
            ->get();
    }

    public static function getAvailabilityForDateRange(
        int $serviceId,
        Carbon $startDate,
        Carbon $endDate,
        ?int $locationId = null
    ): \Illuminate\Database\Eloquent\Collection {
        return self::forService($serviceId)
            ->forLocation($locationId)
            ->forDateRange($startDate, $endDate)
            ->orderBy('slot_datetime')
            ->get();
    }

    public static function getCapacityStats(
        int $serviceId,
        Carbon $date,
        ?int $locationId = null
    ): array {
        $slots = self::getAvailabilityForDate($serviceId, $date, $locationId);

        return [
            'total_slots' => $slots->count(),
            'available_slots' => $slots->where('status', 'available')->count(),
            'partial_slots' => $slots->where('status', 'partial')->count(),
            'full_slots' => $slots->where('status', 'full')->count(),
            'blocked_slots' => $slots->where('status', 'blocked')->count(),
            'total_capacity' => $slots->sum('max_capacity'),
            'total_bookings' => $slots->sum('current_bookings'),
            'total_blocked' => $slots->sum('blocked_slots'),
            'utilization_percentage' => $slots->sum('max_capacity') > 0
                ? round(($slots->sum('current_bookings') / $slots->sum('max_capacity')) * 100, 1)
                : 0,
        ];
    }

    public static function cleanupPastSlots(): int
    {
        $cutoffDate = now()->subDays(30); // Keep slots for 30 days for reporting

        return self::where('slot_datetime', '<', $cutoffDate)
            ->where('current_bookings', 0)
            ->delete();
    }

    // Boot method for model events
    protected static function booted(): void
    {
        // Ensure available_slots never goes negative
        static::saving(function (self $slot) {
            if ($slot->current_bookings < 0) {
                $slot->current_bookings = 0;
            }

            if ($slot->blocked_slots < 0) {
                $slot->blocked_slots = 0;
            }

            if ($slot->max_capacity < 1) {
                $slot->max_capacity = 1;
            }
        });
    }
}
