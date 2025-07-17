<?php

namespace App\Requests\V1;

class StoreShippingMethodRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'carrier' => ['required', 'string', 'max:100'],
            'service_code' => ['nullable', 'string', 'max:100'],
            'estimated_days_min' => ['required', 'integer', 'min:0', 'max:365'],
            'estimated_days_max' => ['required', 'integer', 'min:0', 'max:365', 'gte:estimated_days_min'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Shipping method name is required.',
            'name.max' => 'Shipping method name cannot exceed 255 characters.',
            'carrier.required' => 'Carrier name is required.',
            'carrier.max' => 'Carrier name cannot exceed 100 characters.',
            'estimated_days_min.required' => 'Minimum estimated delivery days is required.',
            'estimated_days_min.min' => 'Minimum estimated delivery days cannot be negative.',
            'estimated_days_min.max' => 'Minimum estimated delivery days cannot exceed 365.',
            'estimated_days_max.required' => 'Maximum estimated delivery days is required.',
            'estimated_days_max.gte' => 'Maximum delivery days must be greater than or equal to minimum delivery days.',
            'estimated_days_max.max' => 'Maximum estimated delivery days cannot exceed 365.',
        ];
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $this->merge([
            'is_active' => $this->boolean('is_active', true),
            'sort_order' => $this->input('sort_order', 0),
        ]);
    }
}
