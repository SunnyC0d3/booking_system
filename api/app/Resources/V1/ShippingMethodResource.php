<?php

namespace App\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShippingMethodResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'carrier' => $this->carrier,
            'service_code' => $this->service_code,
            'estimated_delivery' => $this->getEstimatedDeliveryAttribute(),
            'estimated_days_min' => $this->estimated_days_min,
            'estimated_days_max' => $this->estimated_days_max,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'metadata' => $this->metadata,
            'zones_count' => $this->whenCounted('zones'),
            'rates_count' => $this->whenCounted('rates'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
