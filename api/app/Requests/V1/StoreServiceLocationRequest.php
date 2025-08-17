<?php

namespace App\Requests\V1;

class StoreServiceLocationRequest extends BaseFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->hasPermission('create_service_locations');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'type' => 'required|in:business_premises,client_location,outdoor,mobile,virtual,studio',

            // Address fields
            'address_line_1' => 'nullable|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state_province' => 'nullable|string|max:100',
            'postcode' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',

            // Geographic coordinates
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',

            // Contact information
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|url|max:255',

            // Capacity and logistics
            'max_capacity' => 'nullable|integer|min:1|max:1000',
            'setup_time_minutes' => 'nullable|integer|min:0|max:480',
            'breakdown_time_minutes' => 'nullable|integer|min:0|max:480',
            'travel_time_minutes' => 'nullable|integer|min:0|max:480',

            // Pricing
            'additional_charge' => 'nullable|integer|min:0|max:99999999',
            'travel_charge' => 'nullable|integer|min:0|max:99999999',

            // Availability and booking constraints
            'min_advance_booking_hours' => 'nullable|integer|min:1|max:8760',
            'max_advance_booking_days' => 'nullable|integer|min:1|max:365',
            'requires_site_visit' => 'boolean',
            'requires_access_code' => 'boolean',

            // Status and operational details
            'is_active' => 'boolean',
            'is_public' => 'boolean',
            'requires_approval' => 'boolean',

            // Additional information
            'directions' => 'nullable|string|max:1000',
            'parking_info' => 'nullable|string|max:500',
            'access_instructions' => 'nullable|string|max:1000',
            'equipment_available' => 'nullable|string|max:1000',
            'restrictions' => 'nullable|string|max:1000',
            'notes' => 'nullable|string|max:1000',

            // Operating hours (optional - for business premises)
            'operating_hours' => 'nullable|array',
            'operating_hours.*.day' => 'required_with:operating_hours|integer|min:0|max:6',
            'operating_hours.*.open_time' => 'required_with:operating_hours|date_format:H:i:s',
            'operating_hours.*.close_time' => 'required_with:operating_hours|date_format:H:i:s|after:operating_hours.*.open_time',
            'operating_hours.*.is_closed' => 'boolean',

            // Venue details (for detailed locations)
            'venue_details' => 'nullable|array',
            'venue_details.venue_type' => 'nullable|string|in:indoor,outdoor,mixed,studio,warehouse,home,office,event_space',
            'venue_details.floor_area_sqm' => 'nullable|numeric|min:1|max:10000',
            'venue_details.ceiling_height_m' => 'nullable|numeric|min:1|max:20',
            'venue_details.has_electricity' => 'boolean',
            'venue_details.has_running_water' => 'boolean',
            'venue_details.has_parking' => 'boolean',
            'venue_details.parking_spaces' => 'nullable|integer|min:0|max:1000',
            'venue_details.accessibility_features' => 'nullable|string|max:500',
            'venue_details.special_requirements' => 'nullable|string|max:500',

            // Consultation support
            'supports_consultation' => 'boolean',
            'consultation_methods' => 'nullable|array',
            'consultation_methods.*' => 'in:phone,video,in_person,site_visit',

            // Metadata
            'metadata' => 'nullable|array',
            'sort_order' => 'nullable|integer|min:0|max:9999',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Location name is required.',
            'name.max' => 'Location name cannot exceed 255 characters.',
            'type.required' => 'Location type is required.',
            'type.in' => 'Invalid location type selected.',
            'latitude.between' => 'Latitude must be between -90 and 90 degrees.',
            'longitude.between' => 'Longitude must be between -180 and 180 degrees.',
            'phone.max' => 'Phone number cannot exceed 20 characters.',
            'email.email' => 'Please provide a valid email address.',
            'website.url' => 'Please provide a valid website URL.',
            'max_capacity.min' => 'Maximum capacity must be at least 1.',
            'max_capacity.max' => 'Maximum capacity cannot exceed 1000.',
            'setup_time_minutes.max' => 'Setup time cannot exceed 8 hours.',
            'breakdown_time_minutes.max' => 'Breakdown time cannot exceed 8 hours.',
            'travel_time_minutes.max' => 'Travel time cannot exceed 8 hours.',
            'additional_charge.min' => 'Additional charge cannot be negative.',
            'travel_charge.min' => 'Travel charge cannot be negative.',
            'operating_hours.*.day.min' => 'Day must be between 0 (Sunday) and 6 (Saturday).',
            'operating_hours.*.day.max' => 'Day must be between 0 (Sunday) and 6 (Saturday).',
            'operating_hours.*.open_time.date_format' => 'Open time must be in HH:MM:SS format.',
            'operating_hours.*.close_time.date_format' => 'Close time must be in HH:MM:SS format.',
            'operating_hours.*.close_time.after' => 'Close time must be after open time.',
            'venue_details.venue_type.in' => 'Invalid venue type selected.',
            'venue_details.floor_area_sqm.min' => 'Floor area must be at least 1 square meter.',
            'venue_details.ceiling_height_m.min' => 'Ceiling height must be at least 1 meter.',
            'consultation_methods.*.in' => 'Invalid consultation method selected.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validate location type requirements
            $this->validateLocationTypeRequirements($validator);

            // Validate address requirements
            $this->validateAddressRequirements($validator);

            // Validate capacity and logistics
            $this->validateCapacityAndLogistics($validator);

            // Validate pricing consistency
            $this->validatePricingConsistency($validator);

            // Validate operating hours
            $this->validateOperatingHours($validator);

            // Validate venue details consistency
            $this->validateVenueDetailsConsistency($validator);

            // Validate consultation configuration
            $this->validateConsultationConfiguration($validator);

            // Validate service location limits
            $this->validateServiceLocationLimits($validator);
        });
    }

    /**
     * Validate location type specific requirements
     */
    private function validateLocationTypeRequirements($validator): void
    {
        $type = $this->input('type');
        $addressLine1 = $this->input('address_line_1');
        $city = $this->input('city');
        $latitude = $this->input('latitude');
        $longitude = $this->input('longitude');

        switch ($type) {
            case 'business_premises':
            case 'studio':
                if (empty($addressLine1) || empty($city)) {
                    $validator->errors()->add('address_line_1', 'Address is required for business premises and studio locations.');
                    $validator->errors()->add('city', 'City is required for business premises and studio locations.');
                }
                break;

            case 'client_location':
                // Client locations don't need specific address as it's provided per booking
                break;

            case 'virtual':
                // Virtual locations don't need physical address but should have contact methods
                if (empty($this->input('phone')) && empty($this->input('email'))) {
                    $validator->errors()->add('phone', 'Virtual locations require at least phone or email contact information.');
                }
                break;

            case 'outdoor':
                if (empty($latitude) || empty($longitude)) {
                    $validator->errors()->add('latitude', 'Outdoor locations should have GPS coordinates for accurate location.');
                    $validator->errors()->add('longitude', 'Outdoor locations should have GPS coordinates for accurate location.');
                }
                break;
        }
    }

    /**
     * Validate address requirements
     */
    private function validateAddressRequirements($validator): void
    {
        $hasAnyAddress = $this->input('address_line_1') || $this->input('city') || $this->input('postcode');
        $hasCoordinates = $this->input('latitude') && $this->input('longitude');

        // If any address field is provided, require basic address info
        if ($hasAnyAddress && (!$this->input('address_line_1') || !$this->input('city'))) {
            $validator->errors()->add('address_line_1', 'Address line 1 is required when providing address information.');
            $validator->errors()->add('city', 'City is required when providing address information.');
        }

        // For physical locations, require either address or coordinates
        $physicalTypes = ['business_premises', 'outdoor', 'studio'];
        if (in_array($this->input('type'), $physicalTypes) && !$hasAnyAddress && !$hasCoordinates) {
            $validator->errors()->add('address_line_1', 'Physical locations require either an address or GPS coordinates.');
        }
    }

    /**
     * Validate capacity and logistics
     */
    private function validateCapacityAndLogistics($validator): void
    {
        $setupTime = $this->input('setup_time_minutes', 0);
        $breakdownTime = $this->input('breakdown_time_minutes', 0);
        $travelTime = $this->input('travel_time_minutes', 0);

        // Total logistics time shouldn't exceed reasonable limits
        $totalLogisticsTime = $setupTime + $breakdownTime + $travelTime;
        if ($totalLogisticsTime > 720) { // 12 hours
            $validator->errors()->add('setup_time_minutes', 'Combined setup, breakdown, and travel time cannot exceed 12 hours.');
        }

        // Mobile locations should have travel time
        if ($this->input('type') === 'mobile' && $travelTime === 0) {
            $validator->errors()->add('travel_time_minutes', 'Mobile locations should specify expected travel time.');
        }
    }

    /**
     * Validate pricing consistency
     */
    private function validatePricingConsistency($validator): void
    {
        $additionalCharge = $this->input('additional_charge', 0);
        $travelCharge = $this->input('travel_charge', 0);
        $type = $this->input('type');

        // Virtual locations shouldn't have travel charges
        if ($type === 'virtual' && $travelCharge > 0) {
            $validator->errors()->add('travel_charge', 'Virtual locations should not have travel charges.');
        }

        // Mobile locations should consider travel charges
        if ($type === 'mobile' && $travelCharge === 0 && $additionalCharge === 0) {
            // This is just a warning-like validation, not an error
            // Could be implemented as a soft validation or business rule
        }
    }

    /**
     * Validate operating hours
     */
    private function validateOperatingHours($validator): void
    {
        $operatingHours = $this->input('operating_hours', []);

        if (!empty($operatingHours)) {
            $daysProvided = array_column($operatingHours, 'day');
            $uniqueDays = array_unique($daysProvided);

            if (count($daysProvided) !== count($uniqueDays)) {
                $validator->errors()->add('operating_hours', 'Duplicate days are not allowed in operating hours.');
            }

            // Validate each day's hours
            foreach ($operatingHours as $index => $hours) {
                if (!isset($hours['is_closed']) || !$hours['is_closed']) {
                    if (empty($hours['open_time']) || empty($hours['close_time'])) {
                        $validator->errors()->add("operating_hours.{$index}.open_time", 'Open and close times are required for operating days.');
                    }
                }
            }
        }
    }

    /**
     * Validate venue details consistency
     */
    private function validateVenueDetailsConsistency($validator): void
    {
        $venueDetails = $this->input('venue_details', []);
        $locationType = $this->input('type');

        if (!empty($venueDetails)) {
            // Outdoor venues shouldn't have ceiling height
            if ($locationType === 'outdoor' && isset($venueDetails['ceiling_height_m'])) {
                $validator->errors()->add('venue_details.ceiling_height_m', 'Outdoor venues do not require ceiling height.');
            }

            // Validate parking consistency
            if (isset($venueDetails['has_parking']) && $venueDetails['has_parking'] &&
                isset($venueDetails['parking_spaces']) && $venueDetails['parking_spaces'] == 0) {
                $validator->errors()->add('venue_details.parking_spaces', 'Number of parking spaces is required when parking is available.');
            }

            // Validate floor area reasonableness
            if (isset($venueDetails['floor_area_sqm']) && $venueDetails['floor_area_sqm'] > 0) {
                $maxCapacity = $this->input('max_capacity', 0);
                if ($maxCapacity > 0) {
                    $spacePerPerson = $venueDetails['floor_area_sqm'] / $maxCapacity;
                    if ($spacePerPerson < 1) { // Less than 1 sqm per person
                        $validator->errors()->add('max_capacity', 'Maximum capacity seems too high for the available floor area.');
                    }
                }
            }
        }
    }

    /**
     * Validate consultation configuration
     */
    private function validateConsultationConfiguration($validator): void
    {
        $supportsConsultation = $this->input('supports_consultation', false);
        $consultationMethods = $this->input('consultation_methods', []);
        $locationType = $this->input('type');

        if ($supportsConsultation && empty($consultationMethods)) {
            $validator->errors()->add('consultation_methods', 'Consultation methods are required when consultation is supported.');
        }

        // Virtual locations should only support phone/video consultations
        if ($locationType === 'virtual' && in_array('in_person', $consultationMethods)) {
            $validator->errors()->add('consultation_methods', 'Virtual locations cannot support in-person consultations.');
        }

        // Site visits only make sense for certain location types
        if (in_array('site_visit', $consultationMethods) &&
            !in_array($locationType, ['client_location', 'outdoor', 'mobile'])) {
            $validator->errors()->add('consultation_methods', 'Site visit consultations are only applicable for client locations, outdoor, or mobile services.');
        }
    }

    /**
     * Validate service location limits
     */
    private function validateServiceLocationLimits($validator): void
    {
        $service = $this->route('service');

        if ($service) {
            $currentLocationCount = $service->serviceLocations()->count();
            $maxLocationsPerService = 50; // Business rule

            if ($currentLocationCount >= $maxLocationsPerService) {
                $validator->errors()->add('name', "Maximum number of locations ({$maxLocationsPerService}) reached for this service.");
            }

            // Check for duplicate names within the same service
            $existingLocation = $service->serviceLocations()
                ->where('name', $this->input('name'))
                ->exists();

            if ($existingLocation) {
                $validator->errors()->add('name', 'A location with this name already exists for this service.');
            }
        }
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set default values
        $this->mergeIfMissing([
            'is_active' => true,
            'is_public' => true,
            'requires_approval' => false,
            'requires_site_visit' => false,
            'requires_access_code' => false,
            'supports_consultation' => false,
            'sort_order' => 0,
        ]);

        // Convert price fields from pounds to pence if needed
        $priceFields = ['additional_charge', 'travel_charge'];
        foreach ($priceFields as $field) {
            if ($this->has($field) && is_numeric($this->input($field))) {
                $value = $this->input($field);
                if (str_contains((string)$value, '.') || $value < 100) {
                    $this->merge([$field => (int) round($value * 100)]);
                }
            }
        }

        // Convert boolean fields
        $booleanFields = [
            'is_active', 'is_public', 'requires_approval', 'requires_site_visit',
            'requires_access_code', 'supports_consultation'
        ];

        foreach ($booleanFields as $field) {
            if ($this->has($field)) {
                $this->merge([$field => $this->boolean($field)]);
            }
        }

        // Handle venue details boolean fields
        if ($this->has('venue_details')) {
            $venueDetails = $this->input('venue_details');
            $venueBooleanFields = ['has_electricity', 'has_running_water', 'has_parking'];

            foreach ($venueBooleanFields as $field) {
                if (isset($venueDetails[$field])) {
                    $venueDetails[$field] = filter_var($venueDetails[$field], FILTER_VALIDATE_BOOLEAN);
                }
            }

            $this->merge(['venue_details' => $venueDetails]);
        }
    }
}
