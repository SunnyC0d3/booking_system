<?php

namespace App\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'service_id' => ['required', 'exists:services,id'],
            'service_location_id' => ['nullable', 'exists:service_locations,id'],
            'scheduled_at' => ['required', 'date', 'after:now'],
            'duration_minutes' => ['nullable', 'integer', 'min:15', 'max:480'],

            // Client information
            'client_name' => ['nullable', 'string', 'max:255'],
            'client_email' => ['nullable', 'email', 'max:255'],
            'client_phone' => ['nullable', 'string', 'max:20'],

            // Booking details
            'notes' => ['nullable', 'string', 'max:1000'],
            'special_requirements' => ['nullable', 'string', 'max:500'],
            'requires_consultation' => ['nullable', 'boolean'],

            // Add-ons
            'add_ons' => ['nullable', 'array'],
            'add_ons.*.service_add_on_id' => ['required', 'exists:service_add_ons,id'],
            'add_ons.*.quantity' => ['nullable', 'integer', 'min:1', 'max:10'],

            // Admin-only fields
            'user_id' => ['nullable', 'exists:users,id'], // Only for admin creation
            'status' => ['nullable', Rule::in(['pending', 'confirmed'])], // Admin can set initial status

            // Metadata
            'metadata' => ['nullable', 'array'],
            'metadata.source' => ['nullable', 'string', 'max:50'],
            'metadata.event_type' => ['nullable', 'string', 'max:50'],
            'metadata.guest_count' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'service_id.required' => 'Please select a service.',
            'service_id.exists' => 'The selected service is invalid.',
            'service_location_id.exists' => 'The selected location is invalid.',
            'scheduled_at.required' => 'Please select a booking date and time.',
            'scheduled_at.date' => 'Please provide a valid date and time.',
            'scheduled_at.after' => 'Booking time must be in the future.',
            'duration_minutes.min' => 'Minimum booking duration is 15 minutes.',
            'duration_minutes.max' => 'Maximum booking duration is 8 hours.',
            'client_email.email' => 'Please provide a valid email address.',
            'add_ons.*.service_add_on_id.exists' => 'One or more selected add-ons are invalid.',
            'add_ons.*.quantity.min' => 'Add-on quantity must be at least 1.',
            'add_ons.*.quantity.max' => 'Add-on quantity cannot exceed 10.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Set default client information from authenticated user if not provided
        if (auth()->check()) {
            $user = auth()->user();

            if (!$this->has('client_name') || empty($this->client_name)) {
                $this->merge(['client_name' => $user->name]);
            }

            if (!$this->has('client_email') || empty($this->client_email)) {
                $this->merge(['client_email' => $user->email]);
            }
        }
    }
}

// UpdateBookingRequest.php
class UpdateBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Only allow updating certain fields for regular users
            'client_name' => ['sometimes', 'string', 'max:255'],
            'client_email' => ['sometimes', 'email', 'max:255'],
            'client_phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'special_requirements' => ['sometimes', 'nullable', 'string', 'max:500'],

            // Admin-only fields
            'status' => ['sometimes', Rule::in(['pending', 'confirmed', 'in_progress', 'completed', 'cancelled', 'no_show'])],
            'payment_status' => ['sometimes', Rule::in(['pending', 'deposit_paid', 'fully_paid', 'refunded', 'partially_refunded'])],

            // Rescheduling
            'new_scheduled_at' => ['sometimes', 'date', 'after:now'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:500'], // For cancellations

            // Consultation
            'consultation_notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'client_email.email' => 'Please provide a valid email address.',
            'new_scheduled_at.date' => 'Please provide a valid date and time.',
            'new_scheduled_at.after' => 'New booking time must be in the future.',
            'status.in' => 'Invalid booking status.',
            'payment_status.in' => 'Invalid payment status.',
        ];
    }
}

// FilterBookingRequest.php
class FilterBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Filtering
            'status' => ['nullable', Rule::in(['pending', 'confirmed', 'in_progress', 'completed', 'cancelled', 'no_show', 'rescheduled'])],
            'payment_status' => ['nullable', Rule::in(['pending', 'deposit_paid', 'fully_paid', 'refunded', 'partially_refunded'])],
            'service_id' => ['nullable', 'exists:services,id'],
            'user_id' => ['nullable', 'exists:users,id'], // Admin only
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'upcoming_only' => ['nullable', 'boolean'],

            // Search
            'search' => ['nullable', 'string', 'max:255'],

            // Pagination
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],

            // Sorting
            'sort_by' => ['nullable', Rule::in(['scheduled_at', 'created_at', 'total_amount', 'status'])],
            'sort_direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ];
    }

    public function messages(): array
    {
        return [
            'status.in' => 'Invalid status filter.',
            'payment_status.in' => 'Invalid payment status filter.',
            'service_id.exists' => 'Invalid service filter.',
            'user_id.exists' => 'Invalid user filter.',
            'from_date.date' => 'Invalid from date.',
            'to_date.date' => 'Invalid to date.',
            'to_date.after_or_equal' => 'To date must be after or equal to from date.',
            'per_page.min' => 'Minimum 5 items per page.',
            'per_page.max' => 'Maximum 100 items per page.',
            'sort_by.in' => 'Invalid sort field.',
            'sort_direction.in' => 'Sort direction must be asc or desc.',
        ];
    }
}
