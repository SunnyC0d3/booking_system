<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class ServiceLocation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'service_id',
        'name',
        'description',
        'type',
        'address_line_1',
        'address_line_2',
        'city',
        'county',
        'postcode',
        'country',
        'latitude',
        'longitude',
        'max_capacity',
        'travel_time_minutes',
        'additional_charge',
        'is_active',
        'availability_notes',
        'virtual_platform',
        'virtual_instructions',
        'equipment_available',
        'facilities',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'max_capacity' => 'integer',
        'travel_time_minutes' => 'integer',
        'additional_charge' => 'integer',
        'is_active' => 'boolean',
        'availability_notes' => 'array',
        'equipment_available' => 'array',
        'facilities' => 'array',
    ];

    // Relationships
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function availabilityWindows(): HasMany
    {
        return $this->hasMany(ServiceAvailabilityWindow::class);
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeBusinessPremises(Builder $query): Builder
    {
        return $query->where('type', 'business_premises');
    }

    public function scopeClientLocation(Builder $query): Builder
    {
        return $query->where('type', 'client_location');
    }

    public function scopeVirtual(Builder $query): Builder
    {
        return $query->where('type', 'virtual');
    }

    public function scopeOutdoor(Builder $query): Builder
    {
        return $query->where('type', 'outdoor');
    }

    // Helper methods
    public function getFormattedAdditionalChargeAttribute(): string
    {
        return '£' . number_format($this->additional_charge / 100, 2);
    }

    public function getFullAddressAttribute(): string
    {
        $parts = array_filter([
            $this->address_line_1,
            $this->address_line_2,
            $this->city,
            $this->county,
            $this->postcode,
        ]);

        return implode(', ', $parts);
    }

    public function hasCoordinates(): bool
    {
        return !is_null($this->latitude) && !is_null($this->longitude);
    }

    public function isVirtual(): bool
    {
        return $this->type === 'virtual';
    }

    public function isClientLocation(): bool
    {
        return $this->type === 'client_location';
    }

    public function isBusinessPremises(): bool
    {
        return $this->type === 'business_premises';
    }

    public function isOutdoor(): bool
    {
        return $this->type === 'outdoor';
    }

    public function requiresTravel(): bool
    {
        return $this->travel_time_minutes > 0;
    }

    public function hasAdditionalCharge(): bool
    {
        return $this->additional_charge > 0;
    }

    public function hasEquipment(): bool
    {
        return !empty($this->equipment_available);
    }

    public function hasFacilities(): bool
    {
        return !empty($this->facilities);
    }

    public function getTypeDisplayNameAttribute(): string
    {
        return match($this->type) {
            'business_premises' => 'Business Premises',
            'client_location' => 'Client Location',
            'virtual' => 'Virtual/Online',
            'outdoor' => 'Outdoor Location',
            default => ucfirst($this->type)
        };
    }

    public function getTypeIconAttribute(): string
    {
        return match($this->type) {
            'business_premises' => 'building',
            'client_location' => 'home',
            'virtual' => 'video',
            'outdoor' => 'tree-pine',
            default => 'map-pin'
        };
    }

    public function getAvailableCapacityForDateTime(\DateTime $dateTime): int
    {
        $existingBookings = $this->bookings()
            ->where('scheduled_at', '<=', $dateTime)
            ->where('ends_at', '>', $dateTime)
            ->whereIn('status', ['confirmed', 'in_progress'])
            ->count();

        return max(0, $this->max_capacity - $existingBookings);
    }

    public function isAvailableForDateTime(\DateTime $dateTime): bool
    {
        return $this->getAvailableCapacityForDateTime($dateTime) > 0;
    }

    public function getGoogleMapsUrl(): ?string
    {
        if (!$this->hasCoordinates()) {
            return null;
        }

        return "https://www.google.com/maps?q={$this->latitude},{$this->longitude}";
    }

    public function getDistanceFrom(float $lat, float $lng): ?float
    {
        if (!$this->hasCoordinates()) {
            return null;
        }

        // Haversine formula for calculating distance
        $earthRadius = 6371; // Earth's radius in kilometers

        $latFrom = deg2rad($lat);
        $lngFrom = deg2rad($lng);
        $latTo = deg2rad($this->latitude);
        $lngTo = deg2rad($this->longitude);

        $latDelta = $latTo - $latFrom;
        $lngDelta = $lngTo - $lngFrom;

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
            cos($latFrom) * cos($latTo) *
            sin($lngDelta / 2) * sin($lngDelta / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
