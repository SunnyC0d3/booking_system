<?php

namespace App\Requests\V1;

class StoreServiceAddOnRequest extends BaseFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->hasPermission('create_service_addons');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'required|string|max:2000',
            'short_description' => 'nullable|string|max:500',
            'category' => 'required|in:decoration,equipment,service,consultation,premium,seasonal,custom',

            // Pricing
            'price' => 'required|integer|min:0|max:99999999', // in pence
            'cost_price' => 'nullable|integer|min:0|max:99999999', // in pence
            'pricing_type' => 'required|in:fixed,per_unit,per_hour,percentage',
            'pricing_unit' => 'nullable|string|max:50', // e.g., 'per balloon', 'per meter', 'per hour'

            // Quantity and availability
            'min_quantity' => 'nullable|integer|min:1|max:1000',
            'max_quantity' => 'nullable|integer|min:1|max:1000',
            'default_quantity' => 'nullable|integer|min:1|max:1000',
            'unlimited_quantity' => 'boolean',

            // Duration and logistics
            'duration_minutes' => 'nullable|integer|min:0|max:480', // additional time for this add-on
            'setup_time_minutes' => 'nullable|integer|min:0|max:240',
            'requires_consultation' => 'boolean',
            'consultation_duration_minutes' => 'nullable|integer|min:15|max:120',

            // Availability and constraints
            'is_active' => 'boolean',
            'is_required' => 'boolean',
            'is_optional' => 'boolean',
            'is_seasonal' => 'boolean',
            'available_from' => 'nullable|date',
            'available_until' => 'nullable|date|after:available_from',

            // Service relationship constraints
            'requires_service_types' => 'nullable|array',
            'requires_service_types.*' => 'string|max:100',
            'incompatible_with' => 'nullable|array',
            'incompatible_with.*' => 'exists:service_add_ons,id',
            'prerequisite_add_ons' => 'nullable|array',
            'prerequisite_add_ons.*' => 'exists:service_add_ons,id',

            // Location and venue constraints
            'location_restrictions' => 'nullable|array',
            'location_restrictions.*' => 'in:indoor_only,outdoor_only,studio_only,mobile_only,no_restrictions',
            'venue_requirements' => 'nullable|array',
            'venue_requirements.min_ceiling_height' => 'nullable|numeric|min:1|max:20',
            'venue_requirements.min_floor_area' => 'nullable|numeric|min:1|max:10000',
            'venue_requirements.requires_electricity' => 'boolean',
            'venue_requirements.requires_water' => 'boolean',
            'venue_requirements.requires_parking' => 'boolean',

            // Advance booking and lead time
            'min_advance_booking_hours' => 'nullable|integer|min:1|max:720', // 30 days max
            'lead_time_hours' => 'nullable|integer|min:0|max:168', // 1 week max
            'can_be_added_last_minute' => 'boolean',

            // Terms and conditions
            'terms_and_conditions' => 'nullable|string|max:2000',
            'cancellation_policy' => 'nullable|string|max:1000',
            'refund_policy' => 'nullable|string|max:1000',

            // Display and organization
            'sort_order' => 'nullable|integer|min:0|max:9999',
            'display_group' => 'nullable|string|max:100',
            'display_priority' => 'nullable|in:low,normal,high,featured',
            'badge_text' => 'nullable|string|max:50', // e.g., 'Popular', 'New', 'Premium'

            // Images and media
            'images' => 'nullable|array|max:5',
            'images.*' => 'image|mimes:jpeg,jpg,png,webp|max:5120', // 5MB max per image

            // Tax and compliance
            'tax_category' => 'nullable|string|max:50',
            'is_taxable' => 'boolean',

            // Metadata and custom fields
            'metadata' => 'nullable|array',
            'custom_fields' => 'nullable|array',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Add-on name is required.',
            'name.max' => 'Add-on name cannot exceed 255 characters.',
            'description.required' => 'Add-on description is required.',
            'description.max' => 'Description cannot exceed 2000 characters.',
            'category.required' => 'Add-on category is required.',
            'category.in' => 'Invalid add-on category selected.',
            'price.required' => 'Add-on price is required.',
            'price.integer' => 'Price must be a valid amount in pence.',
            'price.min' => 'Price cannot be negative.',
            'pricing_type.required' => 'Pricing type is required.',
            'pricing_type.in' => 'Invalid pricing type selected.',
            'min_quantity.min' => 'Minimum quantity must be at least 1.',
            'max_quantity.min' => 'Maximum quantity must be at least 1.',
            'default_quantity.min' => 'Default quantity must be at least 1.',
            'duration_minutes.max' => 'Duration cannot exceed 8 hours.',
            'setup_time_minutes.max' => 'Setup time cannot exceed 4 hours.',
            'consultation_duration_minutes.min' => 'Consultation duration must be at least 15 minutes.',
            'consultation_duration_minutes.max' => 'Consultation duration cannot exceed 2 hours.',
            'available_until.after' => 'Available until date must be after available from date.',
            'incompatible_with.*.exists' => 'One or more incompatible add-ons do not exist.',
            'prerequisite_add_ons.*.exists' => 'One or more prerequisite add-ons do not exist.',
            'min_advance_booking_hours.max' => 'Minimum advance booking cannot exceed 30 days.',
            'lead_time_hours.max' => 'Lead time cannot exceed 1 week.',
            'images.max' => 'Cannot upload more than 5 images.',
            'images.*.image' => 'Each file must be an image.',
            'images.*.mimes' => 'Images must be JPEG, JPG, PNG, or WebP format.',
            'images.*.max' => 'Each image cannot exceed 5MB.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validate quantity constraints
            $this->validateQuantityConstraints($validator);

            // Validate pricing configuration
            $this->validatePricingConfiguration($validator);

            // Validate consultation requirements
            $this->validateConsultationRequirements($validator);

            // Validate service relationship constraints
            $this->validateServiceRelationshipConstraints($validator);

            // Validate venue requirements
            $this->validateVenueRequirements($validator);

            // Validate seasonal availability
            $this->validateSeasonalAvailability($validator);

            // Validate business logic
            $this->validateBusinessLogic($validator);

            // Validate add-on limits
            $this->validateAddOnLimits($validator);

            // Validate category-specific rules
            $this->validateCategorySpecificRules($validator);
        });
    }

    /**
     * Validate quantity constraints
     */
    private function validateQuantityConstraints($validator): void
    {
        $minQuantity = $this->input('min_quantity', 1);
        $maxQuantity = $this->input('max_quantity');
        $defaultQuantity = $this->input('default_quantity', 1);
        $unlimitedQuantity = $this->input('unlimited_quantity', false);

        if (!$unlimitedQuantity && $maxQuantity) {
            if ($minQuantity > $maxQuantity) {
                $validator->errors()->add('min_quantity', 'Minimum quantity cannot be greater than maximum quantity.');
            }

            if ($defaultQuantity > $maxQuantity) {
                $validator->errors()->add('default_quantity', 'Default quantity cannot be greater than maximum quantity.');
            }
        }

        if ($defaultQuantity < $minQuantity) {
            $validator->errors()->add('default_quantity', 'Default quantity cannot be less than minimum quantity.');
        }

        // If unlimited quantity is true, max_quantity should not be set
        if ($unlimitedQuantity && $maxQuantity) {
            $validator->errors()->add('max_quantity', 'Maximum quantity should not be set when unlimited quantity is enabled.');
        }
    }

    /**
     * Validate pricing configuration
     */
    private function validatePricingConfiguration($validator): void
    {
        $price = $this->input('price');
        $costPrice = $this->input('cost_price');
        $pricingType = $this->input('pricing_type');
        $pricingUnit = $this->input('pricing_unit');

        // Cost price should not exceed selling price
        if ($costPrice && $costPrice > $price) {
            $validator->errors()->add('cost_price', 'Cost price cannot exceed selling price.');
        }

        // Pricing unit validation based on pricing type
        if (in_array($pricingType, ['per_unit', 'per_hour']) && empty($pricingUnit)) {
            $validator->errors()->add('pricing_unit', 'Pricing unit is required for per-unit and per-hour pricing types.');
        }

        // Percentage pricing should have reasonable limits
        if ($pricingType === 'percentage') {
            if ($price > 10000) { // 100% in basis points
                $validator->errors()->add('price', 'Percentage pricing cannot exceed 100%.');
            }
        }

        // Zero price validation for certain categories
        $category = $this->input('category');
        $freeCategoriesAllowed = ['consultation', 'service'];
        if ($price === 0 && !in_array($category, $freeCategoriesAllowed)) {
            $validator->errors()->add('price', 'Free add-ons are only allowed for consultation and service categories.');
        }
    }

    /**
     * Validate consultation requirements
     */
    private function validateConsultationRequirements($validator): void
    {
        $requiresConsultation = $this->input('requires_consultation', false);
        $consultationDuration = $this->input('consultation_duration_minutes');
        $category = $this->input('category');

        if ($requiresConsultation && !$consultationDuration) {
            $validator->errors()->add('consultation_duration_minutes', 'Consultation duration is required when consultation is required.');
        }

        if ($consultationDuration && !$requiresConsultation) {
            $validator->errors()->add('requires_consultation', 'Consultation requirement must be enabled when consultation duration is specified.');
        }

        // Consultation category should require consultation
        if ($category === 'consultation' && !$requiresConsultation) {
            $validator->errors()->add('requires_consultation', 'Consultation category add-ons must require consultation.');
        }
    }

    /**
     * Validate service relationship constraints
     */
    private function validateServiceRelationshipConstraints($validator): void
    {
        $incompatibleWith = $this->input('incompatible_with', []);
        $prerequisiteAddOns = $this->input('prerequisite_add_ons', []);

        // Check for circular dependencies in prerequisites
        if (!empty($prerequisiteAddOns)) {
            foreach ($prerequisiteAddOns as $prerequisiteId) {
                // This would need more complex logic to check for circular dependencies
                // For now, just ensure they're not the same (which will be validated later)
            }
        }

        // Ensure add-on is not incompatible with its prerequisites
        $conflictingAddOns = array_intersect($incompatibleWith, $prerequisiteAddOns);
        if (!empty($conflictingAddOns)) {
            $validator->errors()->add('incompatible_with', 'Add-on cannot be incompatible with its prerequisite add-ons.');
        }
    }

    /**
     * Validate venue requirements
     */
    private function validateVenueRequirements($validator): void
    {
        $venueRequirements = $this->input('venue_requirements', []);
        $locationRestrictions = $this->input('location_restrictions', []);

        if (!empty($venueRequirements)) {
            // Outdoor-only add-ons shouldn't have ceiling height requirements
            if (in_array('outdoor_only', $locationRestrictions) &&
                isset($venueRequirements['min_ceiling_height'])) {
                $validator->errors()->add('venue_requirements.min_ceiling_height',
                    'Outdoor-only add-ons should not have ceiling height requirements.');
            }

            // Validate reasonable ceiling height
            if (isset($venueRequirements['min_ceiling_height']) &&
                $venueRequirements['min_ceiling_height'] > 10) {
                $validator->errors()->add('venue_requirements.min_ceiling_height',
                    'Minimum ceiling height seems unreasonably high.');
            }

            // Validate reasonable floor area
            if (isset($venueRequirements['min_floor_area']) &&
                $venueRequirements['min_floor_area'] > 1000) {
                $validator->errors()->add('venue_requirements.min_floor_area',
                    'Minimum floor area seems unreasonably large.');
            }
        }
    }

    /**
     * Validate seasonal availability
     */
    private function validateSeasonalAvailability($validator): void
    {
        $isSeasonal = $this->input('is_seasonal', false);
        $availableFrom = $this->input('available_from');
        $availableUntil = $this->input('available_until');

        if ($isSeasonal && (!$availableFrom || !$availableUntil)) {
            $validator->errors()->add('available_from', 'Seasonal add-ons must have both available from and until dates.');
            $validator->errors()->add('available_until', 'Seasonal add-ons must have both available from and until dates.');
        }

        if (!$isSeasonal && ($availableFrom || $availableUntil)) {
            $validator->errors()->add('is_seasonal', 'Add-on should be marked as seasonal when availability dates are provided.');
        }
    }

    /**
     * Validate business logic
     */
    private function validateBusinessLogic($validator): void
    {
        $isRequired = $this->input('is_required', false);
        $isOptional = $this->input('is_optional', true);
        $canBeAddedLastMinute = $this->input('can_be_added_last_minute', true);
        $minAdvanceBookingHours = $this->input('min_advance_booking_hours');
        $leadTimeHours = $this->input('lead_time_hours', 0);

        // Required and optional are mutually exclusive
        if ($isRequired && $isOptional) {
            $validator->errors()->add('is_optional', 'Add-on cannot be both required and optional.');
        }

        // Last minute additions conflict with advance booking requirements
        if ($canBeAddedLastMinute && $minAdvanceBookingHours > 24) {
            $validator->errors()->add('can_be_added_last_minute',
                'Add-on cannot be added last minute if it requires more than 24 hours advance booking.');
        }

        // Lead time should be reasonable compared to advance booking
        if ($leadTimeHours > $minAdvanceBookingHours) {
            $validator->errors()->add('lead_time_hours',
                'Lead time cannot exceed minimum advance booking time.');
        }
    }

    /**
     * Validate add-on limits per service
     */
    private function validateAddOnLimits($validator): void
    {
        $service = $this->route('service');

        if ($service) {
            $currentAddOnCount = $service->addOns()->count();
            $maxAddOnsPerService = 20; // Business rule

            if ($currentAddOnCount >= $maxAddOnsPerService) {
                $validator->errors()->add('name', "Maximum number of add-ons ({$maxAddOnsPerService}) reached for this service.");
            }

            // Check for duplicate names within the same service
            $existingAddOn = $service->addOns()
                ->where('name', $this->input('name'))
                ->exists();

            if ($existingAddOn) {
                $validator->errors()->add('name', 'An add-on with this name already exists for this service.');
            }
        }
    }

    /**
     * Validate category-specific rules
     */
    private function validateCategorySpecificRules($validator): void
    {
        $category = $this->input('category');
        $durationMinutes = $this->input('duration_minutes', 0);
        $setupTimeMinutes = $this->input('setup_time_minutes', 0);
        $requiresConsultation = $this->input('requires_consultation', false);

        switch ($category) {
            case 'consultation':
                if (!$requiresConsultation) {
                    $validator->errors()->add('requires_consultation', 'Consultation category add-ons must require consultation.');
                }
                break;

            case 'equipment':
                if ($setupTimeMinutes === 0) {
                    $validator->errors()->add('setup_time_minutes', 'Equipment add-ons should specify setup time.');
                }
                break;

            case 'service':
                if ($durationMinutes === 0) {
                    $validator->errors()->add('duration_minutes', 'Service add-ons should specify additional duration.');
                }
                break;

            case 'decoration':
                // Decoration add-ons typically need setup time
                if ($setupTimeMinutes === 0) {
                    $validator->errors()->add('setup_time_minutes', 'Decoration add-ons typically require setup time.');
                }
                break;

            case 'premium':
                // Premium add-ons should have a significant price
                if ($this->input('price', 0) < 1000) { // £10.00
                    $validator->errors()->add('price', 'Premium add-ons should have a significant price (minimum £10.00).');
                }
                break;
        }
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convert price from pounds to pence if needed
        $priceFields = ['price', 'cost_price'];
        foreach ($priceFields as $field) {
            if ($this->has($field) && is_numeric($this->input($field))) {
                $value = $this->input($field);
                if (str_contains((string)$value, '.') || $value < 100) {
                    $this->merge([$field => (int) round($value * 100)]);
                }
            }
        }

        // Set default values
        $this->mergeIfMissing([
            'is_active' => true,
            'is_required' => false,
            'is_optional' => true,
            'is_seasonal' => false,
            'unlimited_quantity' => false,
            'requires_consultation' => false,
            'can_be_added_last_minute' => true,
            'is_taxable' => true,
            'display_priority' => 'normal',
            'sort_order' => 0,
            'min_quantity' => 1,
            'default_quantity' => 1,
            'duration_minutes' => 0,
            'setup_time_minutes' => 0,
            'lead_time_hours' => 0,
        ]);

        // Convert boolean fields
        $booleanFields = [
            'unlimited_quantity', 'is_active', 'is_required', 'is_optional',
            'is_seasonal', 'requires_consultation', 'can_be_added_last_minute', 'is_taxable'
        ];

        foreach ($booleanFields as $field) {
            if ($this->has($field)) {
                $this->merge([$field => $this->boolean($field)]);
            }
        }

        // Handle venue requirements boolean fields
        if ($this->has('venue_requirements')) {
            $venueRequirements = $this->input('venue_requirements');
            $venueBooleanFields = ['requires_electricity', 'requires_water', 'requires_parking'];

            foreach ($venueBooleanFields as $field) {
                if (isset($venueRequirements[$field])) {
                    $venueRequirements[$field] = filter_var($venueRequirements[$field], FILTER_VALIDATE_BOOLEAN);
                }
            }

            $this->merge(['venue_requirements' => $venueRequirements]);
        }
    }
}
