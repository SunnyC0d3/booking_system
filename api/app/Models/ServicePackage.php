<?php

namespace App\Models;

use App\Services\V1\Bookings\TimeSlotService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class ServicePackage extends Model implements HasMedia
{
    use HasFactory, SoftDeletes, InteractsWithMedia;

    protected $fillable = [
        'name',
        'description',
        'short_description',
        'total_price',
        'discount_amount',
        'discount_percentage',
        'individual_price_total',
        'total_duration_minutes',
        'is_active',
        'requires_consultation',
        'consultation_duration_minutes',
        'max_advance_booking_days',
        'min_advance_booking_hours',
        'cancellation_policy',
        'terms_and_conditions',
        'sort_order',
        'metadata',
    ];

    protected $casts = [
        'total_price' => 'integer',
        'discount_amount' => 'integer',
        'discount_percentage' => 'decimal:2',
        'individual_price_total' => 'integer',
        'total_duration_minutes' => 'integer',
        'is_active' => 'boolean',
        'requires_consultation' => 'boolean',
        'consultation_duration_minutes' => 'integer',
        'max_advance_booking_days' => 'integer',
        'min_advance_booking_hours' => 'integer',
        'sort_order' => 'integer',
        'metadata' => 'array',
    ];

    // Relationships
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'service_package_items')
            ->withPivot(['quantity', 'order', 'is_optional', 'notes'])
            ->withTimestamps()
            ->orderBy('service_package_items.order');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function packageItems(): HasMany
    {
        return $this->hasMany(ServicePackageItem::class);
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('is_active', false);
    }

    public function scopeWithConsultation(Builder $query): Builder
    {
        return $query->where('requires_consultation', true);
    }

    public function scopeWithoutConsultation(Builder $query): Builder
    {
        return $query->where('requires_consultation', false);
    }

    public function scopeOrderedByPrice(Builder $query, string $direction = 'asc'): Builder
    {
        return $query->orderBy('total_price', $direction);
    }

    public function scopeOrderedByDuration(Builder $query, string $direction = 'asc'): Builder
    {
        return $query->orderBy('total_duration_minutes', $direction);
    }

    public function scopeBySort(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    // Accessors & Mutators
    public function getFormattedTotalPriceAttribute(): string
    {
        return '£' . number_format($this->total_price / 100, 2);
    }

    public function getFormattedIndividualPriceTotalAttribute(): string
    {
        return '£' . number_format($this->individual_price_total / 100, 2);
    }

    public function getFormattedDiscountAmountAttribute(): string
    {
        return '£' . number_format($this->discount_amount / 100, 2);
    }

    public function getFormattedDurationAttribute(): string
    {
        $hours = floor($this->total_duration_minutes / 60);
        $minutes = $this->total_duration_minutes % 60;

        if ($hours > 0 && $minutes > 0) {
            return "{$hours}h {$minutes}m";
        } elseif ($hours > 0) {
            return "{$hours}h";
        } else {
            return "{$minutes}m";
        }
    }

    public function getSavingsAttribute(): int
    {
        return $this->individual_price_total - $this->total_price;
    }

    public function getFormattedSavingsAttribute(): string
    {
        return '£' . number_format($this->savings / 100, 2);
    }

    public function getSavingsPercentageAttribute(): float
    {
        if ($this->individual_price_total <= 0) {
            return 0;
        }

        return round(($this->savings / $this->individual_price_total) * 100, 1);
    }

    public function getIsDiscountedAttribute(): bool
    {
        return $this->savings > 0;
    }

    public function getServiceCountAttribute(): int
    {
        return $this->services()->count();
    }

    public function getRequiredServicesAttribute()
    {
        return $this->services()->wherePivot('is_optional', false)->get();
    }

    public function getOptionalServicesAttribute()
    {
        return $this->services()->wherePivot('is_optional', true)->get();
    }

    public function getTotalEstimatedDurationAttribute(): int
    {
        return $this->services->sum(function ($service) {
            $quantity = $service->pivot->quantity ?? 1;
            return $service->duration_minutes * $quantity;
        });
    }

    // Helper methods
    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function isAvailableForBooking(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        // Check if all required services are active
        $requiredServices = $this->required_services;

        foreach ($requiredServices as $service) {
            if (!$service->isActive()) {
                return false;
            }
        }

        return true;
    }

    public function hasService(int $serviceId): bool
    {
        return $this->services()->where('services.id', $serviceId)->exists();
    }

    public function addService(
        int $serviceId,
        int $quantity = 1,
        int $order = null,
        bool $isOptional = false,
        ?string $notes = null
    ): void {
        $order = $order ?? ($this->packageItems()->max('order') + 1);

        $this->services()->attach($serviceId, [
            'quantity' => $quantity,
            'order' => $order,
            'is_optional' => $isOptional,
            'notes' => $notes,
        ]);

        $this->recalculatePricing();
    }

    public function removeService(int $serviceId): void
    {
        $this->services()->detach($serviceId);
        $this->recalculatePricing();
    }

    public function updateServiceQuantity(int $serviceId, int $quantity): void
    {
        $this->services()->updateExistingPivot($serviceId, ['quantity' => $quantity]);
        $this->recalculatePricing();
    }

    public function recalculatePricing(): void
    {
        $totalPrice = 0;
        $totalDuration = 0;

        foreach ($this->services as $service) {
            $quantity = $service->pivot->quantity ?? 1;
            $totalPrice += $service->base_price * $quantity;
            $totalDuration += $service->duration_minutes * $quantity;
        }

        $this->update([
            'individual_price_total' => $totalPrice,
            'total_duration_minutes' => $totalDuration,
        ]);
    }

    public function applyDiscount(int $discountAmount = null, float $discountPercentage = null): void
    {
        if ($discountAmount !== null) {
            $this->update([
                'discount_amount' => $discountAmount,
                'discount_percentage' => null,
                'total_price' => max(0, $this->individual_price_total - $discountAmount),
            ]);
        } elseif ($discountPercentage !== null) {
            $calculatedDiscount = (int) round($this->individual_price_total * ($discountPercentage / 100));
            $this->update([
                'discount_amount' => $calculatedDiscount,
                'discount_percentage' => $discountPercentage,
                'total_price' => max(0, $this->individual_price_total - $calculatedDiscount),
            ]);
        }
    }

    public function removeDiscount(): void
    {
        $this->update([
            'discount_amount' => 0,
            'discount_percentage' => null,
            'total_price' => $this->individual_price_total,
        ]);
    }

    public function canBeBookedOn(\Carbon\Carbon $date): bool
    {
        if (!$this->isAvailableForBooking()) {
            return false;
        }

        $now = \Carbon\Carbon::now();

        // Check minimum advance booking
        if ($this->min_advance_booking_hours) {
            $minTime = $now->clone()->addHours($this->min_advance_booking_hours);
            if ($date->lt($minTime)) {
                return false;
            }
        }

        // Check maximum advance booking
        if ($this->max_advance_booking_days) {
            $maxTime = $now->clone()->addDays($this->max_advance_booking_days);
            if ($date->gt($maxTime)) {
                return false;
            }
        }

        return true;
    }

    public function getAvailableTimeSlots(
        \Carbon\Carbon $date,
        ?ServiceLocation $location = null
    ): \Illuminate\Support\Collection {
        $availableSlots = collect();

        // For packages, we need to check availability for the longest service
        // or find common availability windows across all services
        $requiredServices = $this->required_services;

        if ($requiredServices->isEmpty()) {
            return $availableSlots;
        }

        // Get availability for each required service
        $serviceSlots = [];
        foreach ($requiredServices as $service) {
            $timeSlotService = app(TimeSlotService::class);
            $slots = $timeSlotService->getAvailableSlots(
                $service,
                $date->clone()->startOfDay(),
                $date->clone()->endOfDay(),
                $location,
                $this->total_duration_minutes
            );
            $serviceSlots[$service->id] = $slots;
        }

        // Find overlapping time slots (simplified approach)
        if (!empty($serviceSlots)) {
            $firstServiceSlots = reset($serviceSlots);

            foreach ($firstServiceSlots as $slot) {
                $slotAvailable = true;

                // Check if this slot works for all required services
                foreach ($requiredServices->skip(1) as $service) {
                    $serviceId = $service->id;
                    $hasMatchingSlot = $serviceSlots[$serviceId]->contains(function ($s) use ($slot) {
                        return $s['start_time']->equalTo($slot['start_time']);
                    });

                    if (!$hasMatchingSlot) {
                        $slotAvailable = false;
                        break;
                    }
                }

                if ($slotAvailable) {
                    $availableSlots->push([
                        'start_time' => $slot['start_time'],
                        'end_time' => $slot['start_time']->clone()->addMinutes($this->total_duration_minutes),
                        'duration_minutes' => $this->total_duration_minutes,
                        'is_available' => true,
                        'package_id' => $this->id,
                        'location_id' => $location?->id,
                    ]);
                }
            }
        }

        return $availableSlots->sortBy('start_time');
    }

    public function createBookingFromPackage(
        \App\Models\User $user,
        \Carbon\Carbon $scheduledAt,
        ?ServiceLocation $location = null,
        array $selectedOptionalServices = [],
        array $bookingData = []
    ): \App\Models\Booking {
        // Calculate final pricing including optional services
        $totalPrice = $this->total_price;
        $totalDuration = $this->total_duration_minutes;

        foreach ($selectedOptionalServices as $serviceId) {
            $optionalService = $this->services()->where('services.id', $serviceId)->first();
            if ($optionalService && $optionalService->pivot->is_optional) {
                $quantity = $optionalService->pivot->quantity ?? 1;
                $totalPrice += $optionalService->base_price * $quantity;
                $totalDuration += $optionalService->duration_minutes * $quantity;
            }
        }

        // Create the main booking
        $booking = \App\Models\Booking::create(array_merge([
            'user_id' => $user->id,
            'service_package_id' => $this->id,
            'service_location_id' => $location?->id,
            'scheduled_at' => $scheduledAt,
            'ends_at' => $scheduledAt->clone()->addMinutes($totalDuration),
            'duration_minutes' => $totalDuration,
            'base_price' => $this->total_price,
            'total_amount' => $totalPrice,
            'status' => \App\Constants\BookingStatuses::PENDING,
            'payment_status' => \App\Constants\PaymentStatuses::PENDING,
            'requires_consultation' => $this->requires_consultation,
            'client_name' => $user->name,
            'client_email' => $user->email,
        ], $bookingData));

        // Create individual service bookings for each service in the package
        $currentTime = $scheduledAt->clone();

        foreach ($this->required_services as $service) {
            $quantity = $service->pivot->quantity ?? 1;

            for ($i = 0; $i < $quantity; $i++) {
                \App\Models\ServiceBooking::create([
                    'booking_id' => $booking->id,
                    'service_id' => $service->id,
                    'scheduled_at' => $currentTime->clone(),
                    'ends_at' => $currentTime->clone()->addMinutes($service->duration_minutes),
                    'duration_minutes' => $service->duration_minutes,
                    'price' => $service->base_price,
                    'order' => $service->pivot->order,
                    'notes' => $service->pivot->notes,
                ]);

                $currentTime->addMinutes($service->duration_minutes + ($service->buffer_minutes ?? 0));
            }
        }

        // Add selected optional services
        foreach ($selectedOptionalServices as $serviceId) {
            $optionalService = $this->services()->where('services.id', $serviceId)->first();
            if ($optionalService && $optionalService->pivot->is_optional) {
                $quantity = $optionalService->pivot->quantity ?? 1;

                for ($i = 0; $i < $quantity; $i++) {
                    \App\Models\ServiceBooking::create([
                        'booking_id' => $booking->id,
                        'service_id' => $optionalService->id,
                        'scheduled_at' => $currentTime->clone(),
                        'ends_at' => $currentTime->clone()->addMinutes($optionalService->duration_minutes),
                        'duration_minutes' => $optionalService->duration_minutes,
                        'price' => $optionalService->base_price,
                        'order' => $optionalService->pivot->order,
                        'is_optional' => true,
                        'notes' => $optionalService->pivot->notes,
                    ]);

                    $currentTime->addMinutes($optionalService->duration_minutes + ($optionalService->buffer_minutes ?? 0));
                }
            }
        }

        return $booking;
    }

    public function duplicate(string $newName): self
    {
        $duplicate = $this->replicate([
            'name',
            'created_at',
            'updated_at',
        ]);

        $duplicate->name = $newName;
        $duplicate->is_active = false; // Start as inactive
        $duplicate->save();

        // Copy service relationships
        foreach ($this->services as $service) {
            $duplicate->services()->attach($service->id, [
                'quantity' => $service->pivot->quantity,
                'order' => $service->pivot->order,
                'is_optional' => $service->pivot->is_optional,
                'notes' => $service->pivot->notes,
            ]);
        }

        // Copy media
        $this->copyMedia()->each(function ($file) use ($duplicate) {
            $duplicate->addMediaFromUrl($file->getUrl())
                ->usingName($file->name)
                ->toMediaCollection($file->collection_name);
        });

        $duplicate->recalculatePricing();

        return $duplicate;
    }

    // Static helper methods
    public static function createFromServices(
        string $name,
        array $serviceIds,
        ?int $discountAmount = null,
        ?float $discountPercentage = null
    ): self {
        $package = self::create([
            'name' => $name,
            'is_active' => false, // Start inactive until configured
        ]);

        foreach ($serviceIds as $index => $serviceId) {
            $package->addService($serviceId, 1, $index + 1);
        }

        if ($discountAmount || $discountPercentage) {
            $package->applyDiscount($discountAmount, $discountPercentage);
        }

        return $package;
    }

    public static function getPopularPackages(int $limit = 5): \Illuminate\Database\Eloquent\Collection
    {
        return self::active()
            ->withCount('bookings')
            ->orderBy('bookings_count', 'desc')
            ->limit($limit)
            ->get();
    }

    public static function getPackagesByPriceRange(int $minPrice, int $maxPrice): \Illuminate\Database\Eloquent\Collection
    {
        return self::active()
            ->whereBetween('total_price', [$minPrice, $maxPrice])
            ->orderBy('total_price')
            ->get();
    }

    // Validation rules
    public static function getValidationRules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'short_description' => 'nullable|string|max:500',
            'total_price' => 'required|integer|min:0',
            'discount_amount' => 'nullable|integer|min:0',
            'discount_percentage' => 'nullable|numeric|min:0|max:100',
            'is_active' => 'boolean',
            'requires_consultation' => 'boolean',
            'consultation_duration_minutes' => 'nullable|integer|min:0',
            'max_advance_booking_days' => 'nullable|integer|min:1|max:365',
            'min_advance_booking_hours' => 'nullable|integer|min:0|max:8760',
            'cancellation_policy' => 'nullable|string',
            'terms_and_conditions' => 'nullable|string',
            'sort_order' => 'nullable|integer|min:0',
        ];
    }

    // Boot method for model events
    protected static function booted(): void
    {
        static::creating(function (self $package) {
            if (!$package->sort_order) {
                $package->sort_order = (self::max('sort_order') ?? 0) + 1;
            }
        });

        static::saved(function (self $package) {
            // Recalculate pricing when package is saved
            if ($package->wasRecentlyCreated || $package->wasChanged(['discount_amount', 'discount_percentage'])) {
                $package->recalculatePricing();
            }
        });
    }
}

// Pivot model for package items
class ServicePackageItem extends Model
{
    protected $fillable = [
        'service_package_id',
        'service_id',
        'quantity',
        'order',
        'is_optional',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'order' => 'integer',
        'is_optional' => 'boolean',
    ];

    public function package(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ServicePackage::class, 'service_package_id');
    }

    public function service(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function getTotalPriceAttribute(): int
    {
        return $this->service->base_price * $this->quantity;
    }

    public function getFormattedTotalPriceAttribute(): string
    {
        return '£' . number_format($this->total_price / 100, 2);
    }

    public function getTotalDurationMinutesAttribute(): int
    {
        return $this->service->duration_minutes * $this->quantity;
    }
}
