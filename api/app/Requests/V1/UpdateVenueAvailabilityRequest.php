<?php

namespace App\Requests\V1;

use App\Requests\V1\BaseFormRequest;
use Carbon\Carbon;

class UpdateVenueAvailabilityRequest extends BaseFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->hasPermission('edit_venue_availability');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // Window type and scheduling (optional for updates)
            'window_type' => 'sometimes|string|in:regular,special_event,maintenance,seasonal',
            'day_of_week' => 'sometimes|nullable|integer|min:0|max:6', // 0=Sunday, 6=Saturday
            'specific_date' => 'sometimes|nullable|date|after_or_equal:today',
            'date_range_start' => 'sometimes|nullable|date|after_or_equal:today',
            'date_range_end' => 'sometimes|nullable|date|after:date_range_start',

            // Time windows
            'earliest_access' => 'sometimes|date_format:H:i',
            'latest_departure' => 'sometimes|date_format:H:i',
            'quiet_hours_start' => 'sometimes|nullable|date_format:H:i',
            'quiet_hours_end' => 'sometimes|nullable|date_format:H:i',

            // Capacity and restrictions
            'max_concurrent_events' => 'sometimes|integer|min:0|max:10',
            'restrictions' => 'sometimes|nullable|array',
            'restrictions.no_children' => 'sometimes|boolean',
            'restrictions.no_alcohol' => 'sometimes|boolean',
            'restrictions.no_music' => 'sometimes|boolean',
            'restrictions.no_photography' => 'sometimes|boolean',
            'restrictions.min_age' => 'sometimes|nullable|integer|min:0|max:100',
            'restrictions.max_noise_level' => 'sometimes|nullable|integer|min:30|max:120', // dB
            'restrictions.required_insurance' => 'sometimes|boolean',
            'restrictions.security_deposit' => 'sometimes|nullable|numeric|min:0|max:10000',
            'restrictions.advance_notice_hours' => 'sometimes|nullable|integer|min:0|max:168', // 1 week max
            'restrictions.cleanup_required' => 'sometimes|boolean',
            'restrictions.special_equipment_only' => 'sometimes|boolean',
            'restrictions.weather_dependent' => 'sometimes|boolean',
            'restrictions.max_wind_speed' => 'sometimes|nullable|integer|min:0|max:50', // mph

            // Additional information
            'notes' => 'sometimes|nullable|string|max:1000',
            'is_active' => 'sometimes|boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'window_type.in' => 'Please select a valid window type (regular, special_event, maintenance, seasonal).',
            'day_of_week.min' => 'Day of week must be between 0 (Sunday) and 6 (Saturday).',
            'day_of_week.max' => 'Day of week must be between 0 (Sunday) and 6 (Saturday).',
            'specific_date.after_or_equal' => 'Specific date cannot be in the past.',
            'date_range_start.after_or_equal' => 'Start date cannot be in the past.',
            'date_range_end.after' => 'End date must be after the start date.',
            'earliest_access.date_format' => 'Earliest access time must be in HH:MM format.',
            'latest_departure.date_format' => 'Latest departure time must be in HH:MM format.',
            'quiet_hours_start.date_format' => 'Quiet hours start time must be in HH:MM format.',
            'quiet_hours_end.date_format' => 'Quiet hours end time must be in HH:MM format.',
            'max_concurrent_events.min' => 'Maximum concurrent events cannot be negative.',
            'max_concurrent_events.max' => 'Maximum concurrent events cannot exceed 10.',
            'restrictions.min_age.min' => 'Minimum age cannot be negative.',
            'restrictions.min_age.max' => 'Minimum age cannot exceed 100.',
            'restrictions.max_noise_level.min' => 'Noise level cannot be below 30dB.',
            'restrictions.max_noise_level.max' => 'Noise level cannot exceed 120dB.',
            'restrictions.security_deposit.min' => 'Security deposit cannot be negative.',
            'restrictions.security_deposit.max' => 'Security deposit cannot exceed £10,000.',
            'restrictions.advance_notice_hours.min' => 'Advance notice hours cannot be negative.',
            'restrictions.advance_notice_hours.max' => 'Advance notice cannot exceed 1 week (168 hours).',
            'restrictions.max_wind_speed.min' => 'Wind speed cannot be negative.',
            'restrictions.max_wind_speed.max' => 'Wind speed cannot exceed 50 mph.',
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
            $this->validateWindowTypeTransition($validator);
            $this->validateExistingBookingImpact($validator);
            $this->validatePartialUpdateConsistency($validator);
        });
    }

    /**
     * Validate window type specific requirements (considering existing data)
     */
    private function validateWindowTypeRequirements($validator): void
    {
        $windowType = $this->input('window_type');

        // Skip validation if window type is not being updated
        if (!$windowType) {
            return;
        }

        $dayOfWeek = $this->input('day_of_week');
        $specificDate = $this->input('specific_date');
        $dateRangeStart = $this->input('date_range_start');
        $dateRangeEnd = $this->input('date_range_end');

        // Get existing values if not being updated
        $existingWindow = $this->route('window');
        if ($existingWindow) {
            $dayOfWeek = $this->has('day_of_week') ? $dayOfWeek : $existingWindow->day_of_week;
            $specificDate = $this->has('specific_date') ? $specificDate : $existingWindow->specific_date;
            $dateRangeStart = $this->has('date_range_start') ? $dateRangeStart : $existingWindow->date_range_start;
            $dateRangeEnd = $this->has('date_range_end') ? $dateRangeEnd : $existingWindow->date_range_end;
        }

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
                $maxEvents = $this->input('max_concurrent_events', $existingWindow?->max_concurrent_events ?? 1);
                if ($maxEvents > 0) {
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
     * Validate time consistency (with existing data context)
     */
    private function validateTimeConsistency($validator): void
    {
        $earliestAccess = $this->input('earliest_access');
        $latestDeparture = $this->input('latest_departure');

        // Get existing values if not being updated
        $existingWindow = $this->route('window');
        if ($existingWindow) {
            $earliestAccess = $this->has('earliest_access') ? $earliestAccess : $existingWindow->earliest_access->format('H:i');
            $latestDeparture = $this->has('latest_departure') ? $latestDeparture : $existingWindow->latest_departure->format('H:i');
        }

        if (!$earliestAccess || !$latestDeparture) {
            return;
        }

        $start = Carbon::createFromFormat('H:i', $earliestAccess);
        $end = Carbon::createFromFormat('H:i', $latestDeparture);

        // Validate that latest_departure is after earliest_access
        if ($this->has('latest_departure') && $this->has('earliest_access')) {
            if ($end->lessThanOrEqualTo($start)) {
                // Check if this is intentionally an overnight window
                $isOvernight = $end->addDay()->greaterThan($start);
                if (!$isOvernight) {
                    $validator->errors()->add('latest_departure', 'Latest departure must be after earliest access time.');
                    return;
                }
            }
        }

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
        $windowType = $this->input('window_type', $existingWindow?->window_type);
        if ($windowType === 'regular') {
            if ($start->hour < 6) {
                $validator->errors()->add('earliest_access', 'Regular windows starting before 6:00 AM may have noise restrictions.');
            }

            if ($end->hour > 22 || ($end->hour === 22 && $end->minute > 0)) {
                $validator->errors()->add('latest_departure', 'Regular windows ending after 10:00 PM may have noise restrictions.');
            }
        }
    }

    /**
     * Validate date requirements (with existing data context)
     */
    private function validateDateRequirements($validator): void
    {
        $specificDate = $this->input('specific_date');
        $dateRangeStart = $this->input('date_range_start');
        $dateRangeEnd = $this->input('date_range_end');

        // Get existing values if not being updated
        $existingWindow = $this->route('window');
        if ($existingWindow) {
            $specificDate = $this->has('specific_date') ? $specificDate : $existingWindow->specific_date;
            $dateRangeStart = $this->has('date_range_start') ? $dateRangeStart : $existingWindow->date_range_start;
            $dateRangeEnd = $this->has('date_range_end') ? $dateRangeEnd : $existingWindow->date_range_end;
        }

        // Cannot have both specific date and date range
        if ($specificDate && ($dateRangeStart || $dateRangeEnd)) {
            $validator->errors()->add('specific_date', 'Cannot specify both a specific date and a date range.');
        }

        // If date range is being updated, both start and end should be provided
        if ($this->hasAny(['date_range_start', 'date_range_end'])) {
            if (($dateRangeStart && !$dateRangeEnd) || (!$dateRangeStart && $dateRangeEnd)) {
                $validator->errors()->add('date_range_start', 'Both start and end dates are required for date ranges.');
            }
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
            $windowType = $this->input('window_type', $existingWindow?->window_type);
            if ($windowType === 'seasonal' && $durationDays < 30) {
                $validator->errors()->add('date_range_end', 'Seasonal windows are typically at least 30 days long.');
            }
        }

        // Validate future dates for balloon arch bookings
        if ($specificDate) {
            $date = Carbon::parse($specificDate);
            if ($date->isPast()) {
                $validator->errors()->add('specific_date', 'Cannot set availability for past dates.');
            }

            if ($date->isToday()) {
                $validator->errors()->add('specific_date', 'Same-day availability windows may not allow sufficient setup time.');
            }
        }
    }

    /**
     * Validate quiet hours (with existing data context)
     */
    private function validateQuietHours($validator): void
    {
        $quietStart = $this->input('quiet_hours_start');
        $quietEnd = $this->input('quiet_hours_end');
        $earliestAccess = $this->input('earliest_access');
        $latestDeparture = $this->input('latest_departure');

        // Get existing values if not being updated
        $existingWindow = $this->route('window');
        if ($existingWindow) {
            $quietStart = $this->has('quiet_hours_start') ? $quietStart : $existingWindow->quiet_hours_start?->format('H:i');
            $quietEnd = $this->has('quiet_hours_end') ? $quietEnd : $existingWindow->quiet_hours_end?->format('H:i');
            $earliestAccess = $this->has('earliest_access') ? $earliestAccess : $existingWindow->earliest_access->format('H:i');
            $latestDeparture = $this->has('latest_departure') ? $latestDeparture : $existingWindow->latest_departure->format('H:i');
        }

        // If quiet hours are being updated, both start and end should be provided
        if ($this->hasAny(['quiet_hours_start', 'quiet_hours_end'])) {
            if (($quietStart && !$quietEnd) || (!$quietStart && $quietEnd)) {
                $validator->errors()->add('quiet_hours_start', 'Both quiet hours start and end times are required.');
            }
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

        // Get existing restrictions if not being completely replaced
        $existingWindow = $this->route('window');
        if ($existingWindow && !$this->has('restrictions')) {
            $restrictions = array_merge($existingWindow->restrictions ?? [], $restrictions);
        }

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
        $existingWindow = $this->route('window');

        $windowType = $this->input('window_type', $existingWindow?->window_type);
        $maxEvents = $this->input('max_concurrent_events', $existingWindow?->max_concurrent_events);
        $earliestAccess = $this->input('earliest_access', $existingWindow?->earliest_access?->format('H:i'));
        $latestDeparture = $this->input('latest_departure', $existingWindow?->latest_departure?->format('H:i'));
        $restrictions = $this->input('restrictions', $existingWindow?->restrictions ?? []);

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
            $specificDate = $this->input('specific_date', $existingWindow?->specific_date);
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
     * Validate window type transitions
     */
    private function validateWindowTypeTransition($validator): void
    {
        $newWindowType = $this->input('window_type');

        if (!$newWindowType) {
            return;
        }

        $existingWindow = $this->route('window');
        if (!$existingWindow) {
            return;
        }

        $currentWindowType = $existingWindow->window_type;

        // Check for problematic transitions
        $problematicTransitions = [
            'maintenance' => ['regular', 'special_event'], // Maintenance to active may affect bookings
            'regular' => ['maintenance'], // Regular to maintenance removes recurring availability
        ];

        if (isset($problematicTransitions[$currentWindowType]) &&
            in_array($newWindowType, $problematicTransitions[$currentWindowType])) {

            $validator->errors()->add('window_type',
                "Changing window type from '{$currentWindowType}' to '{$newWindowType}' may impact existing bookings and should be done carefully.");
        }

        // Special validation for maintenance transitions
        if ($currentWindowType !== 'maintenance' && $newWindowType === 'maintenance') {
            // Check if there are existing bookings that would be affected
            $validator->errors()->add('window_type',
                'Converting to maintenance window may conflict with existing bookings. Please verify no active bookings exist for this time period.');
        }
    }

    /**
     * Validate impact on existing bookings
     */
    private function validateExistingBookingImpact($validator): void
    {
        $existingWindow = $this->route('window');
        if (!$existingWindow) {
            return;
        }

        // Check if time window is being reduced
        $currentStart = $existingWindow->earliest_access;
        $currentEnd = $existingWindow->latest_departure;
        $newStart = $this->has('earliest_access') ? Carbon::createFromFormat('H:i', $this->input('earliest_access')) : $currentStart;
        $newEnd = $this->has('latest_departure') ? Carbon::createFromFormat('H:i', $this->input('latest_departure')) : $currentEnd;

        if ($newStart->greaterThan($currentStart) || $newEnd->lessThan($currentEnd)) {
            $validator->errors()->add('earliest_access',
                'Reducing availability window time may conflict with existing bookings. Use force_update=true if you have verified no conflicts exist.');
        }

        // Check if capacity is being reduced
        $currentCapacity = $existingWindow->max_concurrent_events;
        $newCapacity = $this->input('max_concurrent_events', $currentCapacity);

        if ($newCapacity < $currentCapacity) {
            $validator->errors()->add('max_concurrent_events',
                'Reducing maximum concurrent events may conflict with existing bookings. Use force_update=true if you have verified no conflicts exist.');
        }

        // Check if being deactivated
        if ($this->has('is_active') && !$this->boolean('is_active') && $existingWindow->is_active) {
            $validator->errors()->add('is_active',
                'Deactivating availability window may conflict with existing bookings. Use force_update=true if you have verified no conflicts exist.');
        }
    }

    /**
     * Validate partial update consistency
     */
    private function validatePartialUpdateConsistency($validator): void
    {
        $existingWindow = $this->route('window');
        if (!$existingWindow) {
            return;
        }

        // If updating only one time field, ensure consistency with the other
        if ($this->has('earliest_access') && !$this->has('latest_departure')) {
            $newStart = Carbon::createFromFormat('H:i', $this->input('earliest_access'));
            $currentEnd = $existingWindow->latest_departure;

            // Check if new start time is after current end time (invalid)
            if ($newStart->format('H:i') >= $currentEnd->format('H:i') &&
                !($currentEnd->hour < $newStart->hour)) { // Not overnight
                $validator->errors()->add('earliest_access', 'New start time would be after current end time. Please also update latest_departure.');
            }
        }

        if ($this->has('latest_departure') && !$this->has('earliest_access')) {
            $newEnd = Carbon::createFromFormat('H:i', $this->input('latest_departure'));
            $currentStart = $existingWindow->earliest_access;

            // Check if new end time is before current start time (invalid for same day)
            if ($newEnd->format('H:i') <= $currentStart->format('H:i') &&
                !($newEnd->hour < $currentStart->hour)) { // Not overnight
                $validator->errors()->add('latest_departure', 'New end time would be before current start time. Please also update earliest_access.');
            }
        }

        // If updating date fields, ensure consistency
        if ($this->has('date_range_start') && !$this->has('date_range_end')) {
            if (!$existingWindow->date_range_end) {
                $validator->errors()->add('date_range_end', 'End date is required when setting start date.');
            }
        }

        if ($this->has('date_range_end') && !$this->has('date_range_start')) {
            if (!$existingWindow->date_range_start) {
                $validator->errors()->add('date_range_start', 'Start date is required when setting end date.');
            }
        }
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convert boolean fields if present
        $booleanFields = ['is_active'];

        foreach ($booleanFields as $field) {
            if ($this->has($field)) {
                $this->merge([$field => $this->boolean($field)]);
            }
        }

        // Convert restriction boolean fields if restrictions are being updated
        if ($this->has('restrictions')) {
            $restrictions = $this->input('restrictions', []);
            $restrictionBooleans = [
                'no_children', 'no_alcohol', 'no_music', 'no_photography',
                'required_insurance', 'cleanup_required', 'special_equipment_only', 'weather_dependent'
            ];

            foreach ($restrictionBooleans as $field) {
                if (isset($restrictions[$field])) {
                    $restrictions[$field] = filter_var($restrictions[$field], FILTER_VALIDATE_BOOLEAN);
                }
            }

            $this->merge(['restrictions' => $restrictions]);
        }

        // Ensure integer fields are properly converted if present
        $integerFields = ['day_of_week', 'max_concurrent_events'];

        foreach ($integerFields as $field) {
            if ($this->has($field) && $this->input($field) !== null) {
                $this->merge([$field => (int) $this->input($field)]);
            }
        }

        // Convert restriction numeric fields if present
        if ($this->has('restrictions')) {
            $restrictions = $this->input('restrictions', []);
            $restrictionNumerics = ['min_age', 'max_noise_level', 'security_deposit', 'advance_notice_hours', 'max_wind_speed'];

            foreach ($restrictionNumerics as $field) {
                if (isset($restrictions[$field]) && $restrictions[$field] !== null) {
                    if (in_array($field, ['security_deposit'])) {
                        $restrictions[$field] = (float) $restrictions[$field];
                    } else {
                        $restrictions[$field] = (int) $restrictions[$field];
                    }
                }
            }

            $this->merge(['restrictions' => $restrictions]);
        }
    }
}
