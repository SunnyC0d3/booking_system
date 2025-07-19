<?php

namespace App\Models;

use App\Constants\DropshipProductSyncStatuses;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SupplierProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_id',
        'product_id',
        'supplier_sku',
        'supplier_product_id',
        'name',
        'description',
        'supplier_price',
        'retail_price',
        'stock_quantity',
        'weight',
        'length',
        'width',
        'height',
        'sync_status',
        'images',
        'attributes',
        'categories',
        'is_active',
        'is_mapped',
        'last_synced_at',
        'sync_errors',
        'minimum_order_quantity',
        'processing_time_days',
    ];

    protected $casts = [
        'supplier_price' => 'integer',
        'retail_price' => 'integer',
        'images' => 'array',
        'attributes' => 'array',
        'categories' => 'array',
        'is_active' => 'boolean',
        'is_mapped' => 'boolean',
        'last_synced_at' => 'datetime',
        'weight' => 'decimal:2',
        'length' => 'decimal:2',
        'width' => 'decimal:2',
        'height' => 'decimal:2',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productMapping(): HasOne
    {
        return $this->hasOne(ProductSupplierMapping::class);
    }

    public function dropshipOrderItems(): HasMany
    {
        return $this->hasMany(DropshipOrderItem::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeMapped($query)
    {
        return $query->where('is_mapped', true);
    }

    public function scopeInStock($query)
    {
        return $query->where('stock_quantity', '>', 0);
    }

    public function scopeSynced($query)
    {
        return $query->where('sync_status', DropshipProductSyncStatuses::SYNCED);
    }

    public function scopeOutOfSync($query)
    {
        return $query->whereIn('sync_status', [
            DropshipProductSyncStatuses::OUT_OF_SYNC,
            DropshipProductSyncStatuses::SYNC_FAILED
        ]);
    }

    public function scopeBySupplier($query, int $supplierId)
    {
        return $query->where('supplier_id', $supplierId);
    }

    public function getSupplierPriceInPounds(): float
    {
        return $this->supplier_price / 100;
    }

    public function getSupplierPriceFormatted(): string
    {
        return '£' . number_format($this->getSupplierPriceInPounds(), 2);
    }

    public function getRetailPriceInPounds(): ?float
    {
        return $this->retail_price ? $this->retail_price / 100 : null;
    }

    public function getRetailPriceFormatted(): string
    {
        return $this->retail_price ? '£' . number_format($this->getRetailPriceInPounds(), 2) : 'Not set';
    }

    public function isInStock(): bool
    {
        return $this->stock_quantity > 0;
    }

    public function isSynced(): bool
    {
        return $this->sync_status === DropshipProductSyncStatuses::SYNCED;
    }

    public function hasErrors(): bool
    {
        return in_array($this->sync_status, [
            DropshipProductSyncStatuses::SYNC_FAILED,
            DropshipProductSyncStatuses::OUT_OF_SYNC
        ]);
    }

    public function isDiscontinued(): bool
    {
        return $this->sync_status === DropshipProductSyncStatuses::SUPPLIER_DISCONTINUED;
    }

    public function canOrder(int $quantity): bool
    {
        return $this->is_active &&
            $this->stock_quantity >= $quantity &&
            $quantity >= $this->minimum_order_quantity;
    }

    public function calculateProfitMargin(): ?float
    {
        if (!$this->retail_price || !$this->supplier_price) {
            return null;
        }

        return round((($this->retail_price - $this->supplier_price) / $this->retail_price) * 100, 2);
    }

    public function getProfitMarginFormatted(): string
    {
        $margin = $this->calculateProfitMargin();
        return $margin !== null ? number_format($margin, 2) . '%' : 'N/A';
    }

    public function getDimensions(): array
    {
        return [
            'length' => (float) $this->length,
            'width' => (float) $this->width,
            'height' => (float) $this->height,
        ];
    }

    public function getDimensionsFormatted(): string
    {
        $dimensions = $this->getDimensions();
        return $dimensions['length'] . ' x ' . $dimensions['width'] . ' x ' . $dimensions['height'] . ' cm';
    }

    public function getWeightFormatted(): string
    {
        if ($this->weight >= 1) {
            return number_format($this->weight, 2) . 'kg';
        }
        return number_format($this->weight * 1000, 0) . 'g';
    }

    public function updateSyncStatus(string $status, ?string $error = null): void
    {
        $this->update([
            'sync_status' => $status,
            'sync_errors' => $error,
            'last_synced_at' => now(),
        ]);
    }

    public function markAsSynced(): void
    {
        $this->updateSyncStatus(DropshipProductSyncStatuses::SYNCED);
    }

    public function markAsFailed(string $error): void
    {
        $this->updateSyncStatus(DropshipProductSyncStatuses::SYNC_FAILED, $error);
    }

    public function markAsOutOfSync(): void
    {
        $this->updateSyncStatus(DropshipProductSyncStatuses::OUT_OF_SYNC);
    }

    public function updateStock(int $quantity): void
    {
        $this->update([
            'stock_quantity' => $quantity,
            'last_synced_at' => now(),
        ]);

        if ($this->is_mapped && $this->product) {
            $this->product->update(['quantity' => $quantity]);
        }
    }

    public function updatePrice(int $supplierPrice, ?int $retailPrice = null): void
    {
        $updateData = [
            'supplier_price' => $supplierPrice,
            'last_synced_at' => now(),
        ];

        if ($retailPrice !== null) {
            $updateData['retail_price'] = $retailPrice;
        }

        $this->update($updateData);

        if ($this->is_mapped && $this->product && $retailPrice) {
            $this->product->update(['price' => $retailPrice]);
        }
    }

    public function createMappedProduct(): Product
    {
        $product = Product::create([
            'vendor_id' => 1,
            'product_category_id' => 1,
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->retail_price ?? $this->supplier_price,
            'quantity' => $this->stock_quantity,
            'product_status_id' => 1,
            'is_dropship' => true,
            'primary_supplier_id' => $this->supplier_id,
            'supplier_cost' => $this->supplier_price,
            'auto_fulfill_dropship' => true,
            'weight' => $this->weight,
            'length' => $this->length,
            'width' => $this->width,
            'height' => $this->height,
        ]);

        $this->update([
            'product_id' => $product->id,
            'is_mapped' => true,
        ]);

        ProductSupplierMapping::create([
            'product_id' => $product->id,
            'supplier_id' => $this->supplier_id,
            'supplier_product_id' => $this->id,
            'is_primary' => true,
        ]);

        return $product;
    }

    public function getLastSyncAgo(): ?string
    {
        if (!$this->last_synced_at) {
            return 'Never';
        }

        return $this->last_synced_at->diffForHumans();
    }

    public function getSyncStatusLabel(): string
    {
        return DropshipProductSyncStatuses::labels()[$this->sync_status] ?? 'Unknown';
    }
}
