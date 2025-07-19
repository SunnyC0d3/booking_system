<?php

namespace App\Resources\V1;

use App\Constants\DropshipProductSyncStatuses;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'supplier_id' => $this->supplier_id,
            'product_id' => $this->product_id,
            'supplier_sku' => $this->supplier_sku,
            'supplier_product_id' => $this->supplier_product_id,
            'name' => $this->name,
            'description' => $this->description,
            'supplier_price' => $this->supplier_price,
            'supplier_price_formatted' => $this->getSupplierPriceFormatted(),
            'supplier_price_pounds' => $this->getSupplierPriceInPounds(),
            'retail_price' => $this->retail_price,
            'retail_price_formatted' => $this->getRetailPriceFormatted(),
            'retail_price_pounds' => $this->getRetailPriceInPounds(),
            'profit_margin_percentage' => $this->calculateProfitMargin(),
            'profit_margin_formatted' => $this->getProfitMarginFormatted(),
            'stock_quantity' => $this->stock_quantity,
            'is_in_stock' => $this->isInStock(),
            'weight' => $this->weight,
            'weight_formatted' => $this->getWeightFormatted(),
            'length' => $this->length,
            'width' => $this->width,
            'height' => $this->height,
            'dimensions' => $this->getDimensions(),
            'dimensions_formatted' => $this->getDimensionsFormatted(),
            'sync_status' => $this->sync_status,
            'sync_status_label' => $this->getSyncStatusLabel(),
            'is_synced' => $this->isSynced(),
            'has_errors' => $this->hasErrors(),
            'is_discontinued' => $this->isDiscontinued(),
            'images' => $this->when(
                $request->boolean('include_images', true),
                $this->images
            ),
            'attributes' => $this->when(
                $request->boolean('include_attributes', true),
                $this->attributes
            ),
            'categories' => $this->categories,
            'is_active' => $this->is_active,
            'is_mapped' => $this->is_mapped,
            'last_synced_at' => $this->last_synced_at,
            'last_sync_ago' => $this->getLastSyncAgo(),
            'sync_errors' => $this->sync_errors,
            'minimum_order_quantity' => $this->minimum_order_quantity,
            'processing_time_days' => $this->processing_time_days,
            'can_order' => $this->when(
                $this->minimum_order_quantity,
                fn() => $this->canOrder($this->minimum_order_quantity)
            ),
            'supplier' => new SupplierResource($this->whenLoaded('supplier')),
            'product' => new ProductResource($this->whenLoaded('product')),
            'product_mapping' => new ProductSupplierMappingResource($this->whenLoaded('productMapping')),
            'dropship_order_items' => DropshipOrderItemResource::collection($this->whenLoaded('dropshipOrderItems')),
            'dropship_order_items_count' => $this->whenCounted('dropshipOrderItems'),
            'recent_orders' => $this->when(
                $this->relationLoaded('dropshipOrderItems'),
                function() {
                    return $this->dropshipOrderItems->take(5)->map(function($item) {
                        return [
                            'id' => $item->id,
                            'dropship_order_id' => $item->dropship_order_id,
                            'order_id' => $item->dropshipOrder->order_id ?? null,
                            'quantity' => $item->quantity,
                            'status' => $item->status,
                            'created_at' => $item->created_at,
                        ];
                    });
                }
            ),
            'health_indicators' => [
                'stock_level' => $this->stock_quantity > 10 ? 'good' : ($this->stock_quantity > 0 ? 'low' : 'out'),
                'sync_health' => $this->isSynced() ? 'good' : 'needs_attention',
                'price_set' => $this->retail_price ? 'good' : 'needs_attention',
                'mapped' => $this->is_mapped ? 'good' : 'unmapped',
                'overall' => $this->getOverallHealth(),
            ],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    protected function getOverallHealth(): string
    {
        $issues = 0;

        if (!$this->isInStock()) $issues++;
        if (!$this->isSynced()) $issues++;
        if (!$this->retail_price) $issues++;
        if (!$this->is_mapped) $issues++;
        if (!$this->is_active) $issues++;

        return match(true) {
            $issues === 0 => 'excellent',
            $issues <= 1 => 'good',
            $issues <= 2 => 'fair',
            $issues <= 3 => 'poor',
            default => 'critical'
        };
    }
}
