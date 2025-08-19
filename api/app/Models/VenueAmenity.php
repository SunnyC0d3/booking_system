<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class VenueAmenity extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_location_id',
        'amenity_type',
        'name',
        'description',
        'included_in_booking',
        'additional_cost',
        'quantity_available',
        'requires_advance_notice',
        'notice_hours_required',
        'specifications',
        'usage_instructions',
        'restrictions',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'included_in_booking' => 'boolean',
        'additional_cost' => 'integer',
        'quantity_available' => 'integer',
        'requires_advance_notice' => 'boolean',
        'notice_hours_required' => 'integer',
        'specifications' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function serviceLocation(): BelongsTo
    {
        return $this->belongsTo(ServiceLocation::class);
    }
}
