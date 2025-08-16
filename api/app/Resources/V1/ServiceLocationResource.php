<?php

namespace App\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceLocationResource extends JsonResource
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
            'address' => $this->address,
            'city' => $this->city,
            'state' => $this->state,
            'postcode' => $this->postcode,
            'country' => $this->country,
            'phone' => $this->phone,
            'email' => $this->email,
            'website' => $this->website,
            'directions' => $this->directions,
            'parking_info' => $this->parking_info,
            'notes' => $this->notes,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'metadata' => $this->metadata,

            // Formatted address
            'full_address' => $this->formatFullAddress(),
            'short_address' => $this->formatShortAddress(),

            // Coordinates (if available)
            'coordinates' => [
                'latitude' => $this->latitude,
                'longitude' => $this->longitude,
            ],

            // Service relationship
            'service' => $this->whenLoaded('service', function () {
                return [
                    'id' => $this->service->id,
                    'name' => $this->service->name,
                ];
            }),

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
     * Format the full address
     */
    private function formatFullAddress(): string
    {
        $parts = array_filter([
            $this->address,
            $this->city,
            $this->state,
            $this->postcode,
            $this->country,
        ]);

        return implode(', ', $parts);
    }

    /**
     * Format a short address
     */
    private function formatShortAddress(): string
    {
        $parts = array_filter([
            $this->city,
            $this->postcode,
        ]);

        return implode(', ', $parts);
    }
}
