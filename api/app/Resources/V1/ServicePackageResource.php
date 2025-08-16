<?php

namespace App\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServicePackageResource extends JsonResource
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
            'total_price' => $this->total_price,
            'formatted_price' => '£' . number_format($this->total_price / 100, 2),
            'discount_amount' => $this->discount_amount,
            'formatted_discount_amount' => $this->discount_amount
                ? '£' . number_format($this->discount_amount / 100, 2)
                : null,
            'discount_percentage' => $this->discount_percentage,
            'individual_price_total' => $this->individual_price_total,
            'formatted_individual_total' => '£' . number_format($this->individual_price_total / 100, 2),
            'total_duration_minutes' => $this->total_duration_minutes,
            'formatted_duration' => $this->formatDuration($this->total_duration_minutes),
            'is_active' => $this->is_active,
            'requires_consultation' => $this->requires_consultation,
            'consultation_duration_minutes' => $this->consultation_duration_minutes,
            'formatted_consultation_duration' => $this->consultation_duration_minutes
                ? $this->formatDuration($this->consultation_duration_minutes)
                : null,
            'max_advance_booking_days' => $this->max_advance_booking_days,
            'min_advance_booking_hours' => $this->min_advance_booking_hours,
            'cancellation_policy' => $this->cancellation_policy,
            'terms_and_conditions' => $this->terms_and_conditions,
            'sort_order' => $this->sort_order,
            'metadata' => $this->metadata,

            // Calculated fields
            'savings' => [
                'amount' => $this->individual_price_total - $this->total_price,
                'formatted' => '£' . number_format(($this->individual_price_total - $this->total_price) / 100, 2),
                'percentage' => $this->individual_price_total > 0
                    ? round((($this->individual_price_total - $this->total_price) / $this->individual_price_total) * 100, 1)
                    : 0,
            ],

            // Pricing breakdown
            'pricing' => [
                'total_price_pence' => $this->total_price,
                'total_price_pounds' => $this->total_price / 100,
                'formatted_total' => '£' . number_format($this->total_price / 100, 2),
                'individual_total_pence' => $this->individual_price_total,
                'individual_total_pounds' => $this->individual_price_total / 100,
                'formatted_individual_total' => '£' . number_format($this->individual_price_total / 100, 2),
                'discount' => [
                    'amount' => $this->discount_amount,
                    'percentage' => $this->discount_percentage,
                    'formatted_amount' => $this->discount_amount
                        ? '£' . number_format($this->discount_amount / 100, 2)
                        : null,
                ],
                'currency' => 'GBP',
            ],

            // Services in the package
            'services' => $this->whenLoaded('services', function () {
                return $this->services->map(function ($service) {
                    return [
                        'id' => $service->id,
                        'name' => $service->name,
                        'description' => $service->description,
                        'short_description' => $service->short_description,
                        'base_price' => $service->base_price,
                        'formatted_price' => '£' . number_format($service->base_price / 100, 2),
                        'duration_minutes' => $service->duration_minutes,
                        'formatted_duration' => $this->formatDuration($service->duration_minutes),
                        'is_active' => $service->is_active,
                        'is_bookable' => $service->is_bookable,

                        // Pivot data from service_package_items
                        'package_data' => [
                            'quantity' => $service->pivot->quantity,
                            'order' => $service->pivot->order,
                            'is_optional' => $service->pivot->is_optional,
                            'notes' => $service->pivot->notes,
                            'line_total' => $service->base_price * $service->pivot->quantity,
                            'formatted_line_total' => '£' . number_format(($service->base_price * $service->pivot->quantity) / 100, 2),
                            'total_duration' => $service->duration_minutes * $service->pivot->quantity,
                            'formatted_total_duration' => $this->formatDuration($service->duration_minutes * $service->pivot->quantity),
                        ],
                    ];
                });
            }),

            // Statistics (when loaded)
            'statistics' => $this->when(
                isset($this->total_bookings_count) ||
                isset($this->completed_bookings_count),
                function () {
                    return [
                        'total_bookings' => $this->total_bookings_count ?? 0,
                        'completed_bookings' => $this->completed_bookings_count ?? 0,
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

            // Package constraints
            'booking_constraints' => [
                'min_advance_hours' => $this->min_advance_booking_hours,
                'max_advance_days' => $this->max_advance_booking_days,
                'requires_consultation' => $this->requires_consultation,
                'consultation_duration' => $this->consultation_duration_minutes,
                'is_bookable' => $this->is_active,
            ],

            // Service counts
            'service_counts' => $this->whenLoaded('services', function () {
                return [
                    'total_services' => $this->services->count(),
                    'required_services' => $this->services->where('pivot.is_optional', false)->count(),
                    'optional_services' => $this->services->where('pivot.is_optional', true)->count(),
                    'total_quantity' => $this->services->sum('pivot.quantity'),
                ];
            }),

            // Availability info
            'availability' => [
                'is_available' => $this->is_active,
                'all_services_active' => $this->whenLoaded('services', function () {
                    return $this->services->every('is_active');
                }),
                'all_services_bookable' => $this->whenLoaded('services', function () {
                    return $this->services->every('is_bookable');
                }),
            ],

            // Timestamps
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'created_at_human' => $this->created_at?->diffForHumans(),
            'updated_at_human' => $this->updated_at?->diffForHumans(),

            // Admin-only fields
            $this->mergeWhen($this->shouldShowAdminFields($request), [
                'total_revenue' => $this->when(isset($this->total_revenue), function () {
                    return [
                        'amount' => $this->total_revenue,
                        'formatted' => '£' . number_format($this->total_revenue / 100, 2),
                    ];
                }),
                'average_booking_value' => $this->when(
                    isset($this->total_revenue) && isset($this->completed_bookings_count),
                    function () {
                        $avg = $this->completed_bookings_count > 0
                            ? $this->total_revenue / $this->completed_bookings_count
                            : 0;
                        return [
                            'amount' => $avg,
                            'formatted' => '£' . number_format($avg / 100, 2),
                        ];
                    }
                ),
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

        // Show admin fields if user has admin permissions
        return $user->hasPermission('view_service_packages');
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
