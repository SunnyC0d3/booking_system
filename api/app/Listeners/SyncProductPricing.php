<?php

namespace App\Listeners;

use App\Events\SupplierProductPriceChanged;
use App\Models\SupplierProduct;
use App\Models\ProductSupplierMapping;
use App\Models\Product;
use App\Jobs\SyncSupplierProductData;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;

class SyncProductPricing implements ShouldQueue
{
    use InteractsWithQueue;

    public $queue = 'pricing_updates';

    public function handle(SupplierProductPriceChanged $event): void
    {
        $supplierProduct = $event->supplierProduct;
        $oldPrice = $event->oldPrice;
        $newPrice = $event->newPrice;

        try {
            Log::info('Processing supplier product price change', [
                'supplier_product_id' => $supplierProduct->id,
                'supplier_sku' => $supplierProduct->supplier_sku,
                'old_price' => $oldPrice,
                'new_price' => $newPrice,
                'price_change_amount' => $newPrice - $oldPrice,
                'price_change_percentage' => $oldPrice > 0 ? round((($newPrice - $oldPrice) / $oldPrice) * 100, 2) : 0
            ]);

            $this->syncMappedProductPricing($supplierProduct, $oldPrice, $newPrice);
            $this->handleSignificantPriceChanges($supplierProduct, $oldPrice, $newPrice);
            $this->updateCompetitorPricing($supplierProduct);
            $this->dispatchBulkPricingJobs($supplierProduct);

        } catch (Exception $e) {
            Log::error('Failed to sync product pricing from supplier price change', [
                'supplier_product_id' => $supplierProduct->id,
                'error' => $e->getMessage()
            ]);

            $this->failed($e);
        }
    }

