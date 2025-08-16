<?php

namespace App\Requests\V1;

class StoreServicePackageRequest extends BaseFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->hasPermission('create_service_packages');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'required|string|max:5000',
            'short_description' => 'nullable|string|max:500',
            'total_price' => 'required|integer|min:0|max:99999999', // in pence
            'discount_amount' => 'nullable|integer|min:0|max:99999999', // in pence
            'discount_percentage' => 'nullable|numeric|min:0|max:100',
            'is_active' => 'boolean',
            'requires_consultation' => 'boolean',
            'consultation_duration_minutes' => 'nullable|integer|min:15|max:120',
            'max_advance_booking_days' => 'nullable|integer|min:1|max:365',
            'min_advance_booking_hours' => 'nullable|integer|min:1|max:8760',
            'cancellation_policy' => 'nullable|string|max:1000',
            'terms_and_conditions' => 'nullable|string|max:2000',
            'sort_order' => 'nullable|integer|min:0|max:9999',
            'metadata' => 'nullable|array',

            // Services in the package
            'services' => 'required|array|min:1|max:10',
            'services.*.service_id' => 'required|exists:services,id',
            'services.*.quantity' => 'nullable|integer|min:1|max:10',
            'services.*.order' => 'nullable|integer|min:0|max:100',
            'services.*.is_optional' => 'nullable|boolean',
            'services.*.notes' => 'nullable|string|max:500',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Package name is required.',
            'name.max' => 'Package name cannot exceed 255 characters.',
            'description.required' => 'Package description is required.',
            'description.max' => 'Description cannot exceed 5000 characters.',
            'total_price.required' => 'Total price is required.',
            'total_price.integer' => 'Total price must be a valid amount in pence.',
            'total_price.min' => 'Total price cannot be negative.',
            'discount_percentage.max' => 'Discount percentage cannot exceed 100%.',
            'services.required' => 'At least one service must be included in the package.',
            'services.min' => 'Package must include at least one service.',
            'services.max' => 'Package cannot include more than 10 services.',
            'services.*.service_id.required' => 'Service ID is required for each service.',
            'services.*.service_id.exists' => 'One or more selected services do not exist.',
            'services.*.quantity.min' => 'Service quantity must be at least 1.',
            'services.*.quantity.max' => 'Service quantity cannot exceed 10.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validate that services exist and are active
            if ($this->has('services')) {
                $serviceIds = collect($this->services)->pluck('service_id')->unique();
                $activeServices = \App\Models\Service::whereIn('id', $serviceIds)
                    ->where('is_active', true)
                    ->pluck('id');

                $inactiveServices = $serviceIds->diff($activeServices);
                if ($inactiveServices->isNotEmpty()) {
                    $validator->errors()->add('services', 'Cannot include inactive services in package: ' . $inactiveServices->implode(', '));
                }
            }

            // Validate discount logic
            if ($this->discount_amount && $this->discount_percentage) {
                $validator->errors()->add('discount_amount', 'Cannot specify both discount amount and percentage. Choose one.');
            }

            // If discount percentage is provided, validate it makes sense
            if ($this->discount_percentage && $this->total_price) {
                $calculatedDiscount = ($this->total_price * $this->discount_percentage) / 100;
                if ($calculatedDiscount >= $this->total_price) {
                    $validator->errors()->add('discount_percentage', 'Discount cannot be equal to or greater than the total price.');
                }
            }

            // If discount amount is provided, validate it makes sense
            if ($this->discount_amount && $this->total_price) {
                if ($this->discount_amount >= $this->total_price) {
                    $validator->errors()->add('discount_amount', 'Discount amount cannot be equal to or greater than the total price.');
                }
            }

            // Validate consultation requirements
            if ($this->requires_consultation && !$this->consultation_duration_minutes) {
                $validator->errors()->add('consultation_duration_minutes', 'Consultation duration is required when consultation is enabled.');
            }

            // Validate advance booking constraints
            if ($this->min_advance_booking_hours && $this->max_advance_booking_days) {
                $maxHours = $this->max_advance_booking_days * 24;
                if ($this->min_advance_booking_hours > $maxHours) {
                    $validator->errors()->add('min_advance_booking_hours', 'Minimum advance booking cannot exceed maximum advance booking period.');
                }
            }

            // Check for duplicate services
            if ($this->has('services')) {
                $serviceIds = collect($this->services)->pluck('service_id');
                $duplicates = $serviceIds->duplicates();
                if ($duplicates->isNotEmpty()) {
                    $validator->errors()->add('services', 'Duplicate services are not allowed in a package.');
                }
            }
        });
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convert price from pounds to pence if needed
        if ($this->has('total_price') && is_numeric($this->total_price)) {
            if (str_contains($this->total_price, '.') || $this->total_price < 100) {
                $this->merge([
                    'total_price' => (int) round($this->total_price * 100)
                ]);
            }
        }

        // Convert discount amount from pounds to pence if needed
        if ($this->has('discount_amount') && is_numeric($this->discount_amount)) {
            if (str_contains($this->discount_amount, '.') || $this->discount_amount < 100) {
                $this->merge([
                    'discount_amount' => (int) round($this->discount_amount * 100)
                ]);
            }
        }

        // Set default values
        $this->merge([
            'is_active' => $this->boolean('is_active', true),
            'requires_consultation' => $this->boolean('requires_consultation', false),
            'sort_order' => $this->input('sort_order', 0),
        ]);

        // Set default values for services
        if ($this->has('services')) {
            $services = collect($this->services)->map(function ($service, $index) {
                return array_merge($service, [
                    'quantity' => $service['quantity'] ?? 1,
                    'order' => $service['order'] ?? $index,
                    'is_optional' => isset($service['is_optional']) ? (bool) $service['is_optional'] : false,
                ]);
            })->toArray();

            $this->merge(['services' => $services]);
        }
    }
}
