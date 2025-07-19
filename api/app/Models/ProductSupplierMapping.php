<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProductSupplierMapping extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'supplier_id',
        'supplier_product_id',
        'is_primary',
        'is_active',
        'priority_order',
        'markup_percentage',
        'fixed_markup',
        'markup_type',
        'minimum_stock_threshold',
        'auto_update_price',
        'auto_update_stock',
        'auto_update_description',
        'field_mappings',
        'last_price_update',
        'last_stock_update',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'is_active' => 'boolean',
        'markup_percentage' => 'decimal:2',
        'fixed_markup' => 'integer',
        'auto_update_price' => 'boolean',
        'auto_update_stock' => 'boolean',
        'auto_update_description' => 'boolean',
        'field_mappings' => 'array',
        'last_price_update' => 'datetime',
        'last_stock_update' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function supplierProduct(): BelongsTo
    {
        return $this->belongsTo(SupplierProduct::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    public function scopeByProduct($query, int $productId)
    {
        return $query->where('product_id', $productId);
    }

    public function scopeBySupplier($query, int $supplierId)
    {
        return $query->where('supplier_id', $supplierId);
    }

    public function scopeOrderedByPriority($query)
    {
        return $query->orderBy('priority_order')->orderBy('is_primary', 'desc');
    }

    public function isPrimary(): bool
    {
        return $this->is_primary;
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function canUpdatePrice(): bool
    {
        return $this->auto_update_price && $this->is_active;
    }

    public function canUpdateStock(): bool
    {
        return $this->auto_update_stock && $this->is_active;
    }

    public function canUpdateDescription(): bool
    {
        return $this->auto_update_description && $this->is_active;
    }

    public function usesPercentageMarkup(): bool
    {
        return $this->markup_type === 'percentage';
    }

    public function usesFixedMarkup(): bool
    {
        return $this->markup_type === 'fixed';
    }

    public function calculateRetailPrice(int $supplierPriceInPennies): int
    {
        if ($this->usesPercentageMarkup()) {
            return (int) round($supplierPriceInPennies * (1 + ($this->markup_percentage / 100)));
        }

        return $supplierPriceInPennies + $this->fixed_markup;
    }

    public function getMarkupAmount(int $supplierPriceInPennies): int
    {
        if ($this->usesPercentageMarkup()) {
            return (int) round($supplierPriceInPennies * ($this->markup_percentage / 100));
        }

        return $this->fixed_markup;
    }

    public function getMarkupAmountInPounds(int $supplierPriceInPennies): float
    {
        return $this->getMarkupAmount($supplierPriceInPennies) / 100;
    }

    public function getMarkupAmountFormatted(int $supplierPriceInPennies): string
    {
        return '£' . number_format($this->getMarkupAmountInPounds($supplierPriceInPennies), 2);
    }

    public function getFixedMarkupInPounds(): float
    {
        return $this->fixed_markup / 100;
    }

    public function getFixedMarkupFormatted(): string
    {
        return '£' . number_format($this->getFixedMarkupInPounds(), 2);
    }

    public function getMarkupPercentageFormatted(): string
    {
        return number_format($this->markup_percentage, 2) . '%';
    }

    public function getMarkupDisplayText(): string
    {
        if ($this->usesPercentageMarkup()) {
            return $this->getMarkupPercentageFormatted();
        }

        return $this->getFixedMarkupFormatted();
    }

    public function updatePricing(int $newSupplierPrice): void
    {
        if (!$this->canUpdatePrice()) {
            return;
        }

        $newRetailPrice = $this->calculateRetailPrice($newSupplierPrice);

        $this->supplierProduct->update([
            'supplier_price' => $newSupplierPrice,
            'retail_price' => $newRetailPrice,
        ]);

        $this->product->update([
            'price' => $newRetailPrice,
            'supplier_cost' => $newSupplierPrice,
        ]);

        $this->update(['last_price_update' => now()]);
    }

    public function updateStock(int $newStockLevel): void
    {
        if (!$this->canUpdateStock()) {
            return;
        }

        $adjustedStock = max(0, $newStockLevel - $this->minimum_stock_threshold);

        $this->supplierProduct->update(['stock_quantity' => $newStockLevel]);
        $this->product->update(['quantity' => $adjustedStock]);

        $this->update(['last_stock_update' => now()]);
    }

    public function updateDescription(string $newDescription): void
    {
        if (!$this->canUpdateDescription()) {
            return;
        }

        $this->supplierProduct->update(['description' => $newDescription]);
        $this->product->update(['description' => $newDescription]);
    }

    public function syncFromSupplierProduct(): void
    {
        if (!$this->supplierProduct || !$this->is_active) {
            return;
        }

        if ($this->canUpdatePrice()) {
            $this->updatePricing($this->supplierProduct->supplier_price);
        }

        if ($this->canUpdateStock()) {
            $this->updateStock($this->supplierProduct->stock_quantity);
        }

        if ($this->canUpdateDescription() && $this->supplierProduct->description) {
            $this->updateDescription($this->supplierProduct->description);
        }
    }

    public function isStockBelowThreshold(): bool
    {
        if (!$this->supplierProduct) {
            return true;
        }

        return $this->supplierProduct->stock_quantity <= $this->minimum_stock_threshold;
    }

    public function getAvailableStock(): int
    {
        if (!$this->supplierProduct) {
            return 0;
        }

        return max(0, $this->supplierProduct->stock_quantity - $this->minimum_stock_threshold);
    }

    public function makePrimary(): void
    {
        $this->product->productMappings()
            ->where('id', '!=', $this->id)
            ->update(['is_primary' => false]);

        $this->update(['is_primary' => true]);

        $this->product->update(['primary_supplier_id' => $this->supplier_id]);
    }

    public function activate(): void
    {
        $this->update(['is_active' => true]);
    }

    public function deactivate(): void
    {
        $this->update(['is_active' => false]);

        if ($this->is_primary) {
            $nextMapping = $this->product->productMappings()
                ->where('id', '!=', $this->id)
                ->where('is_active', true)
                ->orderBy('priority_order')
                ->first();

            if ($nextMapping) {
                $nextMapping->makePrimary();
            } else {
                $this->product->update(['primary_supplier_id' => null]);
            }
        }
    }

    public function updateMarkup(string $type, float $value): void
    {
        if ($type === 'percentage') {
            $this->update([
                'markup_type' => 'percentage',
                'markup_percentage' => $value,
            ]);
        } else {
            $this->update([
                'markup_type' => 'fixed',
                'fixed_markup' => (int) round($value * 100),
            ]);
        }

        if ($this->canUpdatePrice() && $this->supplierProduct) {
            $this->updatePricing($this->supplierProduct->supplier_price);
        }
    }

    public function getFieldMapping(string $field): ?string
    {
        return $this->field_mappings[$field] ?? null;
    }

    public function setFieldMapping(string $field, string $mapping): void
    {
        $mappings = $this->field_mappings ?? [];
        $mappings[$field] = $mapping;
        $this->update(['field_mappings' => $mappings]);
    }

    public function removeFieldMapping(string $field): void
    {
        $mappings = $this->field_mappings ?? [];
        unset($mappings[$field]);
        $this->update(['field_mappings' => $mappings]);
    }

    public function getLastPriceUpdateAgo(): string
    {
        if (!$this->last_price_update) {
            return 'Never';
        }

        return $this->last_price_update->diffForHumans();
    }

    public function getLastStockUpdateAgo(): string
    {
        if (!$this->last_stock_update) {
            return 'Never';
        }

        return $this->last_stock_update->diffForHumans();
    }

    public function getHealthStatus(): array
    {
        return [
            'is_active' => $this->is_active,
            'is_primary' => $this->is_primary,
            'supplier_product_exists' => $this->supplierProduct !== null,
            'supplier_product_active' => $this->supplierProduct?->is_active ?? false,
            'stock_available' => $this->getAvailableStock() > 0,
            'recent_price_update' => $this->last_price_update && $this->last_price_update->isAfter(now()->subDays(7)),
            'recent_stock_update' => $this->last_stock_update && $this->last_stock_update->isAfter(now()->subDays(1)),
        ];
    }
}
