<?php

namespace App\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceAvailabilityWindowResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'service_id' => $this->service_id,
            'service_location_id' => $this->service_location_id,
            'day_of_week' => $this->day_of_week,
            'day_name' => $this->getDayName(),
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'formatted_time_range' => $this->formatTimeRange(),
            'is_active' => $this->is_active,
            'is_recurring' => $this->is_recurring,
            'valid_from' => $this->valid_from,
            'valid_until' => $this->valid_until,
            'max_bookings_per_slot' => $this->max_bookings_per_slot,
            'buffer_time_minutes' => $this->buffer_time_minutes,
            'metadata' => $this->metadata,

            // Service relationship
            'service' => $this->whenLoaded('service', function () {
                return [
                    'id' => $this->service->id,
                    'name' => $this->service->name,
                ];
            }),

            // Location relationship
            'location' => $this->whenLoaded('serviceLocation', function () {
                return [
                    'id' => $this->serviceLocation->id,
                    'name' => $this->serviceLocation->name,
                ];
            }),

            // Time information
            'duration_info' => [
                'total_duration_minutes' => $this->getTotalDurationMinutes(),
                'formatted_duration' => $this->formatDuration($this->getTotalDurationMinutes()),
                'buffer_time' => $this->buffer_time_minutes,
                'formatted_buffer' => $this->formatDuration($this->buffer_time_minutes),
            ],

            // Availability status
            'availability_status' => [
                'is_active' => $this->is_active,
                'is_current' => $this->isCurrent(),
                'is_future' => $this->isFuture(),
                'is_expired' => $this->isExpired(),
            ],

            // Timestamps
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'created_at_human' => $this->created_at?->diffForHumans(),
            'updated_at_human' => $this->updated_at?->diffForHumans(),
        ];
    }

    /**
     * Get the day name from day of week number
     */
    private function getDayName(): string
    {
        $days = [
            0 => 'Sunday',
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
        ];

        return $days[$this->day_of_week] ?? 'Unknown';
    }

    /**
     * Format the time range
     */
    private function formatTimeRange(): string
    {
        return $this->start_time . ' - ' . $this->end_time;
    }

    /**
