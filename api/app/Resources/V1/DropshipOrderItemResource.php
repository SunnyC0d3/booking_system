<?php

namespace App\Resources\V1;

use App\Constants\DropshipStatuses;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DropshipOrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'dropship_order_id' => $this->dropship_order_id,
            'order_item_id' => $this->order_item_id,
            'supplier_product_id' => $this->supplier_product_id,
            'supplier_sku' => $this->supplier_sku,
            'quantity' => $this->quantity,
            'supplier_price' => $this->supplier_price,
            'supplier_price_formatted' => $this->getSupplierPriceFormatted(),
            'supplier_price_pounds' => $this->getSupplierPriceInPounds(),
            'retail_price' => $this->retail_price,
            'retail_price_formatted' => $this->getRetailPriceFormatted(),
            'retail_price_pounds' => $this->getRetailPriceInPounds(),
            'profit_per_item' => $this->profit_per_item,
            'profit_per_item_formatted' => $this->getProfitPerItemFormatted(),
            'profit_per_item_pounds' => $this->getProfitPerItemInPounds(),
            'total_supplier_cost' => $this->getTotalSupplierCost(),
            'total_supplier_cost_formatted' => $this->getTotalSupplierCostFormatted(),
            'total_supplier_cost_pounds' => $this->getTotalSupplierCostInPounds(),
            'total_retail_value' => $this->getTotalRetailValue(),
            'total_retail_value_formatted' => $this->getTotalRetailValueFormatted(),
            'total_retail_value_pounds' => $this->getTotalRetailValueInPounds(),
            'total_profit' => $this->getTotalProfit(),
            'total_profit_formatted' => $this->getTotalProfitFormatted(),
            'total_profit_pounds' => $this->getTotalProfitInPounds(),
            'profit_margin_percentage' => $this->getProfitMarginPercentage(),
            'profit_margin_percentage_formatted' => $this->getProfitMarginPercentageFormatted(),
            'product_details' => $this->product_details,
            'product_name' => $this->getProductName(),
            'product_description' => $this->getProductDescription(),
            'product_image' => $this->when(
                $this->hasProductImage(),
                fn() => $this->getProductImage()
            ),
            'has_product_image' => $this->hasProductImage(),
            'supplier_item_data' => $this->supplier_item_data,
            'status' => $this->status,
            'status_label' => DropshipStatuses::labels()[$this->status] ?? $this->status,
            'notes' => $this->notes,
            'weight' => $this->getWeight(),
            'total_weight' => $this->getTotalWeight(),
            'dimensions' => $this->getDimensions(),
            'is_pending' => $this->isPending(),
            'is_confirmed' => $this->isConfirmed(),
            'is_shipped' => $this->isShipped(),
            'is_delivered' => $this->isDelivered(),
            'is_cancelled' => $this->isCancelled(),
            'can_be_cancelled' => $this->canBeCancelled(),
            'dropship_order' => new DropshipOrderResource($this->whenLoaded('dropshipOrder')),
            'supplier_product' => new SupplierProductResource($this->whenLoaded('supplierProduct')),
            'order_item' => new OrderItemResource($this->whenLoaded('orderItem')),
            'supplier_data' => $this->when(
                $this->relationLoaded('supplierProduct'),
                fn() => $this->getSupplierData()
            ),
            'health_indicators' => [
                'stock_available' => $this->supplierProduct?->isInStock() ? 'good' : 'out_of_stock',
                'profit_margin' => $this->getProfitMarginPercentage() > 20 ? 'good' : 'low',
                'quantity_reasonable' => $this->quantity <= 10 ? 'good' : 'high_quantity',
                'supplier_active' => $this->supplierProduct?->is_active ? 'good' : 'inactive',
            ],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
