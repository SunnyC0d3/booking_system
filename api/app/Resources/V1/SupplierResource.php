<?php

namespace App\Resources\V1;

use App\Constants\SupplierStatuses;
use App\Constants\SupplierIntegrationTypes;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'company_name' => $this->company_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'country' => $this->country,
            'contact_person' => $this->contact_person,
            'status' => $this->status,
            'status_label' => SupplierStatuses::labels()[$this->status] ?? $this->status,
            'integration_type' => $this->integration_type,
            'integration_type_label' => SupplierIntegrationTypes::labels()[$this->integration_type] ?? $this->integration_type,
            'commission_rate' => $this->commission_rate,
            'commission_rate_formatted' => $this->getCommissionRateFormatted(),
            'processing_time_days' => $this->processing_time_days,
            'processing_time_formatted' => $this->getProcessingTimeFormatted(),
            'shipping_methods' => $this->shipping_methods,
            'integration_config' => $this->when(
                $request->user()->hasPermission('manage_supplier_integrations'),
                $this->integration_config
            ),
            'api_endpoint' => $this->when(
                $request->user()->hasPermission('manage_supplier_integrations'),
                $this->api_endpoint
            ),
            'webhook_url' => $this->when(
                $request->user()->hasPermission('manage_supplier_integrations'),
                $this->webhook_url
            ),
            'notes' => $this->notes,
            'auto_fulfill' => $this->auto_fulfill,
            'stock_sync_enabled' => $this->stock_sync_enabled,
            'price_sync_enabled' => $this->price_sync_enabled,
            'last_sync_at' => $this->last_sync_at,
            'minimum_order_value' => $this->minimum_order_value,
            'minimum_order_value_formatted' => $this->getMinimumOrderValueFormatted(),
            'maximum_order_value' => $this->maximum_order_value,
            'maximum_order_value_formatted' => $this->getMaximumOrderValueFormatted(),
            'supported_countries' => $this->supported_countries,
            'is_active' => $this->isActive(),
            'can_auto_fulfill' => $this->canAutoFulfill(),
            'can_sync_stock' => $this->canSyncStock(),
            'can_sync_prices' => $this->canSyncPrices(),
            'has_api_integration' => $this->hasApiIntegration(),
            'has_webhook_integration' => $this->hasWebhookIntegration(),
            'supplier_products_count' => $this->whenCounted('supplierProducts'),
            'dropship_orders_count' => $this->whenCounted('dropshipOrders'),
            'supplier_products' => SupplierProductResource::collection($this->whenLoaded('supplierProducts')),
            'dropship_orders' => DropshipOrderResource::collection($this->whenLoaded('dropshipOrders')),
            'supplier_integrations' => SupplierIntegrationResource::collection($this->whenLoaded('supplierIntegrations')),
            'health_stats' => $this->when(
                $this->relationLoaded('supplierProducts') || $this->relationLoaded('dropshipOrders'),
                function() {
                    return $this->getHealthStats();
                }
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
