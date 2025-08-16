<?php

namespace App\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
