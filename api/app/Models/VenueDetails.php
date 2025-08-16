<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VenueDetails extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_location_id',
        'venue_type',
        'setup_requirements',
        'equipment_available',
        'accessibility_info',
        'parking_info',
        'catering_options',
        'max_capacity',
        'setup_time_minutes',
        'breakdown_time_minutes',
        'additional_fee',
        'amenities',
        'restrictions',
        'contact_info',
        'operating_hours',
        'cancellation_policy',
        'special_instructions',
        'metadata',
    ];

    protected $casts = [
        'amenities' => 'array',
        'restrictions' => 'array',
        'contact_info' => 'array',
        'operating_hours' => 'array',
        'additional_fee' => 'decimal:2',
        'max_capacity' => 'integer',
        'setup_time_minutes' => 'integer',
        'breakdown_time_minutes' => 'integer',
        'metadata' => 'array',
    ];

    protected $appends = [
        'formatted_additional_fee',
        'total_setup_time',
        'has_amenities',
        'has_restrictions',
    ];

    // Relationships
    public function serviceLocation(): BelongsTo
    {
        return $this->belongsTo(ServiceLocation::class);
    }

    // Accessors
    public function getFormattedAdditionalFeeAttribute(): string
    {
        if ($this->additional_fee <= 0) {
            return 'No additional fee';
        }

        return '£' . number_format($this->additional_fee, 2);
    }

    public function getTotalSetupTimeAttribute(): int
    {
        return $this->setup_time_minutes + $this->breakdown_time_minutes;
    }

    public function getHasAmenitiesAttribute(): bool
    {
        return !empty($this->amenities);
    }

    public function getHasRestrictionsAttribute(): bool
    {
        return !empty($this->restrictions);
    }

    // Scopes
    public function scopeByVenueType($query, string $venueType)
    {
        return $query->where('venue_type', $venueType);
    }

    public function scopeWithCapacity($query, int $minimumCapacity)
    {
        return $query->where('max_capacity', '>=', $minimumCapacity);
    }

    public function scopeWithSetupTime($query, int $maxSetupMinutes)
    {
        return $query->where('setup_time_minutes', '<=', $maxSetupMinutes);
    }

    // Helper methods
    public function isAccessible(): bool
    {
        return !empty($this->accessibility_info) &&
            str_contains(strtolower($this->accessibility_info), 'accessible');
    }

    public function hasParking(): bool
    {
        return !empty($this->parking_info) &&
            !str_contains(strtolower($this->parking_info), 'no parking');
    }

    public function canAccommodateGuests(int $guestCount): bool
    {
        return $this->max_capacity >= $guestCount;
    }

    public function hasEquipment(string $equipment = null): bool
    {
        if (!$equipment) {
            return !empty($this->equipment_available);
        }

        return str_contains(strtolower($this->equipment_available), strtolower($equipment));
    }

    public function hasAmenity(string $amenity): bool
    {
        if (empty($this->amenities)) {
            return false;
        }

        return collect($this->amenities)->contains(function ($item) use ($amenity) {
            return str_contains(strtolower($item), strtolower($amenity));
        });
    }

    public function hasRestriction(string $restriction): bool
    {
        if (empty($this->restrictions)) {
            return false;
        }

        return collect($this->restrictions)->contains(function ($item) use ($restriction) {
            return str_contains(strtolower($item), strtolower($restriction));
        });
    }

    public function getVenueTypeDisplayName(): string
    {
        return match ($this->venue_type) {
            'studio' => 'Photography Studio',
            'hall' => 'Event Hall',
            'garden' => 'Garden Venue',
            'ballroom' => 'Ballroom',
            'restaurant' => 'Restaurant',
            'hotel' => 'Hotel',
            'church' => 'Church',
            'outdoor' => 'Outdoor Venue',
            'home' => 'Private Residence',
            'client_location' => 'Client Location',
            'office' => 'Office Space',
            'warehouse' => 'Warehouse',
            'general' => 'General Venue',
            default => ucfirst($this->venue_type),
        };
    }

    public function getEstimatedTotalServiceTime(int $serviceMinutes): int
    {
        return $this->setup_time_minutes + $serviceMinutes + $this->breakdown_time_minutes;
    }

    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'venue_type' => $this->venue_type,
            'venue_type_display_name' => $this->getVenueTypeDisplayName(),
            'setup_requirements' => $this->setup_requirements,
            'equipment_available' => $this->equipment_available,
            'accessibility_info' => $this->accessibility_info,
            'parking_info' => $this->parking_info,
            'catering_options' => $this->catering_options,
            'max_capacity' => $this->max_capacity,
            'setup_time_minutes' => $this->setup_time_minutes,
            'breakdown_time_minutes' => $this->breakdown_time_minutes,
            'total_setup_time' => $this->total_setup_time,
            'additional_fee' => $this->additional_fee,
            'formatted_additional_fee' => $this->formatted_additional_fee,
            'amenities' => $this->amenities,
            'restrictions' => $this->restrictions,
            'contact_info' => $this->contact_info,
            'operating_hours' => $this->operating_hours,
            'has_amenities' => $this->has_amenities,
            'has_restrictions' => $this->has_restrictions,
            'is_accessible' => $this->isAccessible(),
            'has_parking' => $this->hasParking(),
        ];
    }
}
