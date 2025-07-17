<?php

namespace App\Resources\V1;

use App\Constants\AddressTypes;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShippingAddressResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'type_label' => AddressTypes::getTypeLabel($this->type),
            'name' => $this->name,
            'company' => $this->company,
            'line1' => $this->line1,
            'line2' => $this->line2,
            'city' => $this->city,
            'county' => $this->county,
            'postcode' => $this->postcode,
            'country' => $this->country,
            'country_name' => $this->getCountryName(),
            'phone' => $this->phone,
            'is_default' => $this->is_default,
            'is_validated' => $this->is_validated,
            'is_uk_address' => $this->isUKAddress(),
            'is_international' => $this->isInternational(),
            'full_address' => $this->getFullAddressAttribute(),
            'formatted_address' => $this->getFormattedAddressAttribute(),
            'normalized_postcode' => $this->getNormalizedPostcode(),
            'needs_validation' => $this->needsValidation(),
            'validation_data' => $this->when($this->is_validated, $this->validation_data),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
