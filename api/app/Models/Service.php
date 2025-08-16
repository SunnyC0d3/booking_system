<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Service extends Model implements HasMedia
{
    use HasFactory, SoftDeletes, InteractsWithMedia;

    protected $fillable = [
        'name',
        'description',
        'short_description',
        'base_price',
        'duration_minutes',
        'buffer_minutes',
        'max_advance_booking_days',
        'min_advance_booking_hours',
        'requires_deposit',
        'deposit_percentage',
        'deposit_amount',
        'status',
        'metadata',
    ];

    protected $casts = [
        'base_price' => 'integer',
        'duration_minutes' => 'integer',
        'buffer_minutes' => 'integer',
        'max_advance_booking_days' => 'integer',
        'min_advance_booking_hours' => 'integer',
        'requires_deposit' => 'boolean',
        'deposit_percentage' => 'decimal:2',
        'deposit_amount' => 'integer',
        'metadata' => 'array',
    ];

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function availabilityWindows(): HasMany
    {
        return $this->hasMany(ServiceAvailabilityWindow::class);
    }

    public function addOns(): HasMany
    {
        return $this->hasMany(ServiceAddOn::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(ServiceLocation::class);
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    // Helper methods
    public function getFormattedPriceAttribute(): string
    {
        return '£' . number_format($this->base_price / 100, 2);
    }

    public function getDepositAmountAttribute(): ?int
    {
        if (!$this->requires_deposit) {
            return null;
        }

        if ($this->attributes['deposit_amount']) {
            return $this->attributes['deposit_amount'];
        }

        if ($this->deposit_percentage) {
            return (int) round($this->base_price * ($this->deposit_percentage / 100));
        }

        return null;
    }

    public function getFormattedDepositAttribute(): ?string
    {
        $depositAmount = $this->getDepositAmountAttribute();
        return $depositAmount ? '£' . number_format($depositAmount / 100, 2) : null;
    }

    public function getTotalDurationMinutesAttribute(): int
    {
        return $this->duration_minutes + $this->buffer_minutes;
    }

    public function isAvailableForBooking(): bool
    {
        return $this->status === 'active';
    }

    public function canUserBook(User $user): bool
    {
        return $this->isAvailableForBooking();
    }

    // Media collections
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('featured_image')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);

        $this->addMediaCollection('gallery')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }
}
