<?php

namespace App\Models;

use App\Constants\SupplierStatuses;
use App\Constants\SupplierIntegrationTypes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'company_name',
        'email',
        'phone',
        'address',
        'country',
        'contact_person',
        'status',
        'integration_type',
        'commission_rate',
        'processing_time_days',
        'shipping_methods',
        'integration_config',
        'api_endpoint',
        'api_key',
        'webhook_url',
        'notes',
        'auto_fulfill',
        'stock_sync_enabled',
        'price_sync_enabled',
        'last_sync_at',
        'minimum_order_value',
        'maximum_order_value',
        'supported_countries',
    ];

    protected $casts = [
        'shipping_methods' => 'array',
        'integration_config' => 'array',
        'supported_countries' => 'array',
        'commission_rate' => 'decimal:2',
        'minimum_order_value' => 'decimal:2',
        'maximum_order_value' => 'decimal:2',
        'auto_fulfill' => 'boolean',
        'stock_sync_enabled' => 'boolean',
        'price_sync_enabled' => 'boolean',
        'last_sync_at' => 'datetime',
    ];

    public function supplierProducts(): HasMany
    {
        return $this->hasMany(SupplierProduct::class);
    }

    public function activeSupplierProducts(): HasMany
    {
        return $this->hasMany(SupplierProduct::class)->where('is_active', true);
    }

    public function mappedProducts(): HasManyThrough
    {
        return $this->hasManyThrough(Product::class, ProductSupplierMapping::class, 'supplier_id', 'id', 'id', 'product_id');
    }

    public function dropshipOrders(): HasMany
    {
        return $this->hasMany(DropshipOrder::class);
    }

    public function supplierIntegrations(): HasMany
    {
        return $this->hasMany(SupplierIntegration::class);
    }

    public function productMappings(): HasMany
    {
        return $this->hasMany(ProductSupplierMapping::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', SupplierStatuses::ACTIVE);
    }

    public function scopeWithIntegrationType($query, string $type)
    {
        return $query->where('integration_type', $type);
    }

    public function scopeAutoFulfill($query)
    {
        return $query->where('auto_fulfill', true);
    }

    public function scopeWithStockSync($query)
    {
        return $query->where('stock_sync_enabled', true);
    }

    public function scopeWithPriceSync($query)
    {
        return $query->where('price_sync_enabled', true);
    }

    public function isActive(): bool
    {
        return $this->status === SupplierStatuses::ACTIVE;
    }

    public function canAutoFulfill(): bool
    {
        return $this->auto_fulfill && $this->isActive();
    }

    public function hasApiIntegration(): bool
    {
        return $this->integration_type === SupplierIntegrationTypes::API;
    }

    public function hasWebhookIntegration(): bool
    {
        return $this->integration_type === SupplierIntegrationTypes::WEBHOOK;
    }

    public function canSyncStock(): bool
    {
        return $this->stock_sync_enabled && $this->isActive();
    }

    public function canSyncPrices(): bool
    {
        return $this->price_sync_enabled && $this->isActive();
    }

    public function getCommissionRateFormatted(): string
    {
        return number_format($this->commission_rate, 2) . '%';
    }

    public function getMinimumOrderValueFormatted(): string
    {
        return '£' . number_format($this->minimum_order_value, 2);
    }

    public function getMaximumOrderValueFormatted(): string
    {
        return $this->maximum_order_value ? '£' . number_format($this->maximum_order_value, 2) : 'No limit';
    }

    public function getProcessingTimeFormatted(): string
    {
        return $this->processing_time_days . ' day' . ($this->processing_time_days !== 1 ? 's' : '');
    }

    public function supportsCountry(string $countryCode): bool
    {
        if (!$this->supported_countries) {
            return true;
        }

        return in_array($countryCode, $this->supported_countries);
    }

    public function canFulfillOrder(int $orderValue): bool
    {
        if ($orderValue < ($this->minimum_order_value * 100)) {
            return false;
        }

        if ($this->maximum_order_value && $orderValue > ($this->maximum_order_value * 100)) {
            return false;
        }

        return true;
    }

    public function getActiveIntegration(): ?SupplierIntegration
    {
        return $this->supplierIntegrations()->where('is_active', true)->first();
    }

    public function getHealthStats(): array
    {
        $totalProducts = $this->supplierProducts()->count();
        $syncedProducts = $this->supplierProducts()->where('sync_status', 'synced')->count();
        $outOfStockProducts = $this->supplierProducts()->where('stock_quantity', 0)->count();
        $pendingOrders = $this->dropshipOrders()->whereIn('status', ['pending', 'sent_to_supplier'])->count();

        return [
            'total_products' => $totalProducts,
            'synced_products' => $syncedProducts,
            'sync_rate' => $totalProducts > 0 ? round(($syncedProducts / $totalProducts) * 100, 2) : 0,
            'out_of_stock_products' => $outOfStockProducts,
            'pending_orders' => $pendingOrders,
            'last_sync' => $this->last_sync_at,
        ];
    }

    public function updateLastSync(): void
    {
        $this->update(['last_sync_at' => now()]);
    }

    public function getAverageFulfillmentTime(): ?float
    {
        return $this->dropshipOrders()
            ->whereNotNull('shipped_by_supplier_at')
            ->whereNotNull('sent_to_supplier_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, sent_to_supplier_at, shipped_by_supplier_at)) as avg_hours')
            ->value('avg_hours');
    }

    public function getFulfillmentSuccessRate(): float
    {
        $totalOrders = $this->dropshipOrders()->count();

        if ($totalOrders === 0) {
            return 0;
        }

        $successfulOrders = $this->dropshipOrders()
            ->whereIn('status', ['delivered', 'shipped_by_supplier'])
            ->count();

        return round(($successfulOrders / $totalOrders) * 100, 2);
    }
}
