<?php

namespace App\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceAddOnResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'service_id' => $this->service_id,
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price,
            'formatted_price' => '£' . number_format($this->price / 100, 2),
            'duration_minutes' => $this->duration_minutes,
            'formatted_duration' => $this->formatDuration($this->duration_minutes),
            'is_required' => $this->is_required,
            'is_active' => $this->is_active,
            'max_quantity' => $this->max_quantity,
            'sort_order' => $this->sort_order,
            'metadata' => $this->metadata,

            // Pricing information
            'pricing' => [
                'price_pence' => $this->price,
                'price_pounds' => $this->price / 100,
                'formatted_price' => '£' . number_format($this->price / 100, 2),
                'currency' => 'GBP',
            ],

            // Service relationship
            'service' => $this->whenLoaded('service', function () {
                return [
                    'id' => $this->service->id,
                    'name' => $this->service->name,
                ];
            }),

            // Booking constraints
            'constraints' => [
                'is_required' => $this->is_required,
                'max_quantity' => $this->max_quantity,
                'is_available' => $this->is_active,
            ],

            // Statistics (when loaded)
            'statistics' => $this->when(
                isset($this->bookings_count),
                function () {
                    return [
                        'total_bookings' => $this->bookings_count ?? 0,
                    ];
                }
            ),

            // Timestamps
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'created_at_human' => $this->created_at?->diffForHumans(),
            'updated_at_human' => $this->updated_at?->diffForHumans(),
        ];
    }

    /**
     * Format duration in minutes to human readable format
     */
    private function formatDuration(int $minutes): string
    {
        if ($minutes === 0) {
            return 'No additional time';
        }

        if ($minutes < 60) {
            return "{$minutes} minutes";
        }

        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;

        if ($remainingMinutes === 0) {
            return $hours === 1 ? "1 hour" : "{$hours} hours";
        }

        $hoursText = $hours === 1 ? "1 hour" : "{$hours} hours";
        $minutesText = "{$remainingMinutes} minutes";

        return "{$hoursText} {$minutesText}";
    }
}
