<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ShippingRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'shipping_method_id',
        'shipping_zone_id',
        'min_weight',
        'max_weight',
        'min_total',
        'max_total',
        'rate',
        'free_threshold',
        'is_active',
    ];

    protected $casts = [
        'min_weight' => 'decimal:2',
        'max_weight' => 'decimal:2',
        'min_total' => 'integer',
        'max_total' => 'integer',
        'rate' => 'integer',
        'free_threshold' => 'integer',
        'is_active' => 'boolean',
    ];

    public function shippingMethod(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class);
    }

    public function shippingZone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForWeight($query, float $weightInKg)
    {
        return $query->where('min_weight', '<=', $weightInKg)
            ->where(function ($q) use ($weightInKg) {
                $q->where('max_weight', '>=', $weightInKg)
                    ->orWhereNull('max_weight');
            });
    }

    public function scopeForTotal($query, int $totalInPennies)
    {
        return $query->where('min_total', '<=', $totalInPennies)
            ->where(function ($q) use ($totalInPennies) {
                $q->where('max_total', '>=', $totalInPennies)
                    ->orWhereNull('max_total');
            });
    }

    public function scopeForMethodAndZone($query, int $methodId, int $zoneId)
    {
        return $query->where('shipping_method_id', $methodId)
            ->where('shipping_zone_id', $zoneId);
    }

    public function getRateInPennies(): int
    {
        return (int) $this->rate;
    }

    public function getRateInPounds(): float
    {
        return $this->rate / 100;
    }

    public function getRateFormatted(): string
    {
        return '£' . number_format($this->rate / 100, 2);
    }

    public function getFreeThresholdInPennies(): ?int
    {
        return $this->free_threshold ? (int) $this->free_threshold : null;
    }

    public function getFreeThresholdInPounds(): ?float
    {
        return $this->free_threshold ? $this->free_threshold / 100 : null;
    }

    public function getFreeThresholdFormatted(): ?string
    {
        return $this->free_threshold ? '£' . number_format($this->free_threshold / 100, 2) : null;
    }

    public function isFreeShipping(int $orderTotalInPennies): bool
    {
        return $this->free_threshold && $orderTotalInPennies >= $this->free_threshold;
    }

    public function calculateShippingCost(int $orderTotalInPennies): int
    {
        if ($this->isFreeShipping($orderTotalInPennies)) {
            return 0;
        }

        return $this->getRateInPennies();
    }

    public function appliesTo(float $weightInKg, int $totalInPennies): bool
    {
        $weightMatches = ($weightInKg >= $this->min_weight) &&
            ($this->max_weight === null || $weightInKg <= $this->max_weight);

        $totalMatches = ($totalInPennies >= $this->min_total) &&
            ($this->max_total === null || $totalInPennies <= $this->max_total);

        return $weightMatches && $totalMatches && $this->is_active;
    }

    public function getWeightRangeFormatted(): string
    {
        if ($this->max_weight === null) {
            return $this->min_weight . 'kg+';
        }

        if ($this->min_weight == $this->max_weight) {
            return $this->min_weight . 'kg';
        }

        return $this->min_weight . '-' . $this->max_weight . 'kg';
    }

    public function getTotalRangeFormatted(): string
    {
        $minFormatted = '£' . number_format($this->min_total / 100, 2);

        if ($this->max_total === null) {
            return $minFormatted . '+';
        }

        if ($this->min_total == $this->max_total) {
            return $minFormatted;
        }

        $maxFormatted = '£' . number_format($this->max_total / 100, 2);
        return $minFormatted . '-' . $maxFormatted;
    }
}
