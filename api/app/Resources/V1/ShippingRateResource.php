<?php

namespace App\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShippingRateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'shipping_method_id' => $this->shipping_method_id,
            'shipping_zone_id' => $this->shipping_zone_id,
            'min_weight' => $this->min_weight,
            'max_weight' => $this->max_weight,
            'weight_range_formatted' => $this->getWeightRangeFormatted(),
            'min_total' => $this->min_total,
            'min_total_formatted' => $this->formatPrice($this->min_total),
            'max_total' => $this->max_total,
            'max_total_formatted' => $this->max_total ? $this->formatPrice($this->max_total) : null,
            'total_range_formatted' => $this->getTotalRangeFormatted(),
            'rate' => $this->rate,
            'rate_formatted' => $this->getRateFormatted(),
            'free_threshold' => $this->free_threshold,
            'free_threshold_formatted' => $this->getFreeThresholdFormatted(),
            'is_active' => $this->is_active,
            'shipping_method' => $this->whenLoaded('shippingMethod', function () {
                return [
                    'id' => $this->shippingMethod->id,
                    'name' => $this->shippingMethod->name,
                    'carrier' => $this->shippingMethod->carrier,
                    'estimated_days_min' => $this->shippingMethod->estimated_days_min,
                    'estimated_days_max' => $this->shippingMethod->estimated_days_max,
                    'is_active' => $this->shippingMethod->is_active,
                ];
            }),
            'shipping_zone' => $this->whenLoaded('shippingZone', function () {
                return [
                    'id' => $this->shippingZone->id,
                    'name' => $this->shippingZone->name,
                    'countries' => $this->shippingZone->countries,
                    'is_active' => $this->shippingZone->is_active,
                ];
            }),
            'sample_calculations' => $this->when(
                $request->boolean('with_samples'),
                function () {
                    return $this->getSampleCalculations();
                }
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    protected function formatPrice(int $priceInPennies): string
    {
        return '£' . number_format($priceInPennies / 100, 2);
    }

    protected function getSampleCalculations(): array
    {
        $samples = [];

        $testTotals = [
            $this->min_total,
            $this->min_total + 1000,
            $this->max_total ?? ($this->min_total + 5000),
        ];

        if ($this->free_threshold) {
            $testTotals[] = $this->free_threshold;
            $testTotals[] = $this->free_threshold + 100;
        }

        foreach (array_unique($testTotals) as $total) {
            if ($total === null) continue;

            $cost = $this->calculateShippingCost($total);
            $samples[] = [
                'order_total' => $total,
                'order_total_formatted' => $this->formatPrice($total),
                'shipping_cost' => $cost,
                'shipping_cost_formatted' => $this->formatPrice($cost),
                'is_free' => $cost === 0,
            ];
        }

        return array_slice($samples, 0, 5);
    }
}
