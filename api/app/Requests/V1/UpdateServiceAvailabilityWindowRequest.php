<?php

namespace App\Requests\V1;

class UpdateServiceAvailabilityWindowRequest extends BaseFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->hasPermission('edit_service_availability');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'service_location_id' => 'nullable|exists:service_locations,id',
            'type' => 'sometimes|in:regular,exception,special_hours,blocked',
            'pattern' => 'sometimes|in:weekly,daily,date_range,specific_date',
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',

            // Day and date configuration
            'day_of_week' => 'nullable|integer|min:0|max:6',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',

            // Time configuration
            'start_time' => 'sometimes|date_format:H:i:s',
            'end_time' => 'sometimes|date_format:H:i:s|after:start_time',

            // Capacity and booking rules
            'max_bookings' => 'sometimes|integer|min:1|max:50',
            'slot_duration_minutes' => 'nullable|integer|min:15|max:480',
            'break_duration_minutes' => 'nullable|integer|min:0|max:120',
            'booking_buffer_minutes' => 'nullable|integer|min:0|max:60',

            // Advance booking constraints
            'min_advance_booking_hours' => 'nullable|integer|min:1|max:8760',
            'max_advance_booking_days' => 'nullable|integer|min:1|max:365',

            // Status and availability
            'is_active' => 'sometimes|boolean',
            'is_bookable' => 'sometimes|boolean',

            // Pricing modifications
            'price_modifier' => 'nullable|integer|min:-10000|max:10000',
            'price_modifier_type' => 'nullable|in:fixed,percentage',

            // Additional configuration
            'metadata' => 'nullable|array',
            'requires_confirmation' => 'sometimes|boolean',
            'auto_confirm_bookings' => 'sometimes|boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'type.in' => 'Invalid availability window type selected.',
            'pattern.in' => 'Invalid availability pattern selected.',
            'start_time.date_format' => 'Start time must be in HH:MM:SS format.',
            'end_time.date_format' => 'End time must be in HH:MM:SS format.',
            'end_time.after' => 'End time must be after start time.',
            'max_bookings.min' => 'Maximum bookings must be at least 1.',
            'max_bookings.max' => 'Maximum bookings cannot exceed 50.',
            'day_of_week.min' => 'Day of week must be between 0 (Sunday) and 6 (Saturday).',
            'day_of_week.max' => 'Day of week must be between 0 (Sunday) and 6 (Saturday).',
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
            $availabilityWindow = $this->route('serviceAvailabilityWindow');

            // Validate pattern-specific requirements
            $this->validatePatternRequirements($validator, $availabilityWindow);

            // Validate time constraints
            $this->validateTimeConstraints($validator, $availabilityWindow);

            // Validate pricing modifier
            $this->validatePricingModifier($validator);

            // Validate advance booking constraints
            $this->validateAdvanceBookingConstraints($validator, $availabilityWindow);

            // Validate location relationship
            $this->validateLocationRelationship($validator);

            // Validate existing bookings impact
            $this->validateExistingBookingsImpact($validator, $availabilityWindow);

            // Validate capacity reduction impact
            $this->validateCapacityReduction($validator, $availabilityWindow);

            // Validate deactivation constraints
            $this->validateDeactivationConstraints($validator, $availabilityWindow);
        });
    }

    /**
     * Validate pattern-specific requirements
     */
    private function validatePatternRequirements($validator, $availabilityWindow): void
    {
        $pattern = $this->input('pattern', $availabilityWindow->pattern);
        $dayOfWeek = $this->input('day_of_week', $availabilityWindow->day_of_week);
        $startDate = $this->input('start_date', $availabilityWindow->start_date?->format('Y-m-d'));
        $endDate = $this->input('end_date', $availabilityWindow->end_date?->format('Y-m-d'));

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
    private function validateTimeConstraints($validator, $availabilityWindow): void
    {
        $startTime = $this->input('start_time', $availabilityWindow->start_time?->format('H:i:s'));
        $endTime = $this->input('end_time', $availabilityWindow->end_time?->format('H:i:s'));
        $slotDuration = $this->input('slot_duration_minutes', $availabilityWindow->slot_duration_minutes);
        $breakDuration = $this->input('break_duration_minutes', $availabilityWindow->break_duration_minutes ?? 0);

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
    private function validateAdvanceBookingConstraints($validator, $availabilityWindow): void
    {
        $minHours = $this->input('min_advance_booking_hours', $availabilityWindow->min_advance_booking_hours);
        $maxDays = $this->input('max_advance_booking_days', $availabilityWindow->max_advance_booking_days);

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
     * Validate impact on existing bookings
     */
    private function validateExistingBookingsImpact($validator, $availabilityWindow): void
    {
        // Check if time changes would affect existing bookings
        $newStartTime = $this->input('start_time');
        $newEndTime = $this->input('end_time');

        if ($newStartTime || $newEndTime) {
            $futureBookingsCount = $this->getFutureBookingsForWindow($availabilityWindow);

            if ($futureBookingsCount > 0) {
                // Get current and new time windows
                $currentStart = $availabilityWindow->start_time;
                $currentEnd = $availabilityWindow->end_time;
                $finalStartTime = $newStartTime ? \Carbon\Carbon::createFromFormat('H:i:s', $newStartTime) : $currentStart;
                $finalEndTime = $newEndTime ? \Carbon\Carbon::createFromFormat('H:i:s', $newEndTime) : $currentEnd;

                // Check if new window is smaller than current
                if ($finalStartTime->gt($currentStart) || $finalEndTime->lt($currentEnd)) {
                    $validator->errors()->add('start_time',
                        "Cannot reduce availability window time range with {$futureBookingsCount} future bookings. Consider creating a new window instead.");
                }
            }
        }
    }

    /**
     * Validate capacity reduction impact
     */
    private function validateCapacityReduction($validator, $availabilityWindow): void
    {
        $newMaxBookings = $this->input('max_bookings');

        if ($newMaxBookings && $newMaxBookings < $availabilityWindow->max_bookings) {
            // Check if reducing capacity would conflict with existing bookings
            $maxCurrentBookings = $this->getMaxCurrentBookingsInWindow($availabilityWindow);

            if ($newMaxBookings < $maxCurrentBookings) {
                $validator->errors()->add('max_bookings',
                    "Cannot reduce capacity below {$maxCurrentBookings} as there are existing bookings that exceed the new limit.");
            }
        }
    }

    /**
     * Validate deactivation constraints
     */
    private function validateDeactivationConstraints($validator, $availabilityWindow): void
    {
        $isActive = $this->input('is_active');

        if ($isActive === false && $availabilityWindow->is_active) {
            // Check if this is the last active window for the service
            $otherActiveWindows = $availabilityWindow->service->availabilityWindows()
                ->where('id', '!=', $availabilityWindow->id)
                ->where('is_active', true)
                ->count();

            if ($otherActiveWindows === 0) {
                $validator->errors()->add('is_active',
                    'Cannot deactivate the last active availability window for this service.');
            }

            // Check for future bookings in this window
            $futureBookingsCount = $this->getFutureBookingsForWindow($availabilityWindow);
            if ($futureBookingsCount > 0) {
                $validator->errors()->add('is_active',
                    "Cannot deactivate window with {$futureBookingsCount} future bookings.");
            }
        }
    }

    /**
     * Get future bookings count for this availability window
     */
    private function getFutureBookingsForWindow($availabilityWindow): int
    {
        // This is a simplified check - in practice, you'd need more complex logic
        // to determine which bookings are specifically using this availability window
        return $availabilityWindow->service->bookings()
            ->whereIn('status', ['pending', 'confirmed'])
            ->where('scheduled_at', '>', now())
            ->when($availabilityWindow->service_location_id, function ($query) use ($availabilityWindow) {
                $query->where('service_location_id', $availabilityWindow->service_location_id);
            })
            ->count();
    }

    /**
     * Get maximum current bookings in any slot for this window
     */
    private function getMaxCurrentBookingsInWindow($availabilityWindow): int
    {
        // This would need more complex logic to check actual slot utilization
        // For now, return a conservative estimate
        return 1;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convert boolean fields if they exist
        $booleanFields = ['is_active', 'is_bookable', 'requires_confirmation', 'auto_confirm_bookings'];

        foreach ($booleanFields as $field) {
            if ($this->has($field)) {
                $this->merge([$field => $this->boolean($field)]);
            }
        }
    }
}
