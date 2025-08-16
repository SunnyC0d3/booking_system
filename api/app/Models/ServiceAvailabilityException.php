<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class ServiceAvailabilityException extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'service_id',
        'service_location_id',
        'exception_date',
        'exception_type',
        'start_time',
        'end_time',
        'max_bookings',
        'price_modifier',
        'price_modifier_type',
        'reason',
        'notes',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'exception_date' => 'date',
        'start_time' => 'datetime:H:i:s',
        'end_time' => 'datetime:H:i:s',
        'max_bookings' => 'integer',
        'price_modifier' => 'integer',
        'is_active' => 'boolean',
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

    public function scopeBlocked(Builder $query): Builder
    {
        return $query->where('exception_type', 'blocked');
    }

    public function scopeCustomHours(Builder $query): Builder
    {
        return $query->where('exception_type', 'custom_hours');
    }

    public function scopeSpecialPricing(Builder $query): Builder
    {
        return $query->where('exception_type', 'special_pricing');
    }

    public function scopeForDate(Builder $query, Carbon $date): Builder
    {
        return $query->where('exception_date', $date->format('Y-m-d'));
    }

    public function scopeForDateRange(Builder $query, Carbon $startDate, Carbon $endDate): Builder
    {
        return $query->whereBetween('exception_date', [
            $startDate->format('Y-m-d'),
            $endDate->format('Y-m-d')
        ]);
    }

    public function scopeForService(Builder $query, int $serviceId): Builder
    {
        return $query->where('service_id', $serviceId);
    }

    public function scopeForLocation(Builder $query, ?int $locationId): Builder
    {
        return $query->where(function ($q) use ($locationId) {
            if ($locationId) {
                $q->where('service_location_id', $locationId)
                    ->orWhereNull('service_location_id');
            } else {
                $q->whereNull('service_location_id');
            }
        });
    }

    // Helper methods
    public function isBlocked(): bool
    {
        return $this->exception_type === 'blocked';
    }

    public function hasCustomHours(): bool
    {
        return $this->exception_type === 'custom_hours';
    }

    public function hasSpecialPricing(): bool
    {
        return $this->exception_type === 'special_pricing';
    }

    public function getFormattedDateAttribute(): string
    {
        return $this->exception_date->format('M j, Y');
    }

    public function getFormattedTimeRangeAttribute(): ?string
    {
        if (!$this->start_time || !$this->end_time) {
            return null;
        }

        return $this->start_time->format('g:i A') . ' - ' . $this->end_time->format('g:i A');
    }

    public function getFormattedPriceModifierAttribute(): ?string
    {
        if (!$this->price_modifier) {
            return null;
        }

        if ($this->price_modifier_type === 'percentage') {
            $sign = $this->price_modifier > 0 ? '+' : '';
            return $sign . $this->price_modifier . '%';
        }

        $amount = abs($this->price_modifier) / 100;
        $sign = $this->price_modifier > 0 ? '+' : '-';
        return $sign . '£' . number_format($amount, 2);
    }

    public function getExceptionTypeDisplayAttribute(): string
    {
        return match ($this->exception_type) {
            'blocked' => 'Blocked',
            'custom_hours' => 'Custom Hours',
            'special_pricing' => 'Special Pricing',
            default => ucfirst($this->exception_type)
        };
    }

    public function getStatusDisplayAttribute(): string
    {
        if (!$this->is_active) {
            return 'Inactive';
        }

        $now = Carbon::now();
        $exceptionDate = $this->exception_date;

        if ($exceptionDate->isToday()) {
            return 'Active Today';
        } elseif ($exceptionDate->isFuture()) {
            return 'Scheduled';
        } else {
            return 'Past';
        }
    }

    public function isApplicableToday(): bool
    {
        return $this->is_active && $this->exception_date->isToday();
    }

    public function isApplicableOn(Carbon $date): bool
    {
        return $this->is_active && $this->exception_date->isSameDay($date);
    }

    public function getDurationMinutes(): ?int
    {
        if (!$this->start_time || !$this->end_time) {
            return null;
        }

        return $this->start_time->diffInMinutes($this->end_time);
    }

    public function conflicts(Carbon $startTime, Carbon $endTime): bool
    {
        if (!$this->start_time || !$this->end_time) {
            return false;
        }

        $exceptionStart = $this->exception_date->clone()
            ->setTimeFromTimeString($this->start_time->format('H:i:s'));
        $exceptionEnd = $this->exception_date->clone()
            ->setTimeFromTimeString($this->end_time->format('H:i:s'));

        return $startTime->lt($exceptionEnd) && $endTime->gt($exceptionStart);
    }

    public function calculatePriceAdjustment(int $basePrice): int
    {
        if (!$this->price_modifier) {
            return $basePrice;
        }

        if ($this->price_modifier_type === 'percentage') {
            $adjustment = ($basePrice * $this->price_modifier) / 100;
            return $basePrice + (int) round($adjustment);
        }

        return $basePrice + $this->price_modifier;
    }

    // Static helper methods
    public static function createBlockedDate(
        int $serviceId,
        Carbon $date,
        ?int $locationId = null,
        string $reason = null
    ): self {
        return self::create([
            'service_id' => $serviceId,
            'service_location_id' => $locationId,
            'exception_date' => $date,
            'exception_type' => 'blocked',
            'reason' => $reason ?? 'Date blocked for bookings',
            'is_active' => true,
        ]);
    }

    public static function createCustomHours(
        int $serviceId,
        Carbon $date,
        Carbon $startTime,
        Carbon $endTime,
        ?int $locationId = null,
        string $reason = null
    ): self {
        return self::create([
            'service_id' => $serviceId,
            'service_location_id' => $locationId,
            'exception_date' => $date,
            'exception_type' => 'custom_hours',
            'start_time' => $startTime,
            'end_time' => $endTime,
            'reason' => $reason ?? 'Custom operating hours',
            'is_active' => true,
        ]);
    }

    public static function createSpecialPricing(
        int $serviceId,
        Carbon $date,
        int $priceModifier,
        string $modifierType = 'fixed',
        ?int $locationId = null,
        string $reason = null
    ): self {
        return self::create([
            'service_id' => $serviceId,
            'service_location_id' => $locationId,
            'exception_date' => $date,
            'exception_type' => 'special_pricing',
            'price_modifier' => $priceModifier,
            'price_modifier_type' => $modifierType,
            'reason' => $reason ?? 'Special pricing in effect',
            'is_active' => true,
        ]);
    }

    // Validation rules for different exception types
    public static function getValidationRules(string $exceptionType): array
    {
        $baseRules = [
            'service_id' => 'required|exists:services,id',
            'service_location_id' => 'nullable|exists:service_locations,id',
            'exception_date' => 'required|date|after_or_equal:today',
            'exception_type' => 'required|in:blocked,custom_hours,special_pricing',
            'reason' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
        ];

        return match ($exceptionType) {
            'blocked' => $baseRules,
            'custom_hours' => array_merge($baseRules, [
                'start_time' => 'required|date_format:H:i',
                'end_time' => 'required|date_format:H:i|after:start_time',
                'max_bookings' => 'nullable|integer|min:0',
            ]),
            'special_pricing' => array_merge($baseRules, [
                'price_modifier' => 'required|integer',
                'price_modifier_type' => 'required|in:fixed,percentage',
                'start_time' => 'nullable|date_format:H:i',
                'end_time' => 'nullable|date_format:H:i|after:start_time',
            ]),
            default => $baseRules
        };
    }
}
