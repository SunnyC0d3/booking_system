<?php

namespace App\Requests\V1;

use App\Requests\V1\BaseFormRequest;

class StoreVenueDetailsRequest extends BaseFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->hasPermission('create_venue_details');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // Basic venue characteristics
            'venue_type' => 'required|string|in:indoor,outdoor,mixed,studio,client_home,corporate,public_space',
            'space_style' => 'nullable|string|in:modern,traditional,rustic,industrial,garden,ballroom,casual,formal',

            // Physical specifications
            'ceiling_height_meters' => 'nullable|numeric|min:2|max:20',
            'floor_area_sqm' => 'nullable|numeric|min:10|max:10000',
            'room_dimensions' => 'nullable|array',
            'room_dimensions.length' => 'nullable|numeric|min:1|max:200',
            'room_dimensions.width' => 'nullable|numeric|min:1|max:200',
            'room_dimensions.height' => 'nullable|numeric|min:2|max:20',
            'room_dimensions.units' => 'nullable|string|in:meters,feet',
            'color_scheme' => 'nullable|array',
            'color_scheme.*' => 'string|max:50',

            // Access and logistics
            'access_instructions' => 'nullable|string|max:1000',
            'parking_information' => 'nullable|string|max:500',
            'loading_instructions' => 'nullable|string|max:1000',
            'lift_access' => 'boolean',
            'step_free_access' => 'boolean',
            'stairs_count' => 'nullable|integer|min:0|max:100',

            // Utilities and power
            'power_outlets' => 'nullable|array',
            'power_outlets.*.location' => 'required_with:power_outlets|string|max:100',
            'power_outlets.*.type' => 'required_with:power_outlets|string|in:standard_uk,industrial,outdoor,usb',
            'power_outlets.*.voltage' => 'nullable|integer|in:110,220,240',
            'power_outlets.*.amp_rating' => 'nullable|integer|min:5|max:100',
            'has_adequate_lighting' => 'boolean',
            'lighting_notes' => 'nullable|string|max:500',
            'climate_controlled' => 'boolean',
            'typical_temperature' => 'nullable|numeric|min:10|max:35',

            // Setup considerations
            'setup_restrictions' => 'nullable|array',
            'setup_restrictions.no_setup_times' => 'nullable|array',
            'setup_restrictions.no_setup_times.*' => 'date_format:H:i',
            'setup_restrictions.restricted_days' => 'nullable|array',
            'setup_restrictions.restricted_days.*' => 'integer|min:0|max:6',
            'setup_restrictions.special_requirements' => 'nullable|string|max:500',
            'setup_time_minutes' => 'nullable|integer|min:15|max:480',
            'breakdown_time_minutes' => 'nullable|integer|min:15|max:480',
            'noise_restrictions' => 'nullable|string|max:500',
            'prohibited_items' => 'nullable|array',
            'prohibited_items.*' => 'string|max:100',

            // Venue contacts
            'venue_contacts' => 'nullable|array',
            'venue_contacts.manager' => 'nullable|array',
            'venue_contacts.manager.name' => 'nullable|string|max:100',
            'venue_contacts.manager.phone' => 'nullable|string|max:20',
            'venue_contacts.manager.email' => 'nullable|email|max:100',
            'venue_contacts.security' => 'nullable|array',
            'venue_contacts.security.name' => 'nullable|string|max:100',
            'venue_contacts.security.phone' => 'nullable|string|max:20',
            'venue_contacts.emergency' => 'nullable|array',
            'venue_contacts.emergency.name' => 'nullable|string|max:100',
            'venue_contacts.emergency.phone' => 'nullable|string|max:20',
            'special_instructions' => 'nullable|string|max:1000',

            // Photo/event restrictions
            'photography_allowed' => 'boolean',
            'photography_restrictions' => 'nullable|string|max:500',
            'social_media_allowed' => 'boolean',

            // Legacy support fields (backward compatibility)
            'setup_requirements' => 'nullable|string|max:1000',
            'equipment_available' => 'nullable|array',
            'equipment_available.*' => 'string|max:100',
            'accessibility_info' => 'nullable|string|max:500',
            'parking_info' => 'nullable|string|max:500',
            'catering_options' => 'nullable|array',
            'catering_options.*' => 'string|max:100',
            'max_capacity' => 'nullable|integer|min:1|max:1000',
            'additional_fee' => 'nullable|numeric|min:0|max:10000',
            'amenities' => 'nullable|array',
            'amenities.*' => 'string|max:100',
            'restrictions' => 'nullable|array',
            'restrictions.*' => 'string|max:200',
            'contact_info' => 'nullable|array',
            'operating_hours' => 'nullable|array',
            'cancellation_policy' => 'nullable|string|max:1000',
            'metadata' => 'nullable|array',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'venue_type.required' => 'Venue type is required.',
            'venue_type.in' => 'Please select a valid venue type.',
            'ceiling_height_meters.min' => 'Ceiling height must be at least 2 meters.',
            'ceiling_height_meters.max' => 'Ceiling height cannot exceed 20 meters.',
            'floor_area_sqm.min' => 'Floor area must be at least 10 square meters.',
            'floor_area_sqm.max' => 'Floor area cannot exceed 10,000 square meters.',
            'room_dimensions.length.min' => 'Room length must be at least 1 meter.',
            'room_dimensions.width.min' => 'Room width must be at least 1 meter.',
            'room_dimensions.height.min' => 'Room height must be at least 2 meters.',
            'stairs_count.max' => 'Stairs count cannot exceed 100.',
            'power_outlets.*.location.required_with' => 'Power outlet location is required.',
            'power_outlets.*.type.required_with' => 'Power outlet type is required.',
            'power_outlets.*.type.in' => 'Please select a valid power outlet type.',
            'power_outlets.*.voltage.in' => 'Voltage must be 110V, 220V, or 240V.',
            'power_outlets.*.amp_rating.min' => 'Amp rating must be at least 5A.',
            'power_outlets.*.amp_rating.max' => 'Amp rating cannot exceed 100A.',
            'typical_temperature.min' => 'Temperature must be at least 10°C.',
            'typical_temperature.max' => 'Temperature cannot exceed 35°C.',
            'setup_time_minutes.min' => 'Setup time must be at least 15 minutes.',
            'setup_time_minutes.max' => 'Setup time cannot exceed 8 hours.',
            'breakdown_time_minutes.min' => 'Breakdown time must be at least 15 minutes.',
            'breakdown_time_minutes.max' => 'Breakdown time cannot exceed 8 hours.',
            'venue_contacts.manager.email.email' => 'Manager email must be valid.',
            'venue_contacts.emergency.phone.required_with' => 'Emergency contact phone is required.',
            'max_capacity.min' => 'Maximum capacity must be at least 1 person.',
            'max_capacity.max' => 'Maximum capacity cannot exceed 1,000 people.',
            'additional_fee.min' => 'Additional fee cannot be negative.',
            'additional_fee.max' => 'Additional fee cannot exceed £10,000.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $this->validateVenueTypeConsistency($validator);
            $this->validateDimensionConsistency($validator);
            $this->validateAccessibilityFeatures($validator);
            $this->validatePowerRequirements($validator);
            $this->validateSetupTiming($validator);
            $this->validateContactInformation($validator);
        });
    }

    /**
     * Validate venue type consistency
     */
    private function validateVenueTypeConsistency($validator): void
    {
        $venueType = $this->input('venue_type');
        $ceilingHeight = $this->input('ceiling_height_meters');
        $climateControlled = $this->boolean('climate_controlled');

        // Outdoor venues shouldn't have ceiling height
        if ($venueType === 'outdoor' && $ceilingHeight) {
            $validator->errors()->add('ceiling_height_meters', 'Outdoor venues do not require ceiling height specification.');
        }

        // Outdoor venues are typically not climate controlled
        if ($venueType === 'outdoor' && $climateControlled) {
            $validator->errors()->add('climate_controlled', 'Outdoor venues cannot be climate controlled.');
        }

        // Client homes should have specific considerations
        if ($venueType === 'client_home') {
            $accessInstructions = $this->input('access_instructions');
            if (empty($accessInstructions)) {
                $validator->errors()->add('access_instructions', 'Access instructions are required for client home venues.');
            }
        }
    }

    /**
     * Validate dimension consistency
     */
    private function validateDimensionConsistency($validator): void
    {
        $floorArea = $this->input('floor_area_sqm');
        $roomDimensions = $this->input('room_dimensions', []);

        // If room dimensions are provided, validate against floor area
        if (!empty($roomDimensions) && isset($roomDimensions['length'], $roomDimensions['width'])) {
            $calculatedArea = $roomDimensions['length'] * $roomDimensions['width'];

            if ($floorArea && abs($calculatedArea - $floorArea) > ($floorArea * 0.1)) {
                $validator->errors()->add('floor_area_sqm', 'Floor area should match the calculated area from room dimensions (±10%).');
            }
        }

        // Validate ceiling height against room height
        $ceilingHeight = $this->input('ceiling_height_meters');
        if ($ceilingHeight && isset($roomDimensions['height'])) {
            $roomHeight = $roomDimensions['height'];
            $units = $roomDimensions['units'] ?? 'meters';

            // Convert to meters if needed
            if ($units === 'feet') {
                $roomHeight = $roomHeight * 0.3048;
            }

            if (abs($ceilingHeight - $roomHeight) > 0.5) {
                $validator->errors()->add('ceiling_height_meters', 'Ceiling height should match room height dimension.');
            }
        }
    }

    /**
     * Validate accessibility features
     */
    private function validateAccessibilityFeatures($validator): void
    {
        $liftAccess = $this->boolean('lift_access');
        $stepFreeAccess = $this->boolean('step_free_access');
        $stairsCount = $this->input('stairs_count');

        // If there are stairs but step-free access is claimed
        if ($stepFreeAccess && $stairsCount > 0) {
            $validator->errors()->add('step_free_access', 'Cannot claim step-free access when stairs are present.');
        }

        // If no lift access but claiming step-free on multi-level
        if ($stepFreeAccess && !$liftAccess && $stairsCount > 0) {
            $validator->errors()->add('lift_access', 'Lift access may be required for step-free access with stairs present.');
        }
    }

    /**
     * Validate power requirements
     */
    private function validatePowerRequirements($validator): void
    {
        $powerOutlets = $this->input('power_outlets', []);
        $venueType = $this->input('venue_type');

        foreach ($powerOutlets as $index => $outlet) {
            // Validate outdoor power outlets
            if ($venueType === 'outdoor' && isset($outlet['type']) && $outlet['type'] !== 'outdoor') {
                $validator->errors()->add("power_outlets.{$index}.type", 'Outdoor venues should have outdoor-rated power outlets.');
            }

            // Validate industrial power requirements
            if (isset($outlet['type']) && $outlet['type'] === 'industrial') {
                if (!isset($outlet['amp_rating']) || $outlet['amp_rating'] < 20) {
                    $validator->errors()->add("power_outlets.{$index}.amp_rating", 'Industrial outlets should have at least 20A rating.');
                }
            }
        }
    }

    /**
     * Validate setup timing
     */
    private function validateSetupTiming($validator): void
    {
        $setupTime = $this->input('setup_time_minutes');
        $breakdownTime = $this->input('breakdown_time_minutes');
        $floorArea = $this->input('floor_area_sqm');

        // Validate realistic setup times based on area
        if ($setupTime && $floorArea) {
            $minSetupTime = max(30, $floorArea * 0.5); // 0.5 minutes per sqm minimum
            $maxSetupTime = $floorArea * 2; // 2 minutes per sqm maximum

            if ($setupTime < $minSetupTime) {
                $validator->errors()->add('setup_time_minutes', "Setup time seems too short for a {$floorArea}sqm venue. Consider at least {$minSetupTime} minutes.");
            }

            if ($setupTime > $maxSetupTime) {
                $validator->errors()->add('setup_time_minutes', "Setup time seems excessive for a {$floorArea}sqm venue. Consider no more than {$maxSetupTime} minutes.");
            }
        }

        // Breakdown is typically 50-70% of setup time
        if ($setupTime && $breakdownTime) {
            $expectedBreakdownMin = $setupTime * 0.3;
            $expectedBreakdownMax = $setupTime * 0.8;

            if ($breakdownTime < $expectedBreakdownMin || $breakdownTime > $expectedBreakdownMax) {
                $validator->errors()->add('breakdown_time_minutes', 'Breakdown time should typically be 30-80% of setup time.');
            }
        }
    }

    /**
     * Validate contact information
     */
    private function validateContactInformation($validator): void
    {
        $venueContacts = $this->input('venue_contacts', []);
        $venueType = $this->input('venue_type');

        // Require emergency contact for certain venue types
        if (in_array($venueType, ['outdoor', 'client_home']) && empty($venueContacts['emergency'])) {
            $validator->errors()->add('venue_contacts.emergency', 'Emergency contact is required for outdoor and client home venues.');
        }

        // Validate phone number formats
        foreach (['manager', 'security', 'emergency'] as $contactType) {
            if (isset($venueContacts[$contactType]['phone'])) {
                $phone = $venueContacts[$contactType]['phone'];
                if (!preg_match('/^[\+]?[0-9\s\-\(\)]{10,20}$/', $phone)) {
                    $validator->errors()->add("venue_contacts.{$contactType}.phone", 'Please provide a valid phone number.');
                }
            }
        }

        // Require manager contact for corporate venues
        if ($venueType === 'corporate' && empty($venueContacts['manager'])) {
            $validator->errors()->add('venue_contacts.manager', 'Manager contact is required for corporate venues.');
        }
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convert boolean fields
        $booleanFields = [
            'lift_access', 'step_free_access', 'has_adequate_lighting',
            'climate_controlled', 'photography_allowed', 'social_media_allowed'
        ];

        foreach ($booleanFields as $field) {
            if ($this->has($field)) {
                $this->merge([$field => $this->boolean($field)]);
            }
        }

        // Convert numeric fields
        $numericFields = [
            'ceiling_height_meters', 'floor_area_sqm', 'typical_temperature',
            'setup_time_minutes', 'breakdown_time_minutes', 'additional_fee'
        ];

        foreach ($numericFields as $field) {
            if ($this->has($field) && $this->input($field) !== null) {
                $value = $this->input($field);
                if (is_numeric($value)) {
                    $this->merge([$field => (float) $value]);
                }
            }
        }

        // Ensure arrays are properly formatted
        $arrayFields = ['color_scheme', 'power_outlets', 'prohibited_items', 'equipment_available', 'amenities', 'restrictions'];

        foreach ($arrayFields as $field) {
            if ($this->has($field) && !is_array($this->input($field))) {
                $this->merge([$field => []]);
            }
        }
    }
}
