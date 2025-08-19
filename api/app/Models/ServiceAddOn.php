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
        'metadata', // Added missing metadata field
    ];

    protected $casts = [
        'price' => 'integer',
        'duration_minutes' => 'integer',
        'is_active' => 'boolean',
        'is_required' => 'boolean',
        'max_quantity' => 'integer',
        'sort_order' => 'integer',
        'metadata' => 'array', // Added metadata array casting
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

    // Metadata helper methods
    public function hasMetadata(string $key = null): bool
    {
        if ($key === null) {
            return !empty($this->metadata);
        }

        return isset($this->metadata[$key]);
    }

    public function getMetadata(string $key, $default = null)
    {
        return $this->metadata[$key] ?? $default;
    }

    public function setMetadata(string $key, $value): void
    {
        $metadata = $this->metadata ?? [];
        $metadata[$key] = $value;
        $this->metadata = $metadata;
    }

    public function removeMetadata(string $key): void
    {
        if ($this->hasMetadata($key)) {
            $metadata = $this->metadata;
            unset($metadata[$key]);
            $this->metadata = $metadata;
        }
    }

    // Category-specific helper methods
    public function isEquipmentCategory(): bool
    {
        return $this->category === 'equipment';
    }

    public function isServiceEnhancement(): bool
    {
        return $this->category === 'service_enhancement';
    }

    public function isLocationCategory(): bool
    {
        return $this->category === 'location';
    }

    // Pricing helper methods
    public function getTotalPrice(int $quantity): int
    {
        return $this->price * $quantity;
    }

    public function getFormattedTotalPrice(int $quantity): string
    {
        return '£' . number_format($this->getTotalPrice($quantity) / 100, 2);
    }

    public function getTotalDuration(int $quantity): int
    {
        return $this->duration_minutes * $quantity;
    }

    public function getFormattedTotalDuration(int $quantity): string
    {
        $totalMinutes = $this->getTotalDuration($quantity);

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

    // Availability helper methods
    public function canBeAddedToBooking(int $requestedQuantity): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->max_quantity && $requestedQuantity > $this->max_quantity) {
            return false;
        }

        return true;
    }

    public function getAvailableQuantity(): int
    {
        return $this->max_quantity ?? 999; // Return max_quantity or large number if unlimited
    }

    // API response helper
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price,
            'formatted_price' => $this->formatted_price,
            'duration_minutes' => $this->duration_minutes,
            'formatted_duration' => $this->formatted_duration,
            'is_active' => $this->is_active,
            'is_required' => $this->is_required,
            'max_quantity' => $this->max_quantity,
            'available_quantity' => $this->getAvailableQuantity(),
            'sort_order' => $this->sort_order,
            'category' => $this->category,
            'metadata' => $this->metadata,
            'service_id' => $this->service_id,
        ];
    }
}
