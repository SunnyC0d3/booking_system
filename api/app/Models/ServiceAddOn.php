<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class ServiceAddOn extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'service_id',
        'name',
        'description',
        'price',
        'duration_minutes',
        'is_active',
        'is_required',
        'max_quantity',
        'sort_order',
        'category',
    ];

    protected $casts = [
        'price' => 'integer',
        'duration_minutes' => 'integer',
        'is_active' => 'boolean',
        'is_required' => 'boolean',
        'max_quantity' => 'integer',
        'sort_order' => 'integer',
    ];

    // Relationships
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function bookingAddOns(): HasMany
    {
        return $this->hasMany(BookingAddOn::class);
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeRequired(Builder $query): Builder
    {
        return $query->where('is_required', true);
    }

    public function scopeOptional(Builder $query): Builder
    {
        return $query->where('is_required', false);
    }

    public function scopeByCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    // Helper methods
    public function getFormattedPriceAttribute(): string
    {
        return '£' . number_format($this->price / 100, 2);
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

    public function getCategoryDisplayNameAttribute(): string
    {
        return match($this->category) {
            'equipment' => 'Equipment',
            'service_enhancement' => 'Service Enhancement',
            'location' => 'Location',
            'other' => 'Other',
            default => ucfirst($this->category)
        };
    }

    public function getCategoryIconAttribute(): string
    {
        return match($this->category) {
            'equipment' => 'wrench',
            'service_enhancement' => 'star',
            'location' => 'map-pin',
            'other' => 'plus',
            default => 'plus'
        };
    }

    public function isRequired(): bool
    {
        return $this->is_required;
    }

    public function isOptional(): bool
    {
        return !$this->is_required;
    }

    public function hasQuantityLimit(): bool
    {
        return $this->max_quantity > 1;
    }

    public function addsDuration(): bool
    {
        return $this->duration_minutes > 0;
    }

    public function calculateTotalPrice(int $quantity = 1): int
    {
        return $this->price * min($quantity, $this->max_quantity);
    }

    public function calculateTotalDuration(int $quantity = 1): int
    {
        return $this->duration_minutes * min($quantity, $this->max_quantity);
    }
}
