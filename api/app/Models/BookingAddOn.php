<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BookingAddOn extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'service_add_on_id',
        'quantity',
        'unit_price',
        'total_price',
        'duration_minutes',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'integer',
        'total_price' => 'integer',
        'duration_minutes' => 'integer',
    ];

    // Relationships
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function serviceAddOn(): BelongsTo
    {
        return $this->belongsTo(ServiceAddOn::class);
    }

    // Helper methods
    public function getFormattedUnitPriceAttribute(): string
    {
        return '£' . number_format($this->unit_price / 100, 2);
    }

    public function getFormattedTotalPriceAttribute(): string
    {
        return '£' . number_format($this->total_price / 100, 2);
    }

    public function getFormattedDurationAttribute(): string
    {
        if ($this->duration_minutes <= 0) {
            return 'No additional time';
        }

        $hours = floor($this->duration_minutes / 60);
        $minutes = $this->duration_minutes % 60;

        if ($hours > 0 && $minutes > 0) {
            return "+{$hours}h {$minutes}m";
        } elseif ($hours > 0) {
            return "+{$hours}h";
        } else {
            return "+{$minutes}m";
        }
    }

    public function getTotalDurationMinutes(): int
    {
        return $this->duration_minutes * $this->quantity;
    }

    public function getFormattedTotalDurationAttribute(): string
    {
        $totalMinutes = $this->getTotalDurationMinutes();

        if ($totalMinutes <= 0) {
            return 'No additional time';
        }

        $hours = floor($totalMinutes / 60);
        $minutes = $totalMinutes % 60;

        if ($hours > 0 && $minutes > 0) {
            return "+{$hours}h {$minutes}m";
        } elseif ($hours > 0) {
            return "+{$hours}h";
        } else {
            return "+{$minutes}m";
        }
    }
}
