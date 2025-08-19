<?php

namespace App\Models;

use App\Constants\BookingStatuses;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Carbon\Carbon;

class Service extends Model implements HasMedia
{
    use HasFactory, SoftDeletes, InteractsWithMedia;

    protected $fillable = [
        'name',
        'description',
        'short_description',
        'category',
        'base_price',
        'duration_minutes',
        'is_active',
        'is_bookable',
        'requires_consultation',
        'consultation_duration_minutes',
        'requires_deposit',
        'deposit_percentage',
        'deposit_amount',
        'min_advance_booking_hours',
        'max_advance_booking_days',
        'cancellation_policy',
        'terms_and_conditions',
        'preparation_notes',
        'buffer_minutes',
        'sort_order',
        'metadata',
    ];

    protected $casts = [
        'base_price' => 'integer',
        'duration_minutes' => 'integer',
        'is_active' => 'boolean',
        'is_bookable' => 'boolean',
        'requires_consultation' => 'boolean',
        'consultation_duration_minutes' => 'integer',
        'requires_deposit' => 'boolean',
        'deposit_percentage' => 'decimal:2',
        'deposit_amount' => 'integer',
        'min_advance_booking_hours' => 'integer',
        'max_advance_booking_days' => 'integer',
        'buffer_minutes' => 'integer',
        'sort_order' => 'integer',
        'metadata' => 'array',
    ];

    public function locations(): HasMany
    {
        return $this->hasMany(ServiceLocation::class);
    }

    public function serviceLocations(): HasMany
    {
        return $this->hasMany(ServiceLocation::class);
    }

    public function addOns(): HasMany
    {
        return $this->hasMany(ServiceAddOn::class);
    }

