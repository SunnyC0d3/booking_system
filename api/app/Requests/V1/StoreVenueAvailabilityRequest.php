<?php

namespace App\Requests\V1;

use App\Requests\V1\BaseFormRequest;
use Carbon\Carbon;

class StoreVenueAvailabilityRequest extends BaseFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->hasPermission('create_venue_availability');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // Window type and scheduling
            'window_type' => 'required|string|in:regular,special_event,maintenance,seasonal',
            'day_of_week' => 'nullable|integer|min:0|max:6', // 0=Sunday, 6=Saturday
            'specific_date' => 'nullable|date|after_or_equal:today',
            'date_range_start' => 'nullable|date|after_or_equal:today',
            'date_range_end' => 'nullable|date|after:date_range_start',

            // Time windows
            'earliest_access' => 'required|date_format:H:i',
            'latest_departure' => 'required|date_format:H:i|after:earliest_access',
            'quiet_hours_start' => 'nullable|date_format:H:i',
            'quiet_hours_end' => 'nullable|date_format:H:i',

            // Capacity and restrictions
            'max_concurrent_events' => 'required|integer|min:1|max:10',
            'restrictions' => 'nullable|array',
            'restrictions.no_children' => 'boolean',
            'restrictions.no_alcohol' => 'boolean',
            'restrictions.no_music' => 'boolean',
            'restrictions.no_photography' => 'boolean',
            'restrictions.min_age' => 'nullable|integer|min:0|max:100',
            'restrictions.max_noise_level' => 'nullable|integer|min:30|max:120', // dB
            'restrictions.required_insurance' => 'boolean',
            'restrictions.security_deposit' => 'nullable|numeric|min:0|max:10000',
            'restrictions.advance_notice_hours' => 'nullable|integer|min:0|max:168', // 1 week max
            'restrictions.cleanup_required' => 'boolean',
            'restrictions.special_equipment_only' => 'boolean',

            // Additional information
            'notes' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'window_type.required' => 'Availability window type is required.',
            'window_type.in' => 'Please select a valid window type (regular, special_event, maintenance, seasonal).',
            'day_of_week.min' => 'Day of week must be between 0 (Sunday) and 6 (Saturday).',
            'day_of_week.max' => 'Day of week must be between 0 (Sunday) and 6 (Saturday).',
            'specific_date.after_or_equal' => 'Specific date cannot be in the past.',
            'date_range_start.after_or_equal' => 'Start date cannot be in the past.',
            'date_range_end.after' => 'End date must be after the start date.',
            'earliest_access.required' => 'Earliest access time is required.',
            'earliest_access.date_format' => 'Earliest access time must be in HH:MM format.',
            'latest_departure.required' => 'Latest departure time is required.',
            'latest_departure.date_format' => 'Latest departure time must be in HH:MM format.',
            'latest_departure.after' => 'Latest departure must be after earliest access time.',
            'quiet_hours_start.date_format' => 'Quiet hours start time must be in HH:MM format.',
            'quiet_hours_end.date_format' => 'Quiet hours end time must be in HH:MM format.',
            'max_concurrent_events.required' => 'Maximum concurrent events is required.',
            'max_concurrent_events.min' => 'At least 1 concurrent event must be allowed.',
            'max_concurrent_events.max' => 'Maximum concurrent events cannot exceed 10.',
            'restrictions.min_age.min' => 'Minimum age cannot be negative.',
            'restrictions.min_age.max' => 'Minimum age cannot exceed 100.',
            'restrictions.max_noise_level.min' => 'Noise level cannot be below 30dB.',
            'restrictions.max_noise_level.max' => 'Noise level cannot exceed 120dB.',
            'restrictions.security_deposit.min' => 'Security deposit cannot be negative.',
            'restrictions.security_deposit.max' => 'Security deposit cannot exceed £10,000.',
            'restrictions.advance_notice_hours.min' => 'Advance notice hours cannot be negative.',
            'restrictions.advance_notice_hours.max' => 'Advance notice cannot exceed 1 week (168 hours).',
            'notes.max' => 'Notes cannot exceed 1000 characters.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $this->validateWindowTypeRequirements($validator);
            $this->validateTimeConsistency($validator);
            $this->validateDateRequirements($validator);
            $this->validateQuietHours($validator);
            $this->validateRestrictionConsistency($validator);
            $this->validateBusinessRules($validator);
        });
    }

    /**
     * Validate window type specific requirements
     */
    private function validateWindowTypeRequirements($validator): void
    {
        $windowType = $this->input('window_type');
        $dayOfWeek = $this->input('day_of_week');
        $specificDate = $this->input('specific_date');
        $dateRangeStart = $this->input('date_range_start');
        $dateRangeEnd = $this->input('date_range_end');

        switch ($windowType) {
            case 'regular':
                // Regular windows should have day_of_week or be date ranges
                if (!$dayOfWeek && !$dateRangeStart) {
                    $validator->errors()->add('day_of_week', 'Regular availability windows require either a day of week or date range.');
                }

                // Regular windows shouldn't have specific dates
                if ($specificDate) {
                    $validator->errors()->add('specific_date', 'Regular windows should use day of week or date ranges, not specific dates.');
                }
                break;

            case 'special_event':
                // Special events should have specific date or date range
                if (!$specificDate && !$dateRangeStart) {
                    $validator->errors()->add('specific_date', 'Special event windows require either a specific date or date range.');
                }
                break;

            case 'maintenance':
                // Maintenance windows should have specific dates or ranges
                if (!$specificDate && !$dateRangeStart) {
                    $validator->errors()->add('specific_date', 'Maintenance windows require either a specific date or date range.');
                }

                // Maintenance windows should not allow events
                if ($this->input('max_concurrent_events', 0) > 0) {
                    $validator->errors()->add('max_concurrent_events', 'Maintenance windows should not allow concurrent events (set to 0).');
                }
                break;

            case 'seasonal':
                // Seasonal windows should have date ranges
                if (!$dateRangeStart || !$dateRangeEnd) {
                    $validator->errors()->add('date_range_start', 'Seasonal windows require both start and end dates.');
                }

                // Seasonal ranges should be at least 7 days
                if ($dateRangeStart && $dateRangeEnd) {
                    $start = Carbon::parse($dateRangeStart);
                    $end = Carbon::parse($dateRangeEnd);
                    if ($start->diffInDays($end) < 7) {
                        $validator->errors()->add('date_range_end', 'Seasonal windows should span at least 7 days.');
                    }
                }
                break;
        }
    }

    /**
     * Validate time consistency
     */
    private function validateTimeConsistency($validator): void
    {
        $earliestAccess = $this->input('earliest_access');
        $latestDeparture = $this->input('latest_departure');

        if (!$earliestAccess || !$latestDeparture) {
            return;
        }

        $start = Carbon::createFromFormat('H:i', $earliestAccess);
        $end = Carbon::createFromFormat('H:i', $latestDeparture);

        // Check for overnight windows (crossing midnight)
        if ($end->lessThan($start)) {
            // This is an overnight window, which is valid
            $duration = $start->diffInHours($end->addDay());
        } else {
            $duration = $start->diffInHours($end);
        }

        // Validate minimum and maximum window duration
        if ($duration < 2) {
            $validator->errors()->add('latest_departure', 'Availability window must be at least 2 hours long.');
        }

        if ($duration > 18) {
            $validator->errors()->add('latest_departure', 'Availability window cannot exceed 18 hours.');
        }

        // Warn about very early or very late times for regular windows
        if ($this->input('window_type') === 'regular') {
            if ($start->hour < 6) {
                $validator->errors()->add('earliest_access', 'Regular windows starting before 6:00 AM may have noise restrictions.');
            }

            if ($end->hour > 22 || ($end->hour === 22 && $end->minute > 0)) {
                $validator->errors()->add('latest_departure', 'Regular windows ending after 10:00 PM may have noise restrictions.');
            }
        }
    }

    /**
     * Validate date requirements
     */
    private function validateDateRequirements($validator): void
    {
        $specificDate = $this->input('specific_date');
        $dateRangeStart = $this->input('date_range_start');
        $dateRangeEnd = $this->input('date_range_end');

        // Cannot have both specific date and date range
        if ($specificDate && ($dateRangeStart || $dateRangeEnd)) {
            $validator->errors()->add('specific_date', 'Cannot specify both a specific date and a date range.');
        }

        // If date range is provided, both start and end are required
        if (($dateRangeStart && !$dateRangeEnd) || (!$dateRangeStart && $dateRangeEnd)) {
            $validator->errors()->add('date_range_start', 'Both start and end dates are required for date ranges.');
        }

        // Validate date range duration
        if ($dateRangeStart && $dateRangeEnd) {
            $start = Carbon::parse($dateRangeStart);
            $end = Carbon::parse($dateRangeEnd);
            $durationDays = $start->diffInDays($end);

            // Maximum date range of 1 year
            if ($durationDays > 365) {
                $validator->errors()->add('date_range_end', 'Date range cannot exceed 1 year.');
            }

            // Warn about very short ranges for seasonal windows
            if ($this->input('window_type') === 'seasonal' && $durationDays < 30) {
                $validator->errors()->add('date_range_end', 'Seasonal windows are typically at least 30 days long.');
            }
        }

        // Validate future dates for balloon arch bookings
        if ($specificDate) {
            $date = Carbon::parse($specificDate);
            if ($date->isToday()) {
                $validator->errors()->add('specific_date', 'Same-day availability windows may not allow sufficient setup time.');
            }
        }
    }

    /**
     * Validate quiet hours
     */
    private function validateQuietHours($validator): void
    {
        $quietStart = $this->input('quiet_hours_start');
        $quietEnd = $this->input('quiet_hours_end');
        $earliestAccess = $this->input('earliest_access');
        $latestDeparture = $this->input('latest_departure');

        // If quiet hours are specified, both start and end are required
        if (($quietStart && !$quietEnd) || (!$quietStart && $quietEnd)) {
            $validator->errors()->add('quiet_hours_start', 'Both quiet hours start and end times are required.');
        }

        if (!$quietStart || !$quietEnd || !$earliestAccess || !$latestDeparture) {
            return;
        }

        $qStart = Carbon::createFromFormat('H:i', $quietStart);
        $qEnd = Carbon::createFromFormat('H:i', $quietEnd);
        $windowStart = Carbon::createFromFormat('H:i', $earliestAccess);
        $windowEnd = Carbon::createFromFormat('H:i', $latestDeparture);

        // Handle overnight quiet hours
        if ($qEnd->lessThan($qStart)) {
            $qEnd->addDay();
        }

        // Handle overnight windows
        if ($windowEnd->lessThan($windowStart)) {
            $windowEnd->addDay();
        }

        // Quiet hours should overlap with availability window
        if ($qStart->greaterThanOrEqualTo($windowEnd) || $qEnd->lessThanOrEqualTo($windowStart)) {
            $validator->errors()->add('quiet_hours_start', 'Quiet hours should overlap with the availability window.');
        }

        // Validate quiet hours duration
        $quietDuration = $qStart->diffInHours($qEnd);
        if ($quietDuration < 1) {
            $validator->errors()->add('quiet_hours_end', 'Quiet hours must be at least 1 hour long.');
        }

        if ($quietDuration > 12) {
            $validator->errors()->add('quiet_hours_end', 'Quiet hours cannot exceed 12 hours.');
        }
    }

    /**
     * Validate restriction consistency
     */
    private function validateRestrictionConsistency($validator): void
    {
        $restrictions = $this->input('restrictions', []);

        // If no music is restricted, noise level restrictions are more important
        if (isset($restrictions['no_music']) && $restrictions['no_music']) {
            if (!isset($restrictions['max_noise_level'])) {
                $validator->errors()->add('restrictions.max_noise_level', 'Please specify maximum noise level when music is prohibited.');
            }
        }

        // If minimum age is set, validate against common balloon arch events
        if (isset($restrictions['min_age']) && $restrictions['min_age'] > 0) {
            $minAge = $restrictions['min_age'];

            if ($minAge > 21) {
                $validator->errors()->add('restrictions.min_age', 'High minimum age restrictions may limit balloon arch events (birthdays, family celebrations).');
            }
        }

        // If security deposit is required, advance notice should also be required
        if (isset($restrictions['security_deposit']) && $restrictions['security_deposit'] > 0) {
            if (!isset($restrictions['advance_notice_hours']) || $restrictions['advance_notice_hours'] < 48) {
                $validator->errors()->add('restrictions.advance_notice_hours', 'At least 48 hours advance notice is recommended when security deposit is required.');
            }
        }

        // If insurance is required, advance notice should be longer
        if (isset($restrictions['required_insurance']) && $restrictions['required_insurance']) {
            if (!isset($restrictions['advance_notice_hours']) || $restrictions['advance_notice_hours'] < 72) {
                $validator->errors()->add('restrictions.advance_notice_hours', 'At least 72 hours advance notice is recommended when insurance is required.');
            }
        }

        // Photography restrictions for balloon arch events
        if (isset($restrictions['no_photography']) && $restrictions['no_photography']) {
            $validator->errors()->add('restrictions.no_photography', 'Warning: Photography restrictions may significantly impact balloon arch events, which are often celebration-focused.');
        }
    }

    /**
     * Validate business rules specific to balloon arch bookings
     */
    private function validateBusinessRules($validator): void
    {
        $windowType = $this->input('window_type');
        $maxEvents = $this->input('max_concurrent_events');
        $earliestAccess = $this->input('earliest_access');
        $latestDeparture = $this->input('latest_departure');
        $restrictions = $this->input('restrictions', []);

        // Balloon arch events typically need setup time
        if ($earliestAccess && $latestDeparture) {
            $setupTime = 60; // minutes
            $breakdownTime = 30; // minutes

            $start = Carbon::createFromFormat('H:i', $earliestAccess);
            $end = Carbon::createFromFormat('H:i', $latestDeparture);

            // Handle overnight windows
            if ($end->lessThan($start)) {
                $end->addDay();
            }

            $totalAvailableMinutes = $start->diffInMinutes($end);
            $minimumEventDuration = 120; // 2 hours
            $requiredTimePerEvent = $minimumEventDuration + $setupTime + $breakdownTime;

            $theoreticalMaxEvents = floor($totalAvailableMinutes / $requiredTimePerEvent);

            if ($maxEvents > $theoreticalMaxEvents) {
                $validator->errors()->add('max_concurrent_events',
                    "Given the setup requirements for balloon arch events, maximum {$theoreticalMaxEvents} concurrent events are realistic for this time window.");
            }
        }

        // Maintenance windows during prime event times
        if ($windowType === 'maintenance') {
            $specificDate = $this->input('specific_date');
            if ($specificDate) {
                $date = Carbon::parse($specificDate);

                // Warn about maintenance on weekends
                if ($date->isWeekend()) {
                    $validator->errors()->add('specific_date', 'Maintenance on weekends may impact prime booking times for balloon arch events.');
                }

                // Warn about maintenance during event season
                $eventSeasonMonths = [5, 6, 7, 8, 12]; // May-Aug, December
                if (in_array($date->month, $eventSeasonMonths)) {
                    $validator->errors()->add('specific_date', 'Consider scheduling maintenance outside peak event season (May-August, December).');
                }
            }
        }

        // Special considerations for outdoor balloon installations
        $serviceLocation = $this->route('location');
        if ($serviceLocation && $serviceLocation->type === 'outdoor') {
            // Weather-related restrictions
            if ($windowType === 'regular' && !isset($restrictions['weather_dependent'])) {
                $validator->errors()->add('restrictions', 'Consider adding weather dependency restrictions for outdoor balloon arch installations.');
            }

            // Wind restrictions
            if (!isset($restrictions['max_wind_speed'])) {
                $validator->errors()->add('restrictions.max_wind_speed', 'Wind speed restrictions are important for outdoor balloon arch safety.');
            }
        }

        // Concurrent events validation for balloon arch logistics
        if ($maxEvents > 1) {
            if (!isset($restrictions['special_equipment_only']) || !$restrictions['special_equipment_only']) {
                $validator->errors()->add('max_concurrent_events', 'Multiple concurrent balloon arch events may require specialized equipment and additional coordination.');
            }
        }
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convert boolean fields
        $booleanFields = ['is_active'];

        foreach ($booleanFields as $field) {
            if ($this->has($field)) {
                $this->merge([$field => $this->boolean($field)]);
            }
        }

        // Convert restriction boolean fields
        $restrictions = $this->input('restrictions', []);
        $restrictionBooleans = [
            'no_children', 'no_alcohol', 'no_music', 'no_photography',
            'required_insurance', 'cleanup_required', 'special_equipment_only'
        ];

        foreach ($restrictionBooleans as $field) {
            if (isset($restrictions[$field])) {
                $restrictions[$field] = filter_var($restrictions[$field], FILTER_VALIDATE_BOOLEAN);
            }
        }

        if (!empty($restrictions)) {
            $this->merge(['restrictions' => $restrictions]);
        }

        // Ensure integer fields are properly converted
        $integerFields = ['day_of_week', 'max_concurrent_events'];

        foreach ($integerFields as $field) {
            if ($this->has($field) && $this->input($field) !== null) {
                $this->merge([$field => (int) $this->input($field)]);
            }
        }

        // Convert restriction numeric fields
        $restrictionNumerics = ['min_age', 'max_noise_level', 'security_deposit', 'advance_notice_hours'];

        foreach ($restrictionNumerics as $field) {
            if (isset($restrictions[$field]) && $restrictions[$field] !== null) {
                if (in_array($field, ['security_deposit'])) {
                    $restrictions[$field] = (float) $restrictions[$field];
                } else {
                    $restrictions[$field] = (int) $restrictions[$field];
                }
            }
        }

        if (!empty($restrictions)) {
            $this->merge(['restrictions' => $restrictions]);
        }
    }
}
