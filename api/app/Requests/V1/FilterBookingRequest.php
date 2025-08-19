<?php

namespace App\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class FilterBookingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // User and service filters
            'user_id' => 'nullable|integer|exists:users,id',
            'service_id' => 'nullable|integer|exists:services,id',
            'location_id' => 'nullable|integer|exists:service_locations,id',

            // Status filters
            'status' => 'nullable|string|in:pending,confirmed,in_progress,completed,cancelled,no_show,all',
            'payment_status' => 'nullable|string|in:pending,paid,partially_paid,refunded,failed,all',

            // Date filters
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',

            // Amount filters
            'total_amount' => 'nullable|integer|min:0',
            'total_amount_min' => 'nullable|integer|min:0',
            'total_amount_max' => 'nullable|integer|min:0',

            // Boolean filters
            'requires_consultation' => 'nullable|boolean',
            'consultation_completed' => 'nullable|boolean',

            // Search filters
            'search' => 'nullable|string|max:255',
            'client_name' => 'nullable|string|max:255',
            'client_email' => 'nullable|email|max:255',
            'booking_reference' => 'nullable|string|max:50',

            // Sorting and pagination
            'sort' => 'nullable|string|max:255',
            'per_page' => 'nullable|integer|min:1|max:100',

            // Include relationships
            'include' => 'nullable|string|max:500',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'user_id' => 'User ID',
            'service_id' => 'Service ID',
            'location_id' => 'Location ID',
            'date_from' => 'Start Date',
            'date_to' => 'End Date',
            'total_amount_min' => 'Minimum Amount',
            'total_amount_max' => 'Maximum Amount',
            'client_name' => 'Client Name',
            'client_email' => 'Client Email',
            'booking_reference' => 'Booking Reference',
            'per_page' => 'Items Per Page',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'status.in' => 'Status must be one of: pending, confirmed, in_progress, completed, cancelled, no_show, or all.',
            'payment_status.in' => 'Payment status must be one of: pending, paid, partially_paid, refunded, failed, or all.',
            'date_to.after_or_equal' => 'End date must be on or after the start date.',
            'per_page.max' => 'You can request a maximum of 100 items per page.',
            'user_id.exists' => 'The selected user does not exist.',
            'service_id.exists' => 'The selected service does not exist.',
            'location_id.exists' => 'The selected location does not exist.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Validate amount range
            if ($this->has('total_amount_min') && $this->has('total_amount_max')) {
                if ($this->total_amount_min > $this->total_amount_max) {
                    $validator->errors()->add('total_amount_max', 'Maximum amount must be greater than or equal to minimum amount.');
                }
            }

            // Validate sort parameter format
            if ($this->has('sort')) {
                $this->validateSortParameter($validator);
            }

            // Validate include parameter
            if ($this->has('include')) {
                $this->validateIncludeParameter($validator);
            }
        });
    }

    /**
     * Validate sort parameter format
     */
    private function validateSortParameter($validator): void
    {
        $sortValue = $this->input('sort');
        $sortFields = explode(',', $sortValue);

        $allowedSortFields = [
            'scheduled_at', 'created_at', 'updated_at', 'total_amount',
            'status', 'payment_status', 'client_name', 'booking_reference'
        ];

        foreach ($sortFields as $field) {
            $fieldName = ltrim($field, '-'); // Remove - prefix for desc sorting

            if (!in_array($fieldName, $allowedSortFields)) {
                $validator->errors()->add('sort', "Invalid sort field: {$fieldName}. Allowed fields: " . implode(', ', $allowedSortFields));
                break;
            }
        }
    }

    /**
     * Validate include parameter
     */
    private function validateIncludeParameter($validator): void
    {
        $includeValue = $this->input('include');
        $includes = explode(',', $includeValue);

        $allowedIncludes = [
            'service',
            'serviceLocation',
            'user',
            'bookingAddOns',
            'bookingAddOns.serviceAddOn',
            'consultationBooking'
        ];

        foreach ($includes as $include) {
            if (!in_array(trim($include), $allowedIncludes)) {
                $validator->errors()->add('include', "Invalid include: {$include}. Allowed includes: " . implode(', ', $allowedIncludes));
                break;
            }
        }
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convert string boolean values to actual booleans
        if ($this->has('requires_consultation')) {
            $this->merge([
                'requires_consultation' => filter_var($this->requires_consultation, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
            ]);
        }

        if ($this->has('consultation_completed')) {
            $this->merge([
                'consultation_completed' => filter_var($this->consultation_completed, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
            ]);
        }

        // Ensure amounts are integers (convert from pence if needed)
        foreach (['total_amount', 'total_amount_min', 'total_amount_max'] as $field) {
            if ($this->has($field) && is_numeric($this->input($field))) {
                $this->merge([
                    $field => (int) $this->input($field)
                ]);
            }
        }
    }
}
