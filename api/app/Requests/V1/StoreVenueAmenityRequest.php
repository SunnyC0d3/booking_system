<?php

namespace App\Requests\V1;

use App\Requests\V1\BaseFormRequest;

class StoreVenueAmenityRequest extends BaseFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->hasPermission('create_venue_amenities');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // Basic amenity information
            'amenity_type' => 'required|string|in:equipment,furniture,infrastructure,service,restriction',
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',

            // Availability and cost
            'included_in_booking' => 'boolean',
            'additional_cost' => 'nullable|numeric|min:0|max:10000',
            'quantity_available' => 'nullable|integer|min:1|max:1000',
            'requires_advance_notice' => 'boolean',
            'notice_hours_required' => 'nullable|integer|min:0|max:336', // 2 weeks max

            // Technical specifications
            'specifications' => 'nullable|array',
            'specifications.dimensions' => 'nullable|array',
            'specifications.dimensions.length' => 'nullable|numeric|min:0|max:100',
            'specifications.dimensions.width' => 'nullable|numeric|min:0|max:100',
            'specifications.dimensions.height' => 'nullable|numeric|min:0|max:20',
            'specifications.dimensions.weight' => 'nullable|numeric|min:0|max:1000',
            'specifications.dimensions.units' => 'nullable|string|in:cm,m,inches,feet',
            'specifications.capacity' => 'nullable|integer|min:1|max:1000',
            'specifications.power_requirements' => 'nullable|array',
            'specifications.power_requirements.voltage' => 'nullable|integer|in:110,220,240',
            'specifications.power_requirements.amperage' => 'nullable|integer|min:1|max:100',
            'specifications.power_requirements.wattage' => 'nullable|integer|min:1|max:10000',
            'specifications.setup_time' => 'nullable|integer|min:5|max:480', // 5 minutes to 8 hours
            'specifications.breakdown_time' => 'nullable|integer|min:5|max:240', // 5 minutes to 4 hours
            'specifications.material' => 'nullable|string|max:100',
            'specifications.color' => 'nullable|string|max:50',
            'specifications.weather_resistant' => 'nullable|boolean',
            'specifications.indoor_only' => 'nullable|boolean',
            'specifications.max_wind_speed' => 'nullable|integer|min:0|max:50', // mph
            'specifications.temperature_range' => 'nullable|array',
            'specifications.temperature_range.min' => 'nullable|integer|min:-20|max:50',
            'specifications.temperature_range.max' => 'nullable|integer|min:-20|max:50',

            // Usage instructions and restrictions
            'usage_instructions' => 'nullable|string|max:1000',
            'restrictions' => 'nullable|string|max:500',

            // Organization
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer|min:0|max:9999',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'amenity_type.required' => 'Amenity type is required.',
            'amenity_type.in' => 'Please select a valid amenity type (equipment, furniture, infrastructure, service, restriction).',
            'name.required' => 'Amenity name is required.',
            'name.max' => 'Amenity name cannot exceed 100 characters.',
            'description.max' => 'Description cannot exceed 500 characters.',
            'additional_cost.min' => 'Additional cost cannot be negative.',
            'additional_cost.max' => 'Additional cost cannot exceed £10,000.',
            'quantity_available.min' => 'Quantity must be at least 1.',
            'quantity_available.max' => 'Quantity cannot exceed 1,000.',
            'notice_hours_required.min' => 'Notice hours cannot be negative.',
            'notice_hours_required.max' => 'Notice hours cannot exceed 2 weeks (336 hours).',
            'specifications.dimensions.length.min' => 'Length cannot be negative.',
            'specifications.dimensions.length.max' => 'Length cannot exceed 100 meters.',
            'specifications.dimensions.width.min' => 'Width cannot be negative.',
            'specifications.dimensions.width.max' => 'Width cannot exceed 100 meters.',
            'specifications.dimensions.height.min' => 'Height cannot be negative.',
            'specifications.dimensions.height.max' => 'Height cannot exceed 20 meters.',
            'specifications.dimensions.weight.min' => 'Weight cannot be negative.',
            'specifications.dimensions.weight.max' => 'Weight cannot exceed 1,000kg.',
            'specifications.capacity.min' => 'Capacity must be at least 1.',
            'specifications.capacity.max' => 'Capacity cannot exceed 1,000.',
            'specifications.power_requirements.voltage.in' => 'Voltage must be 110V, 220V, or 240V.',
            'specifications.power_requirements.amperage.min' => 'Amperage must be at least 1A.',
            'specifications.power_requirements.amperage.max' => 'Amperage cannot exceed 100A.',
            'specifications.power_requirements.wattage.min' => 'Wattage must be at least 1W.',
            'specifications.power_requirements.wattage.max' => 'Wattage cannot exceed 10,000W.',
            'specifications.setup_time.min' => 'Setup time must be at least 5 minutes.',
            'specifications.setup_time.max' => 'Setup time cannot exceed 8 hours.',
            'specifications.breakdown_time.min' => 'Breakdown time must be at least 5 minutes.',
            'specifications.breakdown_time.max' => 'Breakdown time cannot exceed 4 hours.',
            'specifications.max_wind_speed.min' => 'Wind speed cannot be negative.',
            'specifications.max_wind_speed.max' => 'Wind speed cannot exceed 50 mph.',
            'specifications.temperature_range.min.min' => 'Minimum temperature cannot be below -20°C.',
            'specifications.temperature_range.min.max' => 'Minimum temperature cannot exceed 50°C.',
            'specifications.temperature_range.max.min' => 'Maximum temperature cannot be below -20°C.',
            'specifications.temperature_range.max.max' => 'Maximum temperature cannot exceed 50°C.',
            'usage_instructions.max' => 'Usage instructions cannot exceed 1,000 characters.',
            'restrictions.max' => 'Restrictions cannot exceed 500 characters.',
            'sort_order.min' => 'Sort order cannot be negative.',
            'sort_order.max' => 'Sort order cannot exceed 9,999.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $this->validateAmenityTypeConsistency($validator);
            $this->validateCostAndAvailability($validator);
            $this->validateSpecifications($validator);
            $this->validateAdvanceNoticeRequirements($validator);
            $this->validateBalloonArchCompatibility($validator);
            $this->validateSafetyRequirements($validator);
        });
    }

    /**
     * Validate amenity type consistency
     */
    private function validateAmenityTypeConsistency($validator): void
    {
        $amenityType = $this->input('amenity_type');
        $name = $this->input('name');
        $specifications = $this->input('specifications', []);
        $additionalCost = $this->input('additional_cost');

        switch ($amenityType) {
            case 'equipment':
                // Equipment should have specifications
                if (empty($specifications)) {
                    $validator->errors()->add('specifications', 'Equipment amenities should include technical specifications.');
                }

                // Equipment typically has setup/breakdown times
                if (!isset($specifications['setup_time'])) {
                    $validator->errors()->add('specifications.setup_time', 'Setup time is recommended for equipment.');
                }
                break;

            case 'furniture':
                // Furniture should have dimensions
                if (!isset($specifications['dimensions'])) {
                    $validator->errors()->add('specifications.dimensions', 'Furniture amenities should include dimensions.');
                }

                // Furniture typically has capacity
                if (!isset($specifications['capacity']) && !strpos(strtolower($name), 'table')) {
                    $validator->errors()->add('specifications.capacity', 'Seating furniture should specify capacity.');
                }
                break;

            case 'infrastructure':
                // Infrastructure is typically included in booking
                if ($additionalCost > 0) {
                    $validator->errors()->add('additional_cost', 'Infrastructure amenities are typically included in the base booking cost.');
                }
                break;

            case 'service':
                // Services typically have additional cost
                if (!$additionalCost && !$this->boolean('included_in_booking')) {
                    $validator->errors()->add('additional_cost', 'Service amenities typically have an additional cost or should be marked as included.');
                }

                // Services may require advance notice
                if (!$this->boolean('requires_advance_notice')) {
                    $validator->errors()->add('requires_advance_notice', 'Service amenities often require advance notice for coordination.');
                }
                break;

            case 'restriction':
                // Restrictions shouldn't have additional cost
                if ($additionalCost > 0) {
                    $validator->errors()->add('additional_cost', 'Restriction amenities should not have additional cost.');
                }

                // Restrictions should be marked as not included (they're limitations)
                if ($this->boolean('included_in_booking')) {
                    $validator->errors()->add('included_in_booking', 'Restrictions are limitations, not included amenities.');
                }
                break;
        }
    }

    /**
     * Validate cost and availability settings
     */
    private function validateCostAndAvailability($validator): void
    {
        $includedInBooking = $this->boolean('included_in_booking');
        $additionalCost = $this->input('additional_cost');
        $quantityAvailable = $this->input('quantity_available');
        $requiresNotice = $this->boolean('requires_advance_notice');
        $noticeHours = $this->input('notice_hours_required');

        // If included in booking, additional cost should be 0 or null
        if ($includedInBooking && $additionalCost > 0) {
            $validator->errors()->add('additional_cost', 'Amenities included in booking should not have additional cost.');
        }

        // If not included, should have cost or be explicitly free
        if (!$includedInBooking && !$additionalCost) {
            $validator->errors()->add('additional_cost', 'Amenities not included in booking should specify cost (use 0 for free add-ons).');
        }

        // If requires advance notice, notice hours should be specified
        if ($requiresNotice && !$noticeHours) {
            $validator->errors()->add('notice_hours_required', 'Please specify notice hours when advance notice is required.');
        }

        // If notice hours specified, should require advance notice
        if ($noticeHours > 0 && !$requiresNotice) {
            $validator->errors()->add('requires_advance_notice', 'Should require advance notice when notice hours are specified.');
        }

        // Validate quantity for certain amenity types
        $amenityType = $this->input('amenity_type');
        if (in_array($amenityType, ['equipment', 'furniture']) && !$quantityAvailable) {
            $validator->errors()->add('quantity_available', 'Physical amenities should specify available quantity.');
        }
    }

    /**
     * Validate technical specifications
     */
    private function validateSpecifications($validator): void
    {
        $specifications = $this->input('specifications', []);
        $amenityType = $this->input('amenity_type');

        // Validate power requirements consistency
        if (isset($specifications['power_requirements'])) {
            $power = $specifications['power_requirements'];

            // If voltage is specified, wattage or amperage should also be specified
            if (isset($power['voltage']) && !isset($power['wattage']) && !isset($power['amperage'])) {
                $validator->errors()->add('specifications.power_requirements.wattage', 'Please specify power consumption (wattage or amperage).');
            }

            // Validate electrical calculations (P = V × I)
            if (isset($power['voltage'], $power['amperage'], $power['wattage'])) {
                $calculatedWattage = $power['voltage'] * $power['amperage'];
                $specifiedWattage = $power['wattage'];

                if (abs($calculatedWattage - $specifiedWattage) > ($specifiedWattage * 0.1)) {
                    $validator->errors()->add('specifications.power_requirements.wattage', 'Wattage should match voltage × amperage calculation.');
                }
            }
        }

        // Validate dimensions consistency
        if (isset($specifications['dimensions'])) {
            $dimensions = $specifications['dimensions'];

            // All dimensions should use the same units
            if (isset($dimensions['length'], $dimensions['width'], $dimensions['height'])) {
                $units = $dimensions['units'] ?? 'cm';

                // Warn about very large or small dimensions
                $maxDimension = max($dimensions['length'], $dimensions['width'], $dimensions['height']);
                if ($units === 'cm' && $maxDimension > 1000) {
                    $validator->errors()->add('specifications.dimensions.units', 'Large dimensions might be better specified in meters.');
                }

                if ($units === 'm' && $maxDimension < 1) {
                    $validator->errors()->add('specifications.dimensions.units', 'Small dimensions might be better specified in centimeters.');
                }
            }
        }

        // Validate setup/breakdown time ratio
        if (isset($specifications['setup_time'], $specifications['breakdown_time'])) {
            $setupTime = $specifications['setup_time'];
            $breakdownTime = $specifications['breakdown_time'];

            // Breakdown is typically 30-70% of setup time
            $expectedBreakdownMin = $setupTime * 0.2;
            $expectedBreakdownMax = $setupTime * 0.8;

            if ($breakdownTime < $expectedBreakdownMin || $breakdownTime > $expectedBreakdownMax) {
                $validator->errors()->add('specifications.breakdown_time', 'Breakdown time typically ranges from 20-80% of setup time.');
            }
        }

        // Validate temperature range
        if (isset($specifications['temperature_range'])) {
            $tempRange = $specifications['temperature_range'];

            if (isset($tempRange['min'], $tempRange['max'])) {
                if ($tempRange['min'] >= $tempRange['max']) {
                    $validator->errors()->add('specifications.temperature_range.max', 'Maximum temperature must be higher than minimum temperature.');
                }

                // Warn about very wide or narrow ranges
                $range = $tempRange['max'] - $tempRange['min'];
                if ($range < 5) {
                    $validator->errors()->add('specifications.temperature_range', 'Very narrow temperature range may be too restrictive.');
                }

                if ($range > 60) {
                    $validator->errors()->add('specifications.temperature_range', 'Very wide temperature range may not be realistic for equipment.');
                }
            }
        }

        // Validate weather resistance for outdoor amenities
        if (isset($specifications['weather_resistant'], $specifications['indoor_only'])) {
            if ($specifications['weather_resistant'] && $specifications['indoor_only']) {
                $validator->errors()->add('specifications.indoor_only', 'Cannot be both weather resistant and indoor only.');
            }
        }
    }

    /**
     * Validate advance notice requirements
     */
    private function validateAdvanceNoticeRequirements($validator): void
    {
        $amenityType = $this->input('amenity_type');
        $name = strtolower($this->input('name'));
        $noticeHours = $this->input('notice_hours_required', 0);
        $specifications = $this->input('specifications', []);

        // Equipment with complex setup typically needs advance notice
        if ($amenityType === 'equipment') {
            $setupTime = $specifications['setup_time'] ?? 0;

            if ($setupTime > 60 && $noticeHours < 24) {
                $validator->errors()->add('notice_hours_required', 'Equipment requiring over 1 hour setup typically needs at least 24 hours notice.');
            }

            if ($setupTime > 120 && $noticeHours < 48) {
                $validator->errors()->add('notice_hours_required', 'Complex equipment setup typically requires at least 48 hours notice.');
            }
        }

        // Services typically need advance notice
        if ($amenityType === 'service' && $noticeHours < 24) {
            $validator->errors()->add('notice_hours_required', 'Service amenities typically require at least 24 hours advance notice.');
        }

        // Special equipment for balloon arches
        $balloonArchEquipment = ['helium', 'compressor', 'generator', 'lift', 'crane'];
        foreach ($balloonArchEquipment as $equipment) {
            if (strpos($name, $equipment) !== false && $noticeHours < 48) {
                $validator->errors()->add('notice_hours_required', "Special equipment like {$equipment} typically requires at least 48 hours notice.");
            }
        }

        // Power equipment needs advance notice for safety checks
        if (isset($specifications['power_requirements']) && $noticeHours < 24) {
            $validator->errors()->add('notice_hours_required', 'Electrical equipment typically requires at least 24 hours notice for safety verification.');
        }
    }

    /**
     * Validate balloon arch compatibility
     */
    private function validateBalloonArchCompatibility($validator): void
    {
        $amenityType = $this->input('amenity_type');
        $name = strtolower($this->input('name'));
        $specifications = $this->input('specifications', []);

        // Balloon arch specific equipment validation
        $balloonEquipment = ['balloon', 'helium', 'arch', 'backdrop', 'pump', 'compressor'];
        $isBalloonRelated = false;

        foreach ($balloonEquipment as $term) {
            if (strpos($name, $term) !== false) {
                $isBalloonRelated = true;
                break;
            }
        }

        if ($isBalloonRelated) {
            // Balloon equipment should specify weather resistance for outdoor use
            if (!isset($specifications['weather_resistant'])) {
                $validator->errors()->add('specifications.weather_resistant', 'Balloon equipment should specify weather resistance for outdoor events.');
            }

            // Balloon equipment should specify wind speed limits
            if (!isset($specifications['max_wind_speed'])) {
                $validator->errors()->add('specifications.max_wind_speed', 'Balloon installations should specify maximum safe wind speed.');
            }

            // Helium equipment needs special handling
            if (strpos($name, 'helium') !== false) {
                if (!isset($specifications['temperature_range'])) {
                    $validator->errors()->add('specifications.temperature_range', 'Helium equipment should specify operating temperature range.');
                }

                if ($this->input('notice_hours_required', 0) < 48) {
                    $validator->errors()->add('notice_hours_required', 'Helium equipment typically requires at least 48 hours notice for procurement and safety.');
                }
            }
        }

        // Furniture suitable for events
        if ($amenityType === 'furniture') {
            $eventFurniture = ['chair', 'table', 'bench', 'stool'];
            $isEventFurniture = false;

            foreach ($eventFurniture as $furniture) {
                if (strpos($name, $furniture) !== false) {
                    $isEventFurniture = true;
                    break;
                }
            }

            if ($isEventFurniture) {
                // Event furniture should specify capacity
                if (!isset($specifications['capacity'])) {
                    $validator->errors()->add('specifications.capacity', 'Event furniture should specify seating/usage capacity.');
                }

                // Event furniture should be weather resistant for outdoor events
                if (!isset($specifications['weather_resistant']) && !isset($specifications['indoor_only'])) {
                    $validator->errors()->add('specifications.weather_resistant', 'Event furniture should specify if suitable for outdoor use.');
                }
            }
        }

        // Equipment for balloon arch setup
        if ($amenityType === 'equipment') {
            $setupEquipment = ['ladder', 'scaffold', 'platform', 'tie', 'weight', 'anchor'];
            $isSetupEquipment = false;

            foreach ($setupEquipment as $equipment) {
                if (strpos($name, $equipment) !== false) {
                    $isSetupEquipment = true;
                    break;
                }
            }

            if ($isSetupEquipment) {
                // Setup equipment should specify weight capacity
                if (!isset($specifications['capacity'])) {
                    $validator->errors()->add('specifications.capacity', 'Setup equipment should specify weight/load capacity.');
                }

                // Safety equipment should specify restrictions
                if (!$this->input('restrictions')) {
                    $validator->errors()->add('restrictions', 'Setup equipment should specify safety restrictions and usage guidelines.');
                }
            }
        }
    }

    /**
     * Validate safety requirements
     */
    private function validateSafetyRequirements($validator): void
    {
        $name = strtolower($this->input('name'));
        $specifications = $this->input('specifications', []);
        $restrictions = $this->input('restrictions');

        // Electrical equipment safety
        if (isset($specifications['power_requirements'])) {
            if (!$restrictions) {
                $validator->errors()->add('restrictions', 'Electrical equipment should specify safety restrictions and usage guidelines.');
            }

            // High wattage equipment needs special attention
            $wattage = $specifications['power_requirements']['wattage'] ?? 0;
            if ($wattage > 1000 && $this->input('notice_hours_required', 0) < 48) {
                $validator->errors()->add('notice_hours_required', 'High-power equipment typically requires at least 48 hours notice for electrical safety verification.');
            }
        }

        // Heavy equipment safety
        if (isset($specifications['dimensions']['weight'])) {
            $weight = $specifications['dimensions']['weight'];

            if ($weight > 50 && !$restrictions) {
                $validator->errors()->add('restrictions', 'Heavy equipment should specify handling and safety restrictions.');
            }

            if ($weight > 100 && $this->input('notice_hours_required', 0) < 24) {
                $validator->errors()->add('notice_hours_required', 'Heavy equipment typically requires advance notice for safe handling preparation.');
            }
        }

        // Height equipment safety
        if (isset($specifications['dimensions']['height'])) {
            $height = $specifications['dimensions']['height'];
            $units = $specifications['dimensions']['units'] ?? 'cm';

            // Convert to meters for comparison
            $heightInMeters = $units === 'cm' ? $height / 100 :
                ($units === 'feet' ? $height * 0.3048 : $height);

            if ($heightInMeters > 3 && !$restrictions) {
                $validator->errors()->add('restrictions', 'Tall equipment should specify safety restrictions and setup guidelines.');
            }
        }

        // Outdoor equipment needs weather specifications
        $serviceLocation = $this->route('location');
        if ($serviceLocation && $serviceLocation->type === 'outdoor') {
            if (!isset($specifications['weather_resistant']) && !isset($specifications['indoor_only'])) {
                $validator->errors()->add('specifications.weather_resistant', 'Outdoor venue amenities should specify weather resistance.');
            }

            if (!isset($specifications['max_wind_speed'])) {
                $validator->errors()->add('specifications.max_wind_speed', 'Outdoor amenities should specify maximum safe wind speed.');
            }
        }

        // Pressurized equipment (helium, compressed air)
        $pressurizedTerms = ['helium', 'compressed', 'pressure', 'tank', 'cylinder'];
        $isPressurized = false;

        foreach ($pressurizedTerms as $term) {
            if (strpos($name, $term) !== false) {
                $isPressurized = true;
                break;
            }
        }

        if ($isPressurized) {
            if (!$restrictions) {
                $validator->errors()->add('restrictions', 'Pressurized equipment requires detailed safety restrictions and handling procedures.');
            }

            if ($this->input('notice_hours_required', 0) < 72) {
                $validator->errors()->add('notice_hours_required', 'Pressurized equipment typically requires at least 72 hours notice for safety compliance and procurement.');
            }
        }
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convert boolean fields
        $booleanFields = [
            'included_in_booking', 'requires_advance_notice', 'is_active'
        ];

        foreach ($booleanFields as $field) {
            if ($this->has($field)) {
                $this->merge([$field => $this->boolean($field)]);
            }
        }

        // Convert specification boolean fields
        $specifications = $this->input('specifications', []);
        $specBooleans = ['weather_resistant', 'indoor_only'];

        foreach ($specBooleans as $field) {
            if (isset($specifications[$field])) {
                $specifications[$field] = filter_var($specifications[$field], FILTER_VALIDATE_BOOLEAN);
            }
        }

        if (!empty($specifications)) {
            $this->merge(['specifications' => $specifications]);
        }

        // Convert numeric fields
        $numericFields = ['additional_cost'];

        foreach ($numericFields as $field) {
            if ($this->has($field) && $this->input($field) !== null) {
                $this->merge([$field => (float) $this->input($field)]);
            }
        }

        // Convert integer fields
        $integerFields = ['quantity_available', 'notice_hours_required', 'sort_order'];

        foreach ($integerFields as $field) {
            if ($this->has($field) && $this->input($field) !== null) {
                $this->merge([$field => (int) $this->input($field)]);
            }
        }

        // Ensure name is properly formatted
        if ($this->has('name')) {
            $name = trim($this->input('name'));
            $name = ucwords(strtolower($name)); // Title case
            $this->merge(['name' => $name]);
        }
    }
}
