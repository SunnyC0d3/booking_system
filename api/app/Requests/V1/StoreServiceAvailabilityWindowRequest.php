<?php

namespace App\Requests\V1;

class StoreServiceAvailabilityWindowRequest extends BaseFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->hasPermission('create_service_availability');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'service_location_id' => 'nullable|exists:service_locations,id',
            'type' => 'required|in:regular,exception,special_hours,blocked',
            'pattern' => 'required|in:weekly,daily,date_range,specific_date',
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',

            // Day and date configuration
            'day_of_week' => 'nullable|integer|min:0|max:6',
            'start_date' => 'nullable|date|after_or_equal:today',
            'end_date' => 'nullable|date|after:start_date',

            // Time configuration
            'start_time' => 'required|date_format:H:i:s',
            'end_time' => 'required|date_format:H:i:s|after:start_time',

            // Capacity and booking rules
            'max_bookings' => 'required|integer|min:1|max:50',
            'slot_duration_minutes' => 'nullable|integer|min:15|max:480',
            'break_duration_minutes' => 'nullable|integer|min:0|max:120',
            'booking_buffer_minutes' => 'nullable|integer|min:0|max:60',

            // Advance booking constraints
            'min_advance_booking_hours' => 'nullable|integer|min:1|max:8760',
            'max_advance_booking_days' => 'nullable|integer|min:1|max:365',

            // Status and availability
            'is_active' => 'boolean',
            'is_bookable' => 'boolean',

            // Pricing modifications
            'price_modifier' => 'nullable|integer|min:-10000|max:10000',
            'price_modifier_type' => 'nullable|in:fixed,percentage',

            // Additional configuration
            'metadata' => 'nullable|array',
            'requires_confirmation' => 'boolean',
            'auto_confirm_bookings' => 'boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'type.required' => 'Availability window type is required.',
            'type.in' => 'Invalid availability window type selected.',
            'pattern.required' => 'Availability pattern is required.',
            'pattern.in' => 'Invalid availability pattern selected.',
            'start_time.required' => 'Start time is required.',
            'start_time.date_format' => 'Start time must be in HH:MM:SS format.',
            'end_time.required' => 'End time is required.',
            'end_time.date_format' => 'End time must be in HH:MM:SS format.',
            'end_time.after' => 'End time must be after start time.',
            'max_bookings.required' => 'Maximum bookings capacity is required.',
            'max_bookings.min' => 'Maximum bookings must be at least 1.',
            'max_bookings.max' => 'Maximum bookings cannot exceed 50.',
            'day_of_week.min' => 'Day of week must be between 0 (Sunday) and 6 (Saturday).',
            'day_of_week.max' => 'Day of week must be between 0 (Sunday) and 6 (Saturday).',
            'start_date.after_or_equal' => 'Start date cannot be in the past.',
            'end_date.after' => 'End date must be after start date.',
            'slot_duration_minutes.min' => 'Slot duration must be at least 15 minutes.',
            'slot_duration_minutes.max' => 'Slot duration cannot exceed 8 hours.',
            'min_advance_booking_hours.min' => 'Minimum advance booking must be at least 1 hour.',
            'min_advance_booking_hours.max' => 'Minimum advance booking cannot exceed 1 year.',
            'max_advance_booking_days.min' => 'Maximum advance booking must be at least 1 day.',
            'max_advance_booking_days.max' => 'Maximum advance booking cannot exceed 1 year.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validate pattern-specific requirements
            $this->validatePatternRequirements($validator);

            // Validate time constraints
            $this->validateTimeConstraints($validator);

            // Validate pricing modifier
            $this->validatePricingModifier($validator);

            // Validate advance booking constraints
            $this->validateAdvanceBookingConstraints($validator);

            // Validate location relationship
            $this->validateLocationRelationship($validator);
        });
    }

    /**
     * Validate pattern-specific requirements
     */
    private function validatePatternRequirements($validator): void
    {
        $pattern = $this->input('pattern');
        $dayOfWeek = $this->input('day_of_week');
        $startDate = $this->input('start_date');
        $endDate = $this->input('end_date');

        switch ($pattern) {
            case 'weekly':
                if (is_null($dayOfWeek)) {
                    $validator->errors()->add('day_of_week', 'Day of week is required for weekly patterns.');
                }
                break;

            case 'date_range':
                if (is_null($startDate) || is_null($endDate)) {
                    $validator->errors()->add('start_date', 'Start and end dates are required for date range patterns.');
                    $validator->errors()->add('end_date', 'Start and end dates are required for date range patterns.');
                }
                break;

            case 'specific_date':
                if (is_null($startDate)) {
                    $validator->errors()->add('start_date', 'Start date is required for specific date patterns.');
                }
                if ($endDate && $startDate !== $endDate) {
                    $validator->errors()->add('end_date', 'End date must match start date for specific date patterns.');
                }
                break;
        }
    }

    /**
     * Validate time constraints
     */
    private function validateTimeConstraints($validator): void
    {
        $startTime = $this->input('start_time');
        $endTime = $this->input('end_time');
        $slotDuration = $this->input('slot_duration_minutes');
        $breakDuration = $this->input('break_duration_minutes', 0);

        if ($startTime && $endTime) {
            $start = \Carbon\Carbon::createFromFormat('H:i:s', $startTime);
            $end = \Carbon\Carbon::createFromFormat('H:i:s', $endTime);

            $totalMinutes = $end->diffInMinutes($start);

            // Check if the window is long enough for at least one slot
            $minimumRequired = $slotDuration + $breakDuration;
            if ($slotDuration && $totalMinutes < $minimumRequired) {
                $validator->errors()->add('slot_duration_minutes',
                    "Time window is too short for the specified slot duration and break time. Minimum required: {$minimumRequired} minutes.");
            }

            // Validate reasonable time windows (not longer than 24 hours)
            if ($totalMinutes > 1440) { // 24 hours
                $validator->errors()->add('end_time', 'Availability window cannot exceed 24 hours.');
            }
        }
    }

    /**
     * Validate pricing modifier
     */
    private function validatePricingModifier($validator): void
    {
        $modifier = $this->input('price_modifier');
        $modifierType = $this->input('price_modifier_type');

        if (!is_null($modifier) && is_null($modifierType)) {
            $validator->errors()->add('price_modifier_type', 'Price modifier type is required when price modifier is set.');
        }

        if ($modifierType === 'percentage' && $modifier) {
            if ($modifier < -100 || $modifier > 1000) {
                $validator->errors()->add('price_modifier', 'Percentage modifier must be between -100% and 1000%.');
            }
        }
    }

    /**
     * Validate advance booking constraints
     */
    private function validateAdvanceBookingConstraints($validator): void
    {
        $minHours = $this->input('min_advance_booking_hours');
        $maxDays = $this->input('max_advance_booking_days');

        if ($minHours && $maxDays) {
            $maxHours = $maxDays * 24;
            if ($minHours > $maxHours) {
                $validator->errors()->add('min_advance_booking_hours',
                    'Minimum advance booking cannot exceed maximum advance booking period.');
            }
        }
    }

    /**
     * Validate location relationship
     */
    private function validateLocationRelationship($validator): void
    {
        $locationId = $this->input('service_location_id');

        if ($locationId) {
            $serviceId = $this->route('service')->id;
            $location = \App\Models\ServiceLocation::where('id', $locationId)
                ->where('service_id', $serviceId)
                ->first();

            if (!$location) {
                $validator->errors()->add('service_location_id',
                    'The selected location does not belong to this service.');
            }
        }
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set default values
        if (!$this->has('is_active')) {
            $this->merge(['is_active' => true]);
        }

        if (!$this->has('is_bookable')) {
            $this->merge(['is_bookable' => true]);
        }

        if (!$this->has('requires_confirmation')) {
            $this->merge(['requires_confirmation' => false]);
        }

        if (!$this->has('auto_confirm_bookings')) {
            $this->merge(['auto_confirm_bookings' => true]);
        }

        // Set slot duration to service duration if not provided
        if (!$this->has('slot_duration_minutes')) {
            $service = $this->route('service');
            if ($service) {
                $this->merge(['slot_duration_minutes' => $service->duration_minutes]);
            }
        }

        // Convert boolean fields
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'is_bookable' => $this->boolean('is_bookable'),
            'requires_confirmation' => $this->boolean('requires_confirmation'),
            'auto_confirm_bookings' => $this->boolean('auto_confirm_bookings'),
        ]);
    }
}
