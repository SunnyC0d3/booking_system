<?php

namespace App\Models;

use App\Filters\V1\QueryFilter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class Booking extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'service_id',
        'service_location_id',
        'booking_reference',
        'scheduled_at',
        'ends_at',
        'duration_minutes',
        'base_price',
        'addons_total',
        'total_amount',
        'deposit_amount',
        'remaining_amount',
        'status',
        'payment_status',
        'client_name',
        'client_email',
        'client_phone',
        'notes',
        'special_requirements',
        'requires_consultation',
        'consultation_completed_at',
        'consultation_notes',
        'cancelled_at',
        'cancellation_reason',
        'rescheduled_from_booking_id',
        'metadata',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'ends_at' => 'datetime',
        'consultation_completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'duration_minutes' => 'integer',
        'base_price' => 'integer',
        'addons_total' => 'integer',
        'total_amount' => 'integer',
        'deposit_amount' => 'integer',
        'remaining_amount' => 'integer',
        'requires_consultation' => 'boolean',
        'metadata' => 'array',
    ];

    protected static function booted()
    {
        static::creating(function ($booking) {
            if (empty($booking->booking_reference)) {
                $booking->booking_reference = self::generateBookingReference();
            }
        });
    }

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function serviceLocation(): BelongsTo
    {
        return $this->belongsTo(ServiceLocation::class);
    }

    public function bookingAddOns(): HasMany
    {
        return $this->hasMany(BookingAddOn::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function rescheduledFromBooking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'rescheduled_from_booking_id');
    }

    public function rescheduledBookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'rescheduled_from_booking_id');
    }

    // Scopes
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('scheduled_at', '>', now());
    }

    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('scheduled_at', today());
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', ['pending', 'confirmed', 'in_progress']);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    public function scopeCancelled(Builder $query): Builder
    {
        return $query->where('status', 'cancelled');
    }

    public function scopeFilter(Builder $builder, QueryFilter $filters)
    {
        return $filters->apply($builder);
    }

    public function scopeRequiresConsultation(Builder $query): Builder
    {
        return $query->where('requires_consultation', true)
            ->whereNull('consultation_completed_at');
    }

    // Helper methods
    public static function generateBookingReference(): string
    {
        do {
            $reference = 'BK-' . strtoupper(Str::random(8));
        } while (static::where('booking_reference', $reference)->exists());

        return $reference;
    }

    public function getFormattedTotalAmountAttribute(): string
    {
        return '£' . number_format($this->total_amount / 100, 2);
    }

    public function getFormattedDepositAmountAttribute(): ?string
    {
        return $this->deposit_amount ? '£' . number_format($this->deposit_amount / 100, 2) : null;
    }

    public function getFormattedRemainingAmountAttribute(): ?string
    {
        return $this->remaining_amount ? '£' . number_format($this->remaining_amount / 100, 2) : null;
    }

    public function getFormattedBasePriceAttribute(): string
    {
        return '£' . number_format($this->base_price / 100, 2);
    }

    public function getFormattedAddOnsTotalAttribute(): string
    {
        return '£' . number_format($this->addons_total / 100, 2);
    }

    public function isUpcoming(): bool
    {
        return $this->scheduled_at > now();
    }

    public function isPast(): bool
    {
        return $this->scheduled_at < now();
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, ['pending', 'confirmed']) &&
            $this->scheduled_at > now()->addHours(24); // 24h cancellation policy
    }

    public function canBeRescheduled(): bool
    {
        return in_array($this->status, ['pending', 'confirmed']) &&
            $this->scheduled_at > now()->addHours(24);
    }

    public function isConsultationRequired(): bool
    {
        return $this->requires_consultation && !$this->consultation_completed_at;
    }

    public function isConsultationCompleted(): bool
    {
        return $this->requires_consultation && $this->consultation_completed_at;
    }

    public function needsDeposit(): bool
    {
        return $this->deposit_amount > 0 && $this->payment_status === 'pending';
    }

    public function isDepositPaid(): bool
    {
        return in_array($this->payment_status, ['deposit_paid', 'fully_paid']);
    }

    public function isFullyPaid(): bool
    {
        return $this->payment_status === 'fully_paid';
    }

    public function getDurationInHours(): float
    {
        return $this->duration_minutes / 60;
    }

    public function getFormattedDuration(): string
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

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'pending' => 'yellow',
            'confirmed' => 'blue',
            'in_progress' => 'green',
            'completed' => 'green',
            'cancelled' => 'red',
            'no_show' => 'red',
            'rescheduled' => 'orange',
            default => 'gray'
        };
    }

    public function getPaymentStatusColorAttribute(): string
    {
        return match($this->payment_status) {
            'pending' => 'red',
            'deposit_paid' => 'yellow',
            'fully_paid' => 'green',
            'refunded' => 'gray',
            'partially_refunded' => 'orange',
            default => 'gray'
        };
    }
}