    public function availabilityWindows(): HasMany
    {
        return $this->hasMany(ServiceAvailabilityWindow::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function packages(): BelongsToMany
    {
        return $this->belongsToMany(ServicePackage::class, 'service_package_items')
            ->withPivot(['quantity', 'order', 'is_optional', 'notes'])
            ->withTimestamps();
    }

    public function serviceBookings(): HasMany
    {
        return $this->hasMany(ServiceBooking::class);
    }

    public function capacitySlots(): HasMany
    {
        return $this->hasMany(BookingCapacitySlot::class);
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

    public function scopeByCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    public function scopeRequiresConsultation(Builder $query): Builder
    {
        return $query->where('requires_consultation', true);
    }

    public function scopeRequiresDeposit(Builder $query): Builder
    {
        return $query->where('requires_deposit', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function scopeWithin(Builder $query, float $latitude, float $longitude, float $radiusKm): Builder
    {
        return $query->whereHas('locations', function ($locationQuery) use ($latitude, $longitude, $radiusKm) {
            $locationQuery->selectRaw('*, ( 6371 * acos( cos( radians(?) ) * cos( radians( latitude ) ) * cos( radians( longitude ) - radians(?) ) + sin( radians(?) ) * sin( radians( latitude ) ) ) ) AS distance', [$latitude, $longitude, $latitude])
                ->havingRaw('distance < ?', [$radiusKm]);
        });
    }

    // Accessors & Mutators
    public function getFormattedPriceAttribute(): string
    {
        return '£' . number_format($this->base_price / 100, 2);
    }

    public function getFormattedDurationAttribute(): string
    {
        $hours = floor($this->duration_minutes / 60);
        $minutes = $this->duration_minutes % 60;

        if ($hours > 0 && $minutes > 0) {
            return "{$hours}h {$minutes}m";
        } elseif ($hours > 0) {
            return "{$hours}h";
        } else {
            return "{$minutes}m";
        }
    }

    public function getDepositAmountAttribute(): int
    {
        if (!$this->requires_deposit) {
            return 0;
        }

        if ($this->attributes['deposit_amount'] !== null) {
            return $this->deposit_amount;
        }

        if ($this->deposit_percentage) {
            return (int) round($this->base_price * ($this->deposit_percentage / 100));
        }

        // Default 25% deposit
        return (int) round($this->base_price * 0.25);
    }

    public function getFormattedDepositAmountAttribute(): string
    {
        return '£' . number_format($this->getDepositAmountAttribute() / 100, 2);
    }

    public function getCategoryDisplayNameAttribute(): string
    {
        return match($this->category) {
            'balloon_arch' => 'Balloon Arch',
            'balloon_backdrop' => 'Balloon Backdrop',
            'centerpiece' => 'Centerpiece',
            'bouquet' => 'Bouquet',
            'installation' => 'Installation',
            'consultation' => 'Consultation',
            'delivery' => 'Delivery',
            'setup' => 'Setup & Styling',
            default => ucfirst(str_replace('_', ' ', $this->category))
        };
    }

    public function getCategoryIconAttribute(): string
    {
        return match($this->category) {
            'balloon_arch' => 'rainbow',
            'balloon_backdrop' => 'image',
            'centerpiece' => 'flower',
            'bouquet' => 'flowers',
            'installation' => 'settings',
            'consultation' => 'message-circle',
            'delivery' => 'truck',
            'setup' => 'wrench',
            default => 'star'
        };
    }

    // Helper Methods
    public function isAvailableForBooking(): bool
    {
        return $this->is_active && $this->is_bookable;
    }

    public function hasActiveLocations(): bool
    {
        return $this->locations()->active()->exists();
    }

    public function hasAvailabilityWindows(): bool
    {
        return $this->availabilityWindows()->active()->exists();
    }

    public function requiresAdvanceBooking(): bool
    {
        return $this->min_advance_booking_hours > 0;
    }

    public function hasBookingLimit(): bool
    {
        return $this->max_advance_booking_days > 0;
    }

    public function canBookAt(Carbon $dateTime): bool
    {
        $now = now();

        // Check minimum advance booking requirement
        if ($this->min_advance_booking_hours) {
            $minDateTime = $now->clone()->addHours($this->min_advance_booking_hours);
            if ($dateTime < $minDateTime) {
                return false;
            }
        }

        // Check maximum advance booking limit
        if ($this->max_advance_booking_days) {
            $maxDateTime = $now->clone()->addDays($this->max_advance_booking_days);
            if ($dateTime > $maxDateTime) {
                return false;
            }
        }

        return true;
    }

    public function getAvailableSlots(Carbon $date, ?ServiceLocation $location = null): array
    {
        // This would integrate with TimeSlotService
        return app(\App\Services\V1\Bookings\TimeSlotService::class)
            ->getAvailableSlots($this, $date, $date->clone()->endOfDay(), $location)
            ->toArray();
    }

    public function getTotalBookingsCount(): int
    {
        return $this->bookings()->count();
    }

    public function getCompletedBookingsCount(): int
    {
        return $this->bookings()->where('status', BookingStatuses::COMPLETED)->count();
    }

    public function getTotalRevenue(): int
    {
        return $this->bookings()
            ->where('status', BookingStatuses::COMPLETED)
            ->sum('total_amount');
    }

    public function getFormattedTotalRevenueAttribute(): string
    {
        return '£' . number_format($this->getTotalRevenue() / 100, 2);
    }

    public function getAverageRating(): float
    {
        // Placeholder for review system if needed
        return 0.0;
    }

    public function hasActiveBookings(): bool
    {
        return $this->bookings()
            ->whereIn('status', [BookingStatuses::PENDING, BookingStatuses::CONFIRMED, BookingStatuses::IN_PROGRESS])
            ->exists();
    }

    public function hasFutureBookings(): bool
    {
        return $this->bookings()
            ->whereIn('status', [BookingStatuses::PENDING, BookingStatuses::CONFIRMED])
            ->where('scheduled_at', '>', now())
            ->exists();
    }

    public function getNextAvailableDate(): ?Carbon
    {
        $windows = $this->availabilityWindows()
            ->active()
            ->bookable()
            ->get();

        if ($windows->isEmpty()) {
            return null;
        }

        $now = now();
        $checkDate = $now->clone();

        // Look up to 30 days ahead
        for ($i = 0; $i < 30; $i++) {
            foreach ($windows as $window) {
                if ($window->isAvailableOn($checkDate)) {
                    return $checkDate;
                }
            }
            $checkDate->addDay();
        }

        return null;
    }

    public function canAddMoreLocations(): bool
    {
        // Business rule: max 10 locations per service
        return $this->locations()->count() < 10;
    }

    public function canAddMoreAddOns(): bool
    {
        // Business rule: max 20 add-ons per service
        return $this->addOns()->count() < 20;
    }

    public function getEstimatedSetupTime(): int
    {
        return $this->duration_minutes + ($this->buffer_minutes ?? 0);
    }

    public function isMobileService(): bool
    {
        return $this->locations()
            ->where('type', 'client_location')
            ->exists();
    }

    public function isVirtualService(): bool
    {
        return $this->locations()
            ->where('type', 'virtual')
            ->exists();
    }

    public function supportsVenues(): bool
    {
        return $this->locations()
            ->whereIn('type', ['business_premises', 'outdoor'])
            ->exists();
    }

    // Media Collections
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('images')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->singleFile();

        $this->addMediaCollection('gallery')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);

        $this->addMediaCollection('documents')
            ->acceptsMimeTypes(['application/pdf']);
    }
}
