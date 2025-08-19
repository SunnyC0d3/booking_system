<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ClientVenueRequirement extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'service_location_id',
        'expected_guest_count',
        'age_groups',
        'accessibility_needs',
        'theme_requirements',
        'color_preferences',
        'special_requests',
        'equipment_needed',
        'earliest_setup_time',
        'event_start_time',
        'event_end_time',
        'latest_breakdown_time',
        'dietary_restrictions',
        'noise_considerations',
        'prohibited_elements',
        'other_vendors',
        'coordination_notes',
    ];

    protected $casts = [
        'expected_guest_count' => 'integer',
        'age_groups' => 'array',
        'accessibility_needs' => 'array',
        'color_preferences' => 'array',
        'equipment_needed' => 'array',
        'earliest_setup_time' => 'datetime',
        'event_start_time' => 'datetime',
        'event_end_time' => 'datetime',
        'latest_breakdown_time' => 'datetime',
        'dietary_restrictions' => 'array',
        'prohibited_elements' => 'array',
        'other_vendors' => 'array',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function serviceLocation(): BelongsTo
    {
        return $this->belongsTo(ServiceLocation::class);
    }
}
