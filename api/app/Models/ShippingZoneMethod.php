<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ShippingZoneMethod extends Model
{
    use HasFactory;

    protected $table = 'shipping_zones_methods';

    protected $fillable = [
        'shipping_zone_id',
        'shipping_method_id',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function shippingZone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class);
    }

    public function shippingMethod(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }

    public function scopeForZone($query, int $zoneId)
    {
        return $query->where('shipping_zone_id', $zoneId);
    }

    public function scopeForMethod($query, int $methodId)
    {
        return $query->where('shipping_method_id', $methodId);
    }

    public function scopeForZoneAndMethod($query, int $zoneId, int $methodId)
    {
        return $query->where('shipping_zone_id', $zoneId)
            ->where('shipping_method_id', $methodId);
    }

    public function activate(): bool
    {
        return $this->update(['is_active' => true]);
    }

    public function deactivate(): bool
    {
        return $this->update(['is_active' => false]);
    }

    public function isAvailable(): bool
    {
        return $this->is_active &&
            $this->shippingZone?->is_active &&
            $this->shippingMethod?->is_active;
    }

    public function updateSortOrder(int $sortOrder): bool
    {
        return $this->update(['sort_order' => $sortOrder]);
    }

    public static function createAssociation(int $zoneId, int $methodId, bool $isActive = true, int $sortOrder = 0): self
    {
        return static::create([
            'shipping_zone_id' => $zoneId,
            'shipping_method_id' => $methodId,
            'is_active' => $isActive,
            'sort_order' => $sortOrder,
        ]);
    }

    public static function removeAssociation(int $zoneId, int $methodId): bool
    {
        return static::forZoneAndMethod($zoneId, $methodId)->delete();
    }

    public static function toggleAssociation(int $zoneId, int $methodId): bool
    {
        $association = static::forZoneAndMethod($zoneId, $methodId)->first();

        if ($association) {
            return $association->update(['is_active' => !$association->is_active]);
        }

        static::createAssociation($zoneId, $methodId);
        return true;
    }

    public static function reorderForZone(int $zoneId, array $methodIds): void
    {
        foreach ($methodIds as $order => $methodId) {
            static::forZoneAndMethod($zoneId, $methodId)
                ->update(['sort_order' => $order]);
        }
    }

    public static function getAvailableMethodsForZone(int $zoneId)
    {
        return static::with(['shippingMethod'])
            ->forZone($zoneId)
            ->active()
            ->whereHas('shippingMethod', function ($query) {
                $query->where('is_active', true);
            })
            ->ordered()
            ->get()
            ->pluck('shippingMethod');
    }

    public static function getZonesForMethod(int $methodId)
    {
        return static::with(['shippingZone'])
            ->forMethod($methodId)
            ->active()
            ->whereHas('shippingZone', function ($query) {
                $query->where('is_active', true);
            })
            ->get()
            ->pluck('shippingZone');
    }
}
