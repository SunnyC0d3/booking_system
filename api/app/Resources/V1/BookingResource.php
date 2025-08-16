<?php

namespace App\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'booking_reference' => $this->booking_reference,
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                ];
            }),
            'service' => $this->whenLoaded('service', function () {
                return [
                    'id' => $this->service->id,
                    'name' => $this->service->name,
                    'description' => $this->service->description,
                    'duration_minutes' => $this->service->duration_minutes,
                    'base_price' => $this->service->base_price,
                    'formatted_base_price' => $this->service->getFormattedPriceAttribute(),
                ];
            }),
            'service_location' => $this->whenLoaded('serviceLocation', function () {
                return $this->serviceLocation ? [
                    'id' => $this->serviceLocation->id,
                    'name' => $this->serviceLocation->name,
                    'type' => $this->serviceLocation->type,
                    'type_display_name' => $this->serviceLocation->getTypeDisplayNameAttribute(),
                    'address' => $this->serviceLocation->getFullAddressAttribute(),
                    'additional_charge' => $this->serviceLocation->additional_charge,
                    'formatted_additional_charge' => $this->serviceLocation->getFormattedAdditionalChargeAttribute(),
                    'travel_time_minutes' => $this->serviceLocation->travel_time_minutes,
                ] : null;
            }),

            // Booking details
            'scheduled_at' => $this->scheduled_at?->toISOString(),
            'ends_at' => $this->ends_at?->toISOString(),
            'formatted_scheduled_at' => $this->scheduled_at?->format('l, F j, Y \a\t g:i A'),
            'formatted_time_range' => $this->scheduled_at && $this->ends_at ?
                $this->scheduled_at->format('g:i A') . ' - ' . $this->ends_at->format('g:i A') : null,
            'duration_minutes' => $this->duration_minutes,
            'formatted_duration' => $this->getFormattedDuration(),

            // Pricing
            'base_price' => $this->base_price,
            'addons_total' => $this->addons_total,
            'total_amount' => $this->total_amount,
            'deposit_amount' => $this->deposit_amount,
            'remaining_amount' => $this->remaining_amount,
            'formatted_base_price' => $this->getFormattedBasePriceAttribute(),
            'formatted_addons_total' => $this->getFormattedAddOnsTotalAttribute(),
            'formatted_total_amount' => $this->getFormattedTotalAmountAttribute(),
            'formatted_deposit_amount' => $this->getFormattedDepositAmountAttribute(),
            'formatted_remaining_amount' => $this->getFormattedRemainingAmountAttribute(),

            // Status
            'status' => $this->status,
            'status_color' => $this->getStatusColorAttribute(),
            'payment_status' => $this->payment_status,
            'payment_status_color' => $this->getPaymentStatusColorAttribute(),

            // Client information
            'client_name' => $this->client_name,
            'client_email' => $this->client_email,
            'client_phone' => $this->client_phone,
            'notes' => $this->notes,
            'special_requirements' => $this->special_requirements,

            // Consultation
            'requires_consultation' => $this->requires_consultation,
            'consultation_completed_at' => $this->consultation_completed_at?->toISOString(),
            'consultation_notes' => $this->consultation_notes,
            'is_consultation_required' => $this->isConsultationRequired(),
            'is_consultation_completed' => $this->isConsultationCompleted(),

            // Cancellation/Rescheduling
            'cancelled_at' => $this->cancelled_at?->toISOString(),
            'cancellation_reason' => $this->cancellation_reason,
            'rescheduled_from_booking_id' => $this->rescheduled_from_booking_id,
            'can_be_cancelled' => $this->canBeCancelled(),
            'can_be_rescheduled' => $this->canBeRescheduled(),

            // Add-ons
            'booking_add_ons' => BookingAddOnResource::collection($this->whenLoaded('bookingAddOns')),

            // Payments
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
            'needs_deposit' => $this->needsDeposit(),
            'is_deposit_paid' => $this->isDepositPaid(),
            'is_fully_paid' => $this->isFullyPaid(),

            // Related bookings
            'rescheduled_from_booking' => $this->whenLoaded('rescheduledFromBooking', function () {
                return $this->rescheduledFromBooking ? [
                    'id' => $this->rescheduledFromBooking->id,
                    'booking_reference' => $this->rescheduledFromBooking->booking_reference,
                    'scheduled_at' => $this->rescheduledFromBooking->scheduled_at?->toISOString(),
                ] : null;
            }),
            'rescheduled_bookings' => $this->whenLoaded('rescheduledBookings', function () {
                return $this->rescheduledBookings->map(function ($booking) {
                    return [
                        'id' => $booking->id,
                        'booking_reference' => $booking->booking_reference,
                        'scheduled_at' => $booking->scheduled_at?->toISOString(),
                        'status' => $booking->status,
                    ];
                });
            }),

            // Metadata
            'metadata' => $this->metadata,

            // Utility flags
            'is_upcoming' => $this->isUpcoming(),
            'is_past' => $this->isPast(),

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
        ];
    }
}

// BookingAddOnResource.php
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

// ServiceLocationResource.php
class ServiceLocationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type,
            'type_display_name' => $this->getTypeDisplayNameAttribute(),
            'type_icon' => $this->getTypeIconAttribute(),

            // Address
            'address_line_1' => $this->address_line_1,
            'address_line_2' => $this->address_line_2,
            'city' => $this->city,
            'county' => $this->county,
            'postcode' => $this->postcode,
            'country' => $this->country,
            'full_address' => $this->getFullAddressAttribute(),

            // Location
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'has_coordinates' => $this->hasCoordinates(),
            'google_maps_url' => $this->getGoogleMapsUrl(),

            // Capacity and charges
            'max_capacity' => $this->max_capacity,
            'travel_time_minutes' => $this->travel_time_minutes,
            'additional_charge' => $this->additional_charge,
            'formatted_additional_charge' => $this->getFormattedAdditionalChargeAttribute(),
            'requires_travel' => $this->requiresTravel(),
            'has_additional_charge' => $this->hasAdditionalCharge(),

            // Virtual details
            'virtual_platform' => $this->when($this->isVirtual(), $this->virtual_platform),
            'virtual_instructions' => $this->when($this->isVirtual(), $this->virtual_instructions),

            // Features
            'equipment_available' => $this->equipment_available,
            'facilities' => $this->facilities,
            'availability_notes' => $this->availability_notes,
            'has_equipment' => $this->hasEquipment(),
            'has_facilities' => $this->hasFacilities(),

            // Status
            'is_active' => $this->is_active,

            // Type checks
            'is_virtual' => $this->isVirtual(),
            'is_client_location' => $this->isClientLocation(),
            'is_business_premises' => $this->isBusinessPremises(),
            'is_outdoor' => $this->isOutdoor(),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

// ServiceAddOnResource.php
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
