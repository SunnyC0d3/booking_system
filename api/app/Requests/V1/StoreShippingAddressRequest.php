<?php

namespace App\Requests\V1;

use App\Constants\AddressTypes;

class StoreShippingAddressRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'in:' . implode(',', AddressTypes::all())],
            'name' => ['required', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'line1' => ['required', 'string', 'max:255'],
            'line2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'county' => ['nullable', 'string', 'max:100'],
            'postcode' => ['required', 'string', 'max:20'],
            'country' => ['required', 'string', 'size:2'],
            'phone' => ['nullable', 'string', 'max:20'],
            'is_default' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'Address type is required.',
            'type.in' => 'Invalid address type. Must be shipping, billing, or both.',
            'name.required' => 'Recipient name is required.',
            'name.max' => 'Recipient name cannot exceed 255 characters.',
            'line1.required' => 'Address line 1 is required.',
            'line1.max' => 'Address line 1 cannot exceed 255 characters.',
            'city.required' => 'City is required.',
            'city.max' => 'City cannot exceed 100 characters.',
            'postcode.required' => 'Postcode is required.',
            'postcode.max' => 'Postcode cannot exceed 20 characters.',
            'country.required' => 'Country is required.',
            'country.size' => 'Country must be a 2-letter country code (e.g., GB, US).',
            'phone.max' => 'Phone number cannot exceed 20 characters.',
        ];
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $this->merge([
            'country' => strtoupper($this->input('country', '')),
            'postcode' => strtoupper($this->input('postcode', '')),
            'is_default' => $this->boolean('is_default', false),
        ]);
    }
}