    public function failed(Exception $exception): void
    {
        Log::critical('SyncProductPricing listener failed', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }

    private function syncMappedProductPricing(SupplierProduct $supplierProduct, int $oldPrice, int $newPrice): void
    {
        $mappings = ProductSupplierMapping::where('supplier_product_id', $supplierProduct->id)
            ->where('is_active', true)
            ->where('auto_update_price', true)
            ->with(['product', 'supplier'])
            ->get();

        if ($mappings->isEmpty()) {
            Log::debug('No active price-sync mappings found for supplier product', [
                'supplier_product_id' => $supplierProduct->id
            ]);
            return;
        }

        foreach ($mappings as $mapping) {
            try {
                $this->updateMappingPricing($mapping, $supplierProduct, $oldPrice, $newPrice);
            } catch (Exception $e) {
                Log::error('Failed to update mapping pricing', [
                    'mapping_id' => $mapping->id,
                    'supplier_product_id' => $supplierProduct->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    private function updateMappingPricing(ProductSupplierMapping $mapping, SupplierProduct $supplierProduct, int $oldPrice, int $newPrice): void
    {
        if (!$mapping->canUpdatePrice()) {
            return;
        }

        $oldRetailPrice = $mapping->calculateRetailPrice($oldPrice);
        $newRetailPrice = $mapping->calculateRetailPrice($newPrice);

        DB::transaction(function () use ($mapping, $supplierProduct, $newPrice, $newRetailPrice, $oldRetailPrice) {
            $supplierProduct->update([
                'retail_price' => $newRetailPrice,
                'last_synced_at' => now()
            ]);

            $mapping->product->update([
                'price' => $newRetailPrice,
                'supplier_cost' => $newPrice,
                'profit_margin_percentage' => $this->calculateProfitMarginPercentage($newPrice, $newRetailPrice)
            ]);

            $mapping->update(['last_price_update' => now()]);
        });

        Log::info('Product pricing updated from supplier price change', [
            'mapping_id' => $mapping->id,
            'product_id' => $mapping->product_id,
            'supplier_product_id' => $supplierProduct->id,
            'old_supplier_price' => $oldPrice,
            'new_supplier_price' => $newPrice,
            'old_retail_price' => $oldRetailPrice,
            'new_retail_price' => $newRetailPrice,
            'markup_type' => $mapping->markup_type,
            'markup_value' => $mapping->usesPercentageMarkup() ? $mapping->markup_percentage : $mapping->fixed_markup
        ]);

        $this->notifyStakeholders($mapping, $oldRetailPrice, $newRetailPrice);
    }

    private function calculateProfitMarginPercentage(int $supplierPrice, int $retailPrice): float
    {
        if ($retailPrice === 0) {
            return 0;
        }

        return round((($retailPrice - $supplierPrice) / $retailPrice) * 100, 2);
    }

    private function handleSignificantPriceChanges(SupplierProduct $supplierProduct, int $oldPrice, int $newPrice): void
    {
        $priceChangePercentage = $oldPrice > 0 ? abs(($newPrice - $oldPrice) / $oldPrice) * 100 : 0;
        $significantChangeThreshold = 10.0;

        if ($priceChangePercentage >= $significantChangeThreshold) {
            $this->logSignificantPriceChange($supplierProduct, $oldPrice, $newPrice, $priceChangePercentage);
            $this->handlePriceChangeApprovals($supplierProduct, $oldPrice, $newPrice, $priceChangePercentage);
        }
    }

    private function logSignificantPriceChange(SupplierProduct $supplierProduct, int $oldPrice, int $newPrice, float $changePercentage): void
    {
        $changeDirection = $newPrice > $oldPrice ? 'increase' : 'decrease';

        Log::warning('Significant supplier price change detected', [
            'supplier_product_id' => $supplierProduct->id,
            'supplier_sku' => $supplierProduct->supplier_sku,
            'product_name' => $supplierProduct->name,
            'supplier_id' => $supplierProduct->supplier_id,
            'old_price' => $oldPrice,
            'new_price' => $newPrice,
            'change_direction' => $changeDirection,
            'change_percentage' => round($changePercentage, 2),
            'change_amount' => abs($newPrice - $oldPrice),
            'requires_review' => $changePercentage >= 25.0
        ]);
    }

    private function handlePriceChangeApprovals(SupplierProduct $supplierProduct, int $oldPrice, int $newPrice, float $changePercentage): void
    {
        $extremeChangeThreshold = 25.0;

        if ($changePercentage >= $extremeChangeThreshold) {
            $this->holdPricingUpdatesForApproval($supplierProduct, $oldPrice, $newPrice, $changePercentage);
        }
    }

    private function holdPricingUpdatesForApproval(SupplierProduct $supplierProduct, int $oldPrice, int $newPrice, float $changePercentage): void
    {
        $mappings = ProductSupplierMapping::where('supplier_product_id', $supplierProduct->id)
            ->where('is_active', true)
            ->where('auto_update_price', true)
            ->get();

        foreach ($mappings as $mapping) {
            $mapping->update([
                'auto_update_price' => false,
                'field_mappings' => array_merge($mapping->field_mappings ?? [], [
                    'pending_price_approval' => [
                        'old_price' => $oldPrice,
                        'new_price' => $newPrice,
                        'change_percentage' => $changePercentage,
                        'held_at' => now()->toISOString(),
                        'reason' => 'Extreme price change requires manual approval'
                    ]
                ])
            ]);
        }

        Log::critical('Extreme price change - automatic updates disabled pending approval', [
            'supplier_product_id' => $supplierProduct->id,
            'change_percentage' => $changePercentage,
            'mappings_affected' => $mappings->count(),
            'action_required' => 'Manual review and approval needed'
        ]);
    }

    private function updateCompetitorPricing(SupplierProduct $supplierProduct): void
    {
        $alternativeMappings = ProductSupplierMapping::where('product_id', $supplierProduct->product_id)
            ->where('supplier_product_id', '!=', $supplierProduct->id)
            ->where('is_active', true)
            ->with(['supplierProduct', 'supplier'])
            ->get();

        if ($alternativeMappings->isNotEmpty()) {
            Log::info('Competitor pricing analysis triggered', [
                'primary_supplier_product_id' => $supplierProduct->id,
                'alternative_suppliers_count' => $alternativeMappings->count(),
                'product_id' => $supplierProduct->product_id
            ]);

            $this->analyzeCompetitivePricing($supplierProduct, $alternativeMappings);
        }
    }

    private function analyzeCompetitivePricing(SupplierProduct $primarySupplierProduct, $alternativeMappings): void
    {
        $competitorPrices = $alternativeMappings->map(function ($mapping) {
            return [
                'supplier_id' => $mapping->supplier_id,
                'supplier_name' => $mapping->supplier->name,
                'supplier_price' => $mapping->supplierProduct->supplier_price,
                'retail_price' => $mapping->calculateRetailPrice($mapping->supplierProduct->supplier_price),
                'last_updated' => $mapping->supplierProduct->last_synced_at
            ];
        });

        $primaryRetailPrice = $primarySupplierProduct->productMapping?->calculateRetailPrice($primarySupplierProduct->supplier_price);

        Log::info('Competitive pricing analysis', [
            'primary_supplier_product_id' => $primarySupplierProduct->id,
            'primary_retail_price' => $primaryRetailPrice,
            'competitor_prices' => $competitorPrices->toArray(),
            'lowest_competitor_price' => $competitorPrices->min('retail_price'),
            'highest_competitor_price' => $competitorPrices->max('retail_price'),
            'average_competitor_price' => $competitorPrices->avg('retail_price')
        ]);
    }

    private function dispatchBulkPricingJobs(SupplierProduct $supplierProduct): void
    {
        $relatedProducts = SupplierProduct::where('supplier_id', $supplierProduct->supplier_id)
            ->where('id', '!=', $supplierProduct->id)
            ->whereHas('productMapping', function ($query) {
                $query->where('auto_update_price', true);
            })
            ->where('last_synced_at', '<', now()->subHours(6))
            ->limit(50)
            ->get();

        if ($relatedProducts->isNotEmpty()) {
            SyncSupplierProductData::dispatch($supplierProduct->supplier, 'pricing_update')
                ->onQueue('bulk_pricing')
                ->delay(now()->addMinutes(5));

            Log::info('Bulk pricing sync job dispatched', [
                'supplier_id' => $supplierProduct->supplier_id,
                'trigger_product_id' => $supplierProduct->id,
                'related_products_count' => $relatedProducts->count()
            ]);
        }
    }

    private function notifyStakeholders(ProductSupplierMapping $mapping, int $oldRetailPrice, int $newRetailPrice): void
    {
        $priceChangePercentage = $oldRetailPrice > 0 ? abs(($newRetailPrice - $oldRetailPrice) / $oldRetailPrice) * 100 : 0;
        $notificationThreshold = 5.0;

        if ($priceChangePercentage >= $notificationThreshold) {
            $changeDirection = $newRetailPrice > $oldRetailPrice ? 'increased' : 'decreased';

            Log::info('Price change notification sent to stakeholders', [
                'product_id' => $mapping->product_id,
                'product_name' => $mapping->product->name,
                'vendor_id' => $mapping->product->vendor_id,
                'old_price' => $oldRetailPrice,
                'new_price' => $newRetailPrice,
                'change_direction' => $changeDirection,
                'change_percentage' => round($priceChangePercentage, 2),
                'supplier_name' => $mapping->supplier->name
            ]);
        }
    }

    public function viaQueue(): string
    {
        return 'pricing_updates';
    }

    public function retryUntil(): \DateTime
    {
        return now()->addMinutes(30);
    }

    public function backoff(): array
    {
        return [30, 60, 180];
    }
}
