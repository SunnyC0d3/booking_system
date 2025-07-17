<?php

namespace App\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShippingQuoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this['id'],
            'name' => $this['name'],
            'description' => $this['description'],
            'carrier' => $this['carrier'],
            'service_code' => $this['service_code'],
            'cost' => $this['cost'],
            'cost_formatted' => $this['cost_formatted'],
            'is_free' => $this['is_free'],
            'estimated_delivery' => $this['estimated_delivery'],
            'estimated_days_min' => $this['estimated_days_min'],
            'estimated_days_max' => $this['estimated_days_max'],
            'estimated_date_min' => $this['estimated_date_min'],
            'estimated_date_max' => $this['estimated_date_max'],
            'delivery_window' => $this->when(
                $this['estimated_days_min'] === $this['estimated_days_max'],
                $this['estimated_days_min'] . ' day' . ($this['estimated_days_min'] > 1 ? 's' : ''),
                $this['estimated_days_min'] . '-' . $this['estimated_days_max'] . ' days'
            ),
            'rate_id' => $this['rate_id'],
            'zone_id' => $this['zone_id'],
            'metadata' => $this['metadata'],
            'is_recommended' => $this->when(
                isset($this['is_recommended']),
                $this['is_recommended']
            ),
            'savings' => $this->when(
                isset($this['savings']),
                [
                    'amount' => $this['savings'],
                    'amount_formatted' => '£' . number_format($this['savings'] / 100, 2),
                ]
            ),
        ];
    }
}
