<?php

namespace App\Requests\V1;

class StoreServiceRequest extends BaseFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->hasPermission('create_services');
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
            'category' => 'required|string|max:100',
            'base_price' => 'required|integer|min:0|max:99999999', // in pence
            'duration_minutes' => 'required|integer|min:15|max:480', // 15 minutes to 8 hours
            'vendor_id' => 'nullable|exists:users,id',
            'is_active' => 'boolean',
            'is_bookable' => 'boolean',
            'requires_consultation' => 'boolean',
            'consultation_duration_minutes' => 'nullable|integer|min:15|max:120',
            'min_advance_booking_hours' => 'nullable|integer|min:1|max:8760', // up to 1 year
            'max_advance_booking_days' => 'nullable|integer|min:1|max:365',
            'cancellation_policy' => 'nullable|string|max:1000',
            'terms_and_conditions' => 'nullable|string|max:2000',
            'preparation_notes' => 'nullable|string|max:1000',
            'sort_order' => 'nullable|integer|min:0|max:9999',
            'metadata' => 'nullable|array',
            'images' => 'nullable|array|max:10',
            'images.*' => 'image|mimes:jpeg,jpg,png,webp|max:5120', // 5MB max per image
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Service name is required.',
            'name.max' => 'Service name cannot exceed 255 characters.',
            'description.required' => 'Service description is required.',
            'description.max' => 'Description cannot exceed 5000 characters.',
            'category.required' => 'Service category is required.',
            'base_price.required' => 'Base price is required.',
            'base_price.integer' => 'Base price must be a valid amount in pence.',
            'base_price.min' => 'Base price cannot be negative.',
            'duration_minutes.required' => 'Service duration is required.',
            'duration_minutes.min' => 'Service duration must be at least 15 minutes.',
            'duration_minutes.max' => 'Service duration cannot exceed 8 hours.',
            'vendor_id.exists' => 'Selected vendor does not exist.',
            'consultation_duration_minutes.min' => 'Consultation duration must be at least 15 minutes.',
            'consultation_duration_minutes.max' => 'Consultation duration cannot exceed 2 hours.',
            'images.*.image' => 'Each file must be an image.',
            'images.*.mimes' => 'Images must be in JPEG, PNG, or WebP format.',
            'images.*.max' => 'Each image must not exceed 5MB.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // If requires_consultation is true, consultation_duration_minutes should be provided
            if ($this->requires_consultation && !$this->consultation_duration_minutes) {
                $validator->errors()->add('consultation_duration_minutes', 'Consultation duration is required when consultation is enabled.');
            }

            // Validate vendor assignment permissions
            if ($this->has('vendor_id') && !$this->user()->hasPermission('manage_all_services')) {
                $validator->errors()->add('vendor_id', 'You do not have permission to assign vendors.');
            }

            // Validate advance booking constraints
            if ($this->min_advance_booking_hours && $this->max_advance_booking_days) {
                $maxHours = $this->max_advance_booking_days * 24;
                if ($this->min_advance_booking_hours > $maxHours) {
                    $validator->errors()->add('min_advance_booking_hours', 'Minimum advance booking cannot exceed maximum advance booking period.');
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
        if ($this->has('base_price') && is_numeric($this->base_price)) {
            // If the price looks like it's in pounds (has decimal), convert to pence
            if (str_contains($this->base_price, '.') || $this->base_price < 100) {
                $this->merge([
                    'base_price' => (int) round($this->base_price * 100)
                ]);
            }
        }

        // Set default values
        $this->merge([
            'is_active' => $this->boolean('is_active', true),
            'is_bookable' => $this->boolean('is_bookable', true),
            'requires_consultation' => $this->boolean('requires_consultation', false),
            'sort_order' => $this->input('sort_order', 0),
        ]);
    }
}
