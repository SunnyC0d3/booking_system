<?php

namespace App\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'short_description' => $this->short_description,
            'category' => $this->category,
            'base_price' => $this->base_price,
            'formatted_price' => '£' . number_format($this->base_price / 100, 2),
            'duration_minutes' => $this->duration_minutes,
            'formatted_duration' => $this->formatDuration($this->duration_minutes),
            'is_active' => $this->is_active,
            'is_bookable' => $this->is_bookable,
            'requires_consultation' => $this->requires_consultation,
            'consultation_duration_minutes' => $this->consultation_duration_minutes,
            'formatted_consultation_duration' => $this->consultation_duration_minutes
                ? $this->formatDuration($this->consultation_duration_minutes)
                : null,
            'min_advance_booking_hours' => $this->min_advance_booking_hours,
            'max_advance_booking_days' => $this->max_advance_booking_days,
            'cancellation_policy' => $this->cancellation_policy,
            'terms_and_conditions' => $this->terms_and_conditions,
            'preparation_notes' => $this->preparation_notes,
            'sort_order' => $this->sort_order,
            'metadata' => $this->metadata,

            // Relationships
            'vendor' => $this->whenLoaded('vendor', function () {
                return [
                    'id' => $this->vendor->id,
                    'name' => $this->vendor->name,
                    'email' => $this->vendor->email,
                ];
            }),

            'locations' => $this->whenLoaded('serviceLocations', function () {
                return ServiceLocationResource::collection($this->serviceLocations);
            }),

            'add_ons' => $this->whenLoaded('addOns', function () {
                return ServiceAddOnResource::collection($this->addOns);
            }),

            'availability_windows' => $this->whenLoaded('availabilityWindows', function () {
                return ServiceAvailabilityWindowResource::collection($this->availabilityWindows);
            }),

            'images' => $this->whenLoaded('media', function () {
                return $this->media->where('collection_name', 'images')->map(function ($media) {
                    return [
                        'id' => $media->id,
                        'name' => $media->name,
                        'file_name' => $media->file_name,
                        'mime_type' => $media->mime_type,
                        'size' => $media->size,
                        'url' => $media->getUrl(),
                        'thumbnail_url' => $media->hasGeneratedConversion('thumb')
                            ? $media->getUrl('thumb')
                            : $media->getUrl(),
                    ];
                });
            }),

            // Statistics (when loaded)
            'statistics' => $this->when(
                isset($this->total_bookings_count) ||
                isset($this->completed_bookings_count) ||
                isset($this->pending_bookings_count),
                function () {
                    return [
                        'total_bookings' => $this->total_bookings_count ?? 0,
                        'completed_bookings' => $this->completed_bookings_count ?? 0,
                        'pending_bookings' => $this->pending_bookings_count ?? 0,
                        'cancelled_bookings' => $this->cancelled_bookings_count ?? 0,
                        'completion_rate' => $this->total_bookings_count > 0
                            ? round(($this->completed_bookings_count / $this->total_bookings_count) * 100, 1)
                            : 0,
                    ];
                }
            ),

            // Recent bookings (when loaded)
            'recent_bookings' => $this->whenLoaded('bookings', function () {
                return BookingResource::collection($this->bookings);
            }),

            // Pricing information
            'pricing' => [
                'base_price_pence' => $this->base_price,
                'base_price_pounds' => $this->base_price / 100,
                'formatted_price' => '£' . number_format($this->base_price / 100, 2),
                'currency' => 'GBP',
            ],

            // Booking constraints
            'booking_constraints' => [
                'min_advance_hours' => $this->min_advance_booking_hours,
                'max_advance_days' => $this->max_advance_booking_days,
                'requires_consultation' => $this->requires_consultation,
                'consultation_duration' => $this->consultation_duration_minutes,
                'is_bookable' => $this->is_bookable && $this->is_active,
            ],

            // Availability info
            'availability' => [
                'is_available' => $this->is_active && $this->is_bookable,
                'has_locations' => $this->serviceLocations_count > 0 || $this->serviceLocations->count() > 0,
                'locations_count' => $this->serviceLocations_count ?? $this->serviceLocations->count(),
                'add_ons_count' => $this->addOns_count ?? $this->addOns->count(),
            ],

            // Timestamps
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'created_at_human' => $this->created_at?->diffForHumans(),
            'updated_at_human' => $this->updated_at?->diffForHumans(),

            // Admin-only fields
            $this->mergeWhen($this->shouldShowAdminFields($request), [
                'vendor_id' => $this->vendor_id,
                'total_revenue' => $this->when(isset($this->total_revenue), function () {
                    return [
                        'amount' => $this->total_revenue,
                        'formatted' => '£' . number_format($this->total_revenue / 100, 2),
                    ];
                }),
            ]),
        ];
    }

    /**
     * Format duration in minutes to human readable format
     */
    private function formatDuration(int $minutes): string
    {
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

    /**
     * Determine if admin fields should be shown
     */
    private function shouldShowAdminFields(Request $request): bool
    {
        $user = $request->user();

        if (!$user) {
            return false;
        }

        // Show admin fields if user has admin permissions or owns this service
        return $user->hasPermission('view_all_services') ||
            ($user->hasPermission('view_services') && $this->vendor_id === $user->id);
    }

    /**
     * Get additional data to be added to the response
     */
    public function with(Request $request): array
    {
        return [
            'meta' => [
                'is_admin_view' => $this->shouldShowAdminFields($request),
                'currency' => 'GBP',
                'duration_unit' => 'minutes',
            ],
        ];
    }
}
