<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ShippingMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'carrier',
        'service_code',
        'estimated_days_min',
        'estimated_days_max',
        'is_active',
        'sort_order',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'estimated_days_min' => 'integer',
        'estimated_days_max' => 'integer',
        'sort_order' => 'integer',
        'metadata' => 'array',
    ];

    public function zones(): BelongsToMany
    {
        return $this->belongsToMany(ShippingZone::class, 'shipping_zones_methods')
            ->withPivot('is_active', 'sort_order')
            ->withTimestamps();
    }

    public function rates(): HasMany
    {
        return $this->hasMany(ShippingRate::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByCarrier($query, string $carrier)
    {
        return $query->where('carrier', $carrier);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function getEstimatedDeliveryAttribute(): string
    {
        if ($this->estimated_days_min === $this->estimated_days_max) {
            return "{$this->estimated_days_min} day" . ($this->estimated_days_min > 1 ? 's' : '');
        }

        return "{$this->estimated_days_min}-{$this->estimated_days_max} days";
    }

    public function isAvailableForZone(ShippingZone $zone): bool
    {
        return $this->zones()
            ->where('shipping_zone_id', $zone->id)
            ->wherePivot('is_active', true)
            ->exists();
    }

    public function getRateForZone(ShippingZone $zone, int $weightInGrams = 0, int $totalInPennies = 0): ?ShippingRate
    {
        return $this->rates()
            ->where('shipping_zone_id', $zone->id)
            ->where('is_active', true)
            ->where(function ($query) use ($weightInGrams) {
                $weightInKg = $weightInGrams / 1000;
                $query->where('min_weight', '<=', $weightInKg)
                    ->where(function ($q) use ($weightInKg) {
                        $q->where('max_weight', '>=', $weightInKg)
                            ->orWhereNull('max_weight');
                    });
            })
            ->where(function ($query) use ($totalInPennies) {
                $query->where('min_total', '<=', $totalInPennies)
                    ->where(function ($q) use ($totalInPennies) {
                        $q->where('max_total', '>=', $totalInPennies)
                            ->orWhereNull('max_total');
                    });
            })
            ->first();
    }
}
