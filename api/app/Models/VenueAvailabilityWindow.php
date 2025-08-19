<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class VenueAvailabilityWindow extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_location_id',
        'window_type',
        'day_of_week',
        'specific_date',
        'date_range_start',
        'date_range_end',
        'earliest_access',
        'latest_departure',
        'quiet_hours_start',
        'quiet_hours_end',
        'max_concurrent_events',
        'restrictions',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
        'specific_date' => 'date',
        'date_range_start' => 'date',
        'date_range_end' => 'date',
        'earliest_access' => 'datetime:H:i',
        'latest_departure' => 'datetime:H:i',
        'quiet_hours_start' => 'datetime:H:i',
        'quiet_hours_end' => 'datetime:H:i',
        'max_concurrent_events' => 'integer',
        'restrictions' => 'array',
        'is_active' => 'boolean',
    ];

    public function serviceLocation(): BelongsTo
    {
        return $this->belongsTo(ServiceLocation::class);
    }
}
