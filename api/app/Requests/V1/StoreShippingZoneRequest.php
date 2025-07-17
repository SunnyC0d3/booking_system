<?php

namespace App\Requests\V1;

class StoreShippingZoneRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:shipping_zones,name'],
            'description' => ['nullable', 'string', 'max:1000'],
            'countries' => ['required', 'array', 'min:1'],
            'countries.*' => ['string', 'size:2', 'regex:/^[A-Z]{2}$/'],
            'postcodes' => ['nullable', 'array'],
            'postcodes.*' => ['string', 'max:50'],
            'excluded_postcodes' => ['nullable', 'array'],
            'excluded_postcodes.*' => ['string', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Shipping zone name is required.',
            'name.unique' => 'A shipping zone with this name already exists.',
            'countries.required' => 'At least one country is required.',
            'countries.min' => 'At least one country must be selected.',
            'countries.*.size' => 'Country codes must be exactly 2 characters.',
            'countries.*.regex' => 'Country codes must be uppercase letters only (e.g., GB, US).',
            'postcodes.*.max' => 'Postcode patterns cannot exceed 50 characters.',
            'excluded_postcodes.*.max' => 'Excluded postcode patterns cannot exceed 50 characters.',
        ];
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        if ($this->has('countries')) {
            $this->merge([
                'countries' => array_map('strtoupper', $this->input('countries', []))
            ]);
        }

        if ($this->has('postcodes')) {
            $postcodes = array_filter(array_map('trim', $this->input('postcodes', [])));
            $this->merge(['postcodes' => array_values($postcodes)]);
        }

        if ($this->has('excluded_postcodes')) {
            $excludedPostcodes = array_filter(array_map('trim', $this->input('excluded_postcodes', [])));
            $this->merge(['excluded_postcodes' => array_values($excludedPostcodes)]);
        }

        $this->merge([
            'is_active' => $this->boolean('is_active', true),
            'sort_order' => $this->input('sort_order', 0),
        ]);
    }
}
