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
            'vendor_id' => $this->vendor_id,
            'name' => $this->name,
            'description' => $this->description,
            'short_description' => $this->short_description,
            'category' => $this->category,
            'category_display' => $this->category_display_name,
            'category_icon' => $this->category_icon,

            // Pricing information
            'base_price' => $this->base_price,
            'formatted_price' => $this->formatted_price,
            'duration_minutes' => $this->duration_minutes,
            'formatted_duration' => $this->formatted_duration,

            // Availability status
            'is_active' => $this->is_active,
            'is_bookable' => $this->is_bookable,
            'availability_status' => [
                'is_available' => $this->isAvailableForBooking(),
                'has_locations' => $this->hasActiveLocations(),
                'has_availability_windows' => $this->hasAvailabilityWindows(),
                'next_available_date' => $this->getNextAvailableDate()?->format('Y-m-d'),
            ],

            // Consultation requirements
            'requires_consultation' => $this->requires_consultation,
            'consultation_duration_minutes' => $this->consultation_duration_minutes,

            // Deposit requirements
            'requires_deposit' => $this->requires_deposit,
            'deposit_percentage' => $this->deposit_percentage,
            'deposit_amount' => $this->deposit_amount,
            'formatted_deposit_amount' => $this->formatted_deposit_amount,

            // Booking constraints
            'min_advance_booking_hours' => $this->min_advance_booking_hours,
            'max_advance_booking_days' => $this->max_advance_booking_days,
            'buffer_minutes' => $this->buffer_minutes,

            // Business rules
            'cancellation_policy' => $this->cancellation_policy,
            'terms_and_conditions' => $this->terms_and_conditions,
            'preparation_notes' => $this->preparation_notes,

            // Service characteristics
            'service_type' => [
                'is_mobile' => $this->isMobileService(),
                'is_virtual' => $this->isVirtualService(),
                'supports_venues' => $this->supportsVenues(),
                'estimated_setup_time' => $this->getEstimatedSetupTime(),
            ],

            // Organization
            'sort_order' => $this->sort_order,
            'metadata' => $this->metadata,

            // Vendor relationship
            'vendor' => $this->whenLoaded('vendor', function () {
                return [
                    'id' => $this->vendor->id,
                    'name' => $this->vendor->name,
                    'email' => $this->vendor->email,
                    'phone' => $this->vendor->phone ?? null,
                ];
            }),

            // Service locations
            'locations' => ServiceLocationResource::collection($this->whenLoaded('serviceLocations')),
            'location_count' => $this->whenLoaded('serviceLocations', fn() => $this->serviceLocations->count()),

            // Add-ons
            'add_ons' => ServiceAddOnResource::collection($this->whenLoaded('addOns')),
            'add_ons_count' => $this->whenLoaded('addOns', fn() => $this->addOns->count()),

            // Availability windows
            'availability_windows' => ServiceAvailabilityWindowResource::collection($this->whenLoaded('availabilityWindows')),
            'availability_windows_count' => $this->whenLoaded('availabilityWindows', fn() => $this->availabilityWindows->count()),

            // Service packages
            'packages' => ServicePackageResource::collection($this->whenLoaded('packages')),
            'packages_count' => $this->whenLoaded('packages', fn() => $this->packages->count()),

            // Booking statistics (when requested)
            'booking_stats' => $this->when($this->relationLoaded('bookings') || isset($this->total_bookings_count), function () {
                return [
                    'total_bookings' => $this->total_bookings_count ?? $this->getTotalBookingsCount(),
                    'completed_bookings' => $this->completed_bookings_count ?? $this->getCompletedBookingsCount(),
                    'total_revenue' => $this->getTotalRevenue(),
                    'formatted_total_revenue' => $this->formatted_total_revenue,
                    'average_rating' => $this->getAverageRating(),
                    'has_active_bookings' => $this->hasActiveBookings(),
                    'has_future_bookings' => $this->hasFutureBookings(),
                ];
            }),

            // Business constraints
            'business_rules' => [
                'can_add_more_locations' => $this->canAddMoreLocations(),
                'can_add_more_addons' => $this->canAddMoreAddOns(),
                'requires_advance_booking' => $this->requiresAdvanceBooking(),
                'has_booking_limit' => $this->hasBookingLimit(),
            ],

            // Media/images
            'images' => $this->when($this->relationLoaded('media'), function () {
                return $this->getMedia('images')->map(function ($media) {
                    return [
                        'id' => $media->id,
                        'url' => $media->getUrl(),
                        'thumb_url' => $media->hasGeneratedConversion('thumb') ? $media->getUrl('thumb') : $media->getUrl(),
                        'alt' => $media->getCustomProperty('alt', $this->name),
                        'size' => $media->size,
                        'mime_type' => $media->mime_type,
                    ];
                });
            }),

            'gallery' => $this->when($this->relationLoaded('media'), function () {
                return $this->getMedia('gallery')->map(function ($media) {
                    return [
                        'id' => $media->id,
                        'url' => $media->getUrl(),
                        'thumb_url' => $media->hasGeneratedConversion('thumb') ? $media->getUrl('thumb') : $media->getUrl(),
                        'alt' => $media->getCustomProperty('alt', $this->name),
                        'size' => $media->size,
                        'mime_type' => $media->mime_type,
                    ];
                });
            }),

            // Timestamps
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
            'created_at_human' => $this->created_at?->diffForHumans(),
            'updated_at_human' => $this->updated_at?->diffForHumans(),

            // API meta information
            'links' => [
                'self' => route('api.v1.services.show', $this->id),
                'bookings' => route('api.v1.services.bookings', $this->id),
                'availability' => route('api.v1.services.availability', $this->id),
                'locations' => route('api.v1.services.locations', $this->id),
                'add_ons' => route('api.v1.services.add-ons', $this->id),
            ],
        ];
    }

    /**
     * Get additional data when including full details
     */
    public function withFullDetails(): array
    {
        return array_merge($this->toArray(request()), [
            // Extended availability information
            'detailed_availability' => [
                'next_7_days' => $this->getAvailableSlots(now(), now()->addDays(7)),
                'booking_windows' => $this->availabilityWindows->map(function ($window) {
                    return [
                        'day_of_week' => $window->day_of_week,
                        'day_name' => $window->getDayName(),
                        'start_time' => $window->start_time,
                        'end_time' => $window->end_time,
                        'max_bookings' => $window->max_bookings,
                        'is_active' => $window->is_active,
                    ];
                }),
            ],

            // Pricing breakdown
            'pricing_details' => [
                'base_price_breakdown' => [
                    'service_fee' => $this->base_price,
                    'consultation_fee' => $this->consultation_duration_minutes ? 0 : null, // Usually free
                    'travel_surcharge' => $this->isMobileService() ? 'varies_by_location' : null,
                ],
                'deposit_info' => $this->requires_deposit ? [
                    'amount' => $this->getDepositAmountAttribute(),
                    'percentage' => $this->deposit_percentage,
                    'due_at_booking' => true,
                    'refundable_until' => '48_hours_before',
                ] : null,
                'payment_terms' => [
                    'deposit_required' => $this->requires_deposit,
                    'remaining_due' => $this->requires_deposit ? 'on_completion' : 'on_booking',
                    'accepted_methods' => ['card', 'bank_transfer'],
                ],
            ],

            // Service preparation requirements
            'preparation_requirements' => [
                'client_preparation' => $this->preparation_notes,
                'advance_notice_required' => $this->min_advance_booking_hours ? "{$this->min_advance_booking_hours} hours" : "Same day OK",
                'consultation_required' => $this->requires_consultation,
                'site_visit_may_be_needed' => $this->isMobileService(),
            ],

            // Operational details
            'operational_info' => [
                'setup_time' => $this->getEstimatedSetupTime(),
                'breakdown_time' => $this->buffer_minutes,
                'service_duration' => $this->duration_minutes,
                'total_time_commitment' => $this->duration_minutes + ($this->buffer_minutes * 2),
                'weather_dependent' => $this->supportsVenues() && $this->serviceLocations->contains('type', 'outdoor'),
            ],
        ]);
    }
}
