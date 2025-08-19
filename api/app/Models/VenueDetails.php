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
        'space_style',
        'ceiling_height_meters',
        'floor_area_sqm',
        'room_dimensions',
        'color_scheme',
        'access_instructions',
        'parking_information',
        'loading_instructions',
        'lift_access',
        'step_free_access',
        'stairs_count',
        'power_outlets',
        'has_adequate_lighting',
        'lighting_notes',
        'climate_controlled',
        'typical_temperature',
        'setup_restrictions',
        'setup_time_minutes',
        'breakdown_time_minutes',
        'noise_restrictions',
        'prohibited_items',
        'venue_contacts',
        'special_instructions',
        'photography_allowed',
        'photography_restrictions',
        'social_media_allowed',

        // Legacy fields (for backward compatibility)
        'setup_requirements',
        'equipment_available',
        'accessibility_info',
        'parking_info',
        'catering_options',
        'max_capacity',
        'additional_fee',
        'amenities',
        'restrictions',
        'contact_info',
        'operating_hours',
        'cancellation_policy',
        'metadata',
    ];

    protected $casts = [
        // New venue details migration fields
        'ceiling_height_meters' => 'decimal:2',
        'floor_area_sqm' => 'decimal:2',
        'room_dimensions' => 'array',
        'color_scheme' => 'array',
        'lift_access' => 'boolean',
        'step_free_access' => 'boolean',
        'stairs_count' => 'integer',
        'power_outlets' => 'array',
        'has_adequate_lighting' => 'boolean',
        'climate_controlled' => 'boolean',
        'typical_temperature' => 'decimal:1',
        'setup_restrictions' => 'array',
        'setup_time_minutes' => 'integer',
        'breakdown_time_minutes' => 'integer',
        'prohibited_items' => 'array',
        'venue_contacts' => 'array',
        'photography_allowed' => 'boolean',
        'social_media_allowed' => 'boolean',

        // Legacy fields casts
        'amenities' => 'array',
        'restrictions' => 'array',
        'contact_info' => 'array',
        'operating_hours' => 'array',
        'additional_fee' => 'decimal:2',
        'max_capacity' => 'integer',
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
        if (($this->additional_fee ?? 0) <= 0) {
            return 'No additional fee';
        }

        return '£' . number_format($this->additional_fee, 2);
    }

    public function getTotalSetupTimeAttribute(): int
    {
        return ($this->setup_time_minutes ?? 0) + ($this->breakdown_time_minutes ?? 0);
    }

    public function getHasAmenitiesAttribute(): bool
    {
        return !empty($this->amenities);
    }

    public function getHasRestrictionsAttribute(): bool
    {
        return !empty($this->restrictions) || !empty($this->setup_restrictions) || !empty($this->prohibited_items);
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

    public function scopeAccessible($query)
    {
        return $query->where('step_free_access', true);
    }

    public function scopeWithParking($query)
    {
        return $query->whereNotNull('parking_information');
    }

    // Helper methods
    public function isAccessible(): bool
    {
        return $this->step_free_access ||
            (!empty($this->accessibility_info) && str_contains(strtolower($this->accessibility_info), 'accessible'));
    }

    public function hasParking(): bool
    {
        return !empty($this->parking_information) ||
            (!empty($this->parking_info) && !str_contains(strtolower($this->parking_info), 'no parking'));
    }

    public function canAccommodateGuests(int $guestCount): bool
    {
        return ($this->max_capacity ?? 0) >= $guestCount;
    }

    public function hasEquipment(string $equipment = null): bool
    {
        if (!$equipment) {
            return !empty($this->equipment_available);
        }

        return str_contains(strtolower($this->equipment_available ?? ''), strtolower($equipment));
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
        $allRestrictions = array_merge(
            $this->restrictions ?? [],
            $this->setup_restrictions ?? [],
            $this->prohibited_items ?? []
        );

        if (empty($allRestrictions)) {
            return false;
        }

        return collect($allRestrictions)->contains(function ($item) use ($restriction) {
            return str_contains(strtolower($item), strtolower($restriction));
        });
    }

    public function getVenueTypeDisplayName(): string
    {
        return match ($this->venue_type) {
            'studio' => 'Design Studio',
            'indoor' => 'Indoor Venue',
            'outdoor' => 'Outdoor Venue',
            'mixed' => 'Mixed Indoor/Outdoor',
            'client_home' => 'Client Home',
            'corporate' => 'Corporate Venue',
            'public_space' => 'Public Space',
            default => ucfirst(str_replace('_', ' ', $this->venue_type ?? 'venue')),
        };
    }

    public function getSpaceStyleDisplayName(): string
    {
        return match ($this->space_style) {
            'modern' => 'Modern',
            'traditional' => 'Traditional',
            'rustic' => 'Rustic',
            'industrial' => 'Industrial',
            'garden' => 'Garden',
            'ballroom' => 'Ballroom',
            'casual' => 'Casual',
            'formal' => 'Formal',
            default => ucfirst($this->space_style ?? 'general'),
        };
    }

    public function getEstimatedTotalServiceTime(int $serviceMinutes): int
    {
        return ($this->setup_time_minutes ?? 0) + $serviceMinutes + ($this->breakdown_time_minutes ?? 0);
    }

    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'venue_type' => $this->venue_type,
            'venue_type_display_name' => $this->getVenueTypeDisplayName(),
            'space_style' => $this->space_style,
            'space_style_display_name' => $this->getSpaceStyleDisplayName(),
            'ceiling_height_meters' => $this->ceiling_height_meters,
            'floor_area_sqm' => $this->floor_area_sqm,
            'room_dimensions' => $this->room_dimensions,
            'color_scheme' => $this->color_scheme,
            'access_instructions' => $this->access_instructions,
            'parking_information' => $this->parking_information,
            'loading_instructions' => $this->loading_instructions,
            'lift_access' => $this->lift_access,
            'step_free_access' => $this->step_free_access,
            'stairs_count' => $this->stairs_count,
            'power_outlets' => $this->power_outlets,
            'has_adequate_lighting' => $this->has_adequate_lighting,
            'lighting_notes' => $this->lighting_notes,
            'climate_controlled' => $this->climate_controlled,
            'typical_temperature' => $this->typical_temperature,
            'setup_restrictions' => $this->setup_restrictions,
            'setup_time_minutes' => $this->setup_time_minutes,
            'breakdown_time_minutes' => $this->breakdown_time_minutes,
            'total_setup_time' => $this->total_setup_time,
            'noise_restrictions' => $this->noise_restrictions,
            'prohibited_items' => $this->prohibited_items,
            'venue_contacts' => $this->venue_contacts,
            'special_instructions' => $this->special_instructions,
            'photography_allowed' => $this->photography_allowed,
            'photography_restrictions' => $this->photography_restrictions,
            'social_media_allowed' => $this->social_media_allowed,
            'max_capacity' => $this->max_capacity,
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
