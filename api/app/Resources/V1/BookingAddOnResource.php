<?php

namespace App\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingAddOnResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'service_add_on' => $this->whenLoaded('serviceAddOn', function () {
                return [
                    'id' => $this->serviceAddOn->id,
                    'name' => $this->serviceAddOn->name,
                    'description' => $this->serviceAddOn->description,
                    'category' => $this->serviceAddOn->category,
                    'category_display_name' => $this->serviceAddOn->getCategoryDisplayNameAttribute(),
                ];
            }),
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'total_price' => $this->total_price,
            'duration_minutes' => $this->duration_minutes,
            'formatted_unit_price' => $this->getFormattedUnitPriceAttribute(),
            'formatted_total_price' => $this->getFormattedTotalPriceAttribute(),
            'formatted_duration' => $this->getFormattedDurationAttribute(),
            'total_duration_minutes' => $this->getTotalDurationMinutes(),
            'formatted_total_duration' => $this->getFormattedTotalDurationAttribute(),
        ];
    }
}
