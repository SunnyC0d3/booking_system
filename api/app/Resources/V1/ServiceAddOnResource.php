<?php

namespace App\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceAddOnResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price,
            'formatted_price' => $this->getFormattedPriceAttribute(),
            'duration_minutes' => $this->duration_minutes,
            'formatted_duration' => $this->getFormattedDurationAttribute(),
            'category' => $this->category,
            'category_display_name' => $this->getCategoryDisplayNameAttribute(),
            'category_icon' => $this->getCategoryIconAttribute(),
            'is_active' => $this->is_active,
            'is_required' => $this->is_required,
            'max_quantity' => $this->max_quantity,
            'sort_order' => $this->sort_order,
            'has_quantity_limit' => $this->hasQuantityLimit(),
            'adds_duration' => $this->addsDuration(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
