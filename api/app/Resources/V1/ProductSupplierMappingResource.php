<?php

namespace App\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductSupplierMappingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'supplier_id' => $this->supplier_id,
            'supplier_product_id' => $this->supplier_product_id,
            'is_primary' => $this->is_primary,
            'is_active' => $this->is_active,
            'priority_order' => $this->priority_order,
            'markup_percentage' => $this->markup_percentage,
            'markup_percentage_formatted' => $this->getMarkupPercentageFormatted(),
            'fixed_markup' => $this->fixed_markup,
            'fixed_markup_formatted' => $this->getFixedMarkupFormatted(),
            'fixed_markup_pounds' => $this->getFixedMarkupInPounds(),
            'markup_type' => $this->markup_type,
            'markup_display_text' => $this->getMarkupDisplayText(),
            'uses_percentage_markup' => $this->usesPercentageMarkup(),
            'uses_fixed_markup' => $this->usesFixedMarkup(),
            'minimum_stock_threshold' => $this->minimum_stock_threshold,
            'auto_update_price' => $this->auto_update_price,
            'auto_update_stock' => $this->auto_update_stock,
            'auto_update_description' => $this->auto_update_description,
            'can_update_price' => $this->canUpdatePrice(),
            'can_update_stock' => $this->canUpdateStock(),
            'can_update_description' => $this->canUpdateDescription(),
            'field_mappings' => $this->field_mappings,
            'last_price_update' => $this->last_price_update,
            'last_price_update_ago' => $this->getLastPriceUpdateAgo(),
            'last_stock_update' => $this->last_stock_update,
            'last_stock_update_ago' => $this->getLastStockUpdateAgo(),
            'is_stock_below_threshold' => $this->isStockBelowThreshold(),
            'available_stock' => $this->getAvailableStock(),
            'sample_calculations' => $this->when(
                $this->supplierProduct,
                function() {
                    $supplierPrice = $this->supplierProduct->supplier_price;
                    return [
                        'supplier_price' => $supplierPrice,
                        'supplier_price_formatted' => $this->supplierProduct->getSupplierPriceFormatted(),
                        'calculated_retail_price' => $this->calculateRetailPrice($supplierPrice),
                        'calculated_retail_price_formatted' => '£' . number_format($this->calculateRetailPrice($supplierPrice) / 100, 2),
                        'markup_amount' => $this->getMarkupAmount($supplierPrice),
                        'markup_amount_formatted' => $this->getMarkupAmountFormatted($supplierPrice),
                    ];
                }
            ),
            'product' => new ProductResource($this->whenLoaded('product')),
            'supplier' => new SupplierResource($this->whenLoaded('supplier')),
            'supplier_product' => new SupplierProductResource($this->whenLoaded('supplierProduct')),
            'health_status' => $this->getHealthStatus(),
            'status_indicators' => [
                'is_primary' => $this->is_primary ? 'primary' : 'secondary',
                'is_active' => $this->is_active ? 'active' : 'inactive',
                'supplier_product_active' => $this->supplierProduct?->is_active ? 'active' : 'inactive',
                'stock_status' => $this->getStockStatus(),
                'sync_status' => $this->getSyncStatus(),
                'automation_status' => $this->getAutomationStatus(),
                'overall_health' => $this->getOverallHealth(),
            ],
            'automation_settings' => [
                'price_sync' => [
                    'enabled' => $this->auto_update_price,
                    'last_update' => $this->last_price_update,
                    'status' => $this->getPriceSyncStatus(),
                ],
                'stock_sync' => [
                    'enabled' => $this->auto_update_stock,
                    'last_update' => $this->last_stock_update,
                    'status' => $this->getStockSyncStatus(),
                ],
                'description_sync' => [
                    'enabled' => $this->auto_update_description,
                    'status' => $this->auto_update_description ? 'enabled' : 'disabled',
                ],
            ],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    protected function getStockStatus(): string
    {
        if (!$this->supplierProduct) {
            return 'no_supplier_product';
        }

        if ($this->isStockBelowThreshold()) {
            return 'below_threshold';
        }

        if ($this->supplierProduct->stock_quantity > 0) {
            return 'in_stock';
        }

        return 'out_of_stock';
    }

    protected function getSyncStatus(): string
    {
        $issues = 0;

        if ($this->auto_update_price && (!$this->last_price_update || $this->last_price_update->lt(now()->subWeek()))) {
            $issues++;
        }

        if ($this->auto_update_stock && (!$this->last_stock_update || $this->last_stock_update->lt(now()->subDay()))) {
            $issues++;
        }

        return match($issues) {
            0 => 'up_to_date',
            1 => 'partially_outdated',
            default => 'outdated'
        };
    }

    protected function getAutomationStatus(): string
    {
        $enabledCount = 0;
        if ($this->auto_update_price) $enabledCount++;
        if ($this->auto_update_stock) $enabledCount++;
        if ($this->auto_update_description) $enabledCount++;

        return match($enabledCount) {
            0 => 'manual',
            1 => 'partial',
            2 => 'mostly_automated',
            3 => 'fully_automated'
        };
    }

    protected function getOverallHealth(): string
    {
        $issues = 0;

        if (!$this->is_active) $issues += 3;
        if (!$this->supplierProduct?->is_active) $issues += 2;
        if ($this->isStockBelowThreshold()) $issues += 1;
        if ($this->getSyncStatus() === 'outdated') $issues += 2;
        if ($this->getSyncStatus() === 'partially_outdated') $issues += 1;

        return match(true) {
            $issues === 0 => 'excellent',
            $issues <= 1 => 'good',
            $issues <= 3 => 'fair',
            $issues <= 5 => 'poor',
            default => 'critical'
        };
    }

    protected function getPriceSyncStatus(): string
    {
        if (!$this->auto_update_price) {
            return 'disabled';
        }

        if (!$this->last_price_update) {
            return 'never_synced';
        }

        if ($this->last_price_update->gt(now()->subDay())) {
            return 'recent';
        }

        if ($this->last_price_update->gt(now()->subWeek())) {
            return 'current';
        }

        return 'outdated';
    }

    protected function getStockSyncStatus(): string
    {
        if (!$this->auto_update_stock) {
            return 'disabled';
        }

        if (!$this->last_stock_update) {
            return 'never_synced';
        }

        if ($this->last_stock_update->gt(now()->subHour())) {
            return 'recent';
        }

        if ($this->last_stock_update->gt(now()->subDay())) {
            return 'current';
        }

        return 'outdated';
    }
}
