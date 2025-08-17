<?php

namespace App\Requests\V1;

class UpdateServiceAddOnRequest extends BaseFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->hasPermission('edit_service_addons');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string|max:2000',
            'short_description' => 'nullable|string|max:500',
            'category' => 'sometimes|in:decoration,equipment,service,consultation,premium,seasonal,custom',

            // Pricing
            'price' => 'sometimes|integer|min:0|max:99999999', // in pence
            'cost_price' => 'nullable|integer|min:0|max:99999999', // in pence
            'pricing_type' => 'sometimes|in:fixed,per_unit,per_hour,percentage',
            'pricing_unit' => 'nullable|string|max:50', // e.g., 'per balloon', 'per meter', 'per hour'

            // Quantity and availability
            'min_quantity' => 'nullable|integer|min:1|max:1000',
            'max_quantity' => 'nullable|integer|min:1|max:1000',
            'default_quantity' => 'nullable|integer|min:1|max:1000',
            'unlimited_quantity' => 'sometimes|boolean',

            // Duration and logistics
            'duration_minutes' => 'nullable|integer|min:0|max:480', // additional time for this add-on
            'setup_time_minutes' => 'nullable|integer|min:0|max:240',
            'requires_consultation' => 'sometimes|boolean',
            'consultation_duration_minutes' => 'nullable|integer|min:15|max:120',

            // Availability and constraints
            'is_active' => 'sometimes|boolean',
            'is_required' => 'sometimes|boolean',
            'is_optional' => 'sometimes|boolean',
            'is_seasonal' => 'sometimes|boolean',
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
            'venue_requirements.requires_electricity' => 'sometimes|boolean',
            'venue_requirements.requires_water' => 'sometimes|boolean',
            'venue_requirements.requires_parking' => 'sometimes|boolean',

            // Advance booking and lead time
            'min_advance_booking_hours' => 'nullable|integer|min:1|max:720', // 30 days max
            'lead_time_hours' => 'nullable|integer|min:0|max:168', // 1 week max
            'can_be_added_last_minute' => 'sometimes|boolean',

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
            'is_taxable' => 'sometimes|boolean',

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
            'name.max' => 'Add-on name cannot exceed 255 characters.',
            'description.max' => 'Description cannot exceed 2000 characters.',
            'category.in' => 'Invalid add-on category selected.',
            'price.integer' => 'Price must be a valid amount in pence.',
            'price.min' => 'Price cannot be negative.',
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
            $addOn = $this->route('serviceAddOn');

            // Validate quantity constraints
            $this->validateQuantityConstraints($validator, $addOn);

            // Validate pricing configuration
            $this->validatePricingConfiguration($validator, $addOn);

            // Validate consultation requirements
            $this->validateConsultationRequirements($validator, $addOn);

            // Validate service relationship constraints
            $this->validateServiceRelationshipConstraints($validator, $addOn);

            // Validate venue requirements
            $this->validateVenueRequirements($validator, $addOn);

            // Validate seasonal availability
            $this->validateSeasonalAvailability($validator, $addOn);

            // Validate business logic
            $this->validateBusinessLogic($validator, $addOn);

            // Validate impact on existing bookings
            $this->validateExistingBookingsImpact($validator, $addOn);

            // Validate price changes impact
            $this->validatePriceChangesImpact($validator, $addOn);

            // Validate deactivation constraints
            $this->validateDeactivationConstraints($validator, $addOn);

            // Validate category changes
            $this->validateCategoryChanges($validator, $addOn);

            // Validate name uniqueness
            $this->validateNameUniqueness($validator, $addOn);

            // Validate dependency chain integrity
            $this->validateDependencyChainIntegrity($validator, $addOn);
        });
    }

    /**
     * Validate quantity constraints
     */
    private function validateQuantityConstraints($validator, $addOn): void
    {
        $minQuantity = $this->input('min_quantity', $addOn->min_quantity ?? 1);
        $maxQuantity = $this->input('max_quantity', $addOn->max_quantity);
        $defaultQuantity = $this->input('default_quantity', $addOn->default_quantity ?? 1);
        $unlimitedQuantity = $this->input('unlimited_quantity', $addOn->unlimited_quantity ?? false);

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
    private function validatePricingConfiguration($validator, $addOn): void
    {
        $price = $this->input('price', $addOn->price);
        $costPrice = $this->input('cost_price', $addOn->cost_price);
        $pricingType = $this->input('pricing_type', $addOn->pricing_type);
        $pricingUnit = $this->input('pricing_unit', $addOn->pricing_unit);

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
        $category = $this->input('category', $addOn->category);
        $freeCategoriesAllowed = ['consultation', 'service'];
        if ($price === 0 && !in_array($category, $freeCategoriesAllowed)) {
            $validator->errors()->add('price', 'Free add-ons are only allowed for consultation and service categories.');
        }
    }

    /**
     * Validate consultation requirements
     */
    private function validateConsultationRequirements($validator, $addOn): void
    {
        $requiresConsultation = $this->input('requires_consultation', $addOn->requires_consultation ?? false);
        $consultationDuration = $this->input('consultation_duration_minutes', $addOn->consultation_duration_minutes);
        $category = $this->input('category', $addOn->category);

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
    private function validateServiceRelationshipConstraints($validator, $addOn): void
    {
        $incompatibleWith = $this->input('incompatible_with', $addOn->incompatible_with ?? []);
        $prerequisiteAddOns = $this->input('prerequisite_add_ons', $addOn->prerequisite_add_ons ?? []);

        // Ensure add-on is not incompatible with its prerequisites
        $conflictingAddOns = array_intersect($incompatibleWith, $prerequisiteAddOns);
        if (!empty($conflictingAddOns)) {
            $validator->errors()->add('incompatible_with', 'Add-on cannot be incompatible with its prerequisite add-ons.');
        }

        // Prevent self-reference
        if (in_array($addOn->id, $incompatibleWith)) {
            $validator->errors()->add('incompatible_with', 'Add-on cannot be incompatible with itself.');
        }

        if (in_array($addOn->id, $prerequisiteAddOns)) {
            $validator->errors()->add('prerequisite_add_ons', 'Add-on cannot be a prerequisite of itself.');
        }
    }

    /**
     * Validate venue requirements
     */
    private function validateVenueRequirements($validator, $addOn): void
    {
        $venueRequirements = $this->input('venue_requirements', $addOn->venue_requirements ?? []);
        $locationRestrictions = $this->input('location_restrictions', $addOn->location_restrictions ?? []);

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
    private function validateSeasonalAvailability($validator, $addOn): void
    {
        $isSeasonal = $this->input('is_seasonal', $addOn->is_seasonal ?? false);
        $availableFrom = $this->input('available_from', $addOn->available_from);
        $availableUntil = $this->input('available_until', $addOn->available_until);

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
    private function validateBusinessLogic($validator, $addOn): void
    {
        $isRequired = $this->input('is_required', $addOn->is_required ?? false);
        $isOptional = $this->input('is_optional', $addOn->is_optional ?? true);
        $canBeAddedLastMinute = $this->input('can_be_added_last_minute', $addOn->can_be_added_last_minute ?? true);
        $minAdvanceBookingHours = $this->input('min_advance_booking_hours', $addOn->min_advance_booking_hours);
        $leadTimeHours = $this->input('lead_time_hours', $addOn->lead_time_hours ?? 0);

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
     * Validate impact on existing bookings
     */
    private function validateExistingBookingsImpact($validator, $addOn): void
    {
        $futureBookingsCount = $this->getFutureBookingsCount($addOn);

        if ($futureBookingsCount > 0) {
            // Check critical changes that would affect existing bookings

            // Duration changes
            $newDuration = $this->input('duration_minutes');
            if ($newDuration && $newDuration !== $addOn->duration_minutes) {
                if ($newDuration > $addOn->duration_minutes) {
                    $additionalTime = $newDuration - $addOn->duration_minutes;
                    $validator->errors()->add('duration_minutes',
                        "Increasing duration by {$additionalTime} minutes may conflict with {$futureBookingsCount} existing bookings.");
                }
            }

            // Setup time changes
            $newSetupTime = $this->input('setup_time_minutes');
            if ($newSetupTime && $newSetupTime > ($addOn->setup_time_minutes ?? 0)) {
                $additionalSetup = $newSetupTime - ($addOn->setup_time_minutes ?? 0);
                $validator->errors()->add('setup_time_minutes',
                    "Increasing setup time by {$additionalSetup} minutes may conflict with {$futureBookingsCount} existing bookings.");
            }

            // Location restriction changes
            $newLocationRestrictions = $this->input('location_restrictions');
            if ($newLocationRestrictions && $newLocationRestrictions !== ($addOn->location_restrictions ?? [])) {
                $validator->errors()->add('location_restrictions',
                    "Cannot change location restrictions with {$futureBookingsCount} future bookings that may be at incompatible locations.");
            }

            // Venue requirement changes that add new requirements
            $this->validateVenueRequirementChanges($validator, $addOn, $futureBookingsCount);
        }
    }

    /**
     * Validate venue requirement changes impact
     */
    private function validateVenueRequirementChanges($validator, $addOn, $futureBookingsCount): void
    {
        $newVenueRequirements = $this->input('venue_requirements', []);
        $existingVenueRequirements = $addOn->venue_requirements ?? [];

        // Check if new requirements are being added
        $newRequirements = [];

        if (isset($newVenueRequirements['min_ceiling_height']) &&
            $newVenueRequirements['min_ceiling_height'] > ($existingVenueRequirements['min_ceiling_height'] ?? 0)) {
            $newRequirements[] = 'increased ceiling height requirement';
        }

        if (isset($newVenueRequirements['min_floor_area']) &&
            $newVenueRequirements['min_floor_area'] > ($existingVenueRequirements['min_floor_area'] ?? 0)) {
            $newRequirements[] = 'increased floor area requirement';
        }

        $booleanRequirements = ['requires_electricity', 'requires_water', 'requires_parking'];
        foreach ($booleanRequirements as $requirement) {
            if (isset($newVenueRequirements[$requirement]) &&
                $newVenueRequirements[$requirement] &&
                !($existingVenueRequirements[$requirement] ?? false)) {
                $newRequirements[] = str_replace('requires_', '', $requirement) . ' requirement';
            }
        }

        if (!empty($newRequirements)) {
            $requirementsList = implode(', ', $newRequirements);
            $validator->errors()->add('venue_requirements',
                "Cannot add new venue requirements ({$requirementsList}) with {$futureBookingsCount} future bookings.");
        }
    }

    /**
     * Validate price changes impact
     */
    private function validatePriceChangesImpact($validator, $addOn): void
    {
        $newPrice = $this->input('price');

        if ($newPrice && $newPrice !== $addOn->price) {
            $futureBookingsCount = $this->getFutureBookingsCount($addOn);

            if ($futureBookingsCount > 0) {
                $priceChange = $newPrice - $addOn->price;
                $changeType = $priceChange > 0 ? 'increase' : 'decrease';
                $changeAmount = abs($priceChange);

                // Significant price increases may need customer notification
                if ($priceChange > 0 && $changeAmount > 1000) { // £10.00 increase
                    $validator->errors()->add('price',
                        "Significant price {$changeType} (£" . number_format($changeAmount / 100, 2) . ") with {$futureBookingsCount} future bookings may require customer notification.");
                }
            }
        }
    }

    /**
     * Validate deactivation constraints
     */
    private function validateDeactivationConstraints($validator, $addOn): void
    {
        $isActive = $this->input('is_active');

        if ($isActive === false && $addOn->is_active) {
            $futureBookingsCount = $this->getFutureBookingsCount($addOn);

            if ($futureBookingsCount > 0) {
                $validator->errors()->add('is_active',
                    "Cannot deactivate add-on with {$futureBookingsCount} future bookings.");
            }

            // Check if other add-ons depend on this one
            $dependentAddOns = $this->getDependentAddOnsCount($addOn);
            if ($dependentAddOns > 0) {
                $validator->errors()->add('is_active',
                    "Cannot deactivate add-on that is a prerequisite for {$dependentAddOns} other add-ons.");
            }
        }
    }

    /**
     * Validate category changes
     */
    private function validateCategoryChanges($validator, $addOn): void
    {
        $newCategory = $this->input('category');

        if ($newCategory && $newCategory !== $addOn->category) {
            $futureBookingsCount = $this->getFutureBookingsCount($addOn);

            if ($futureBookingsCount > 0) {
                $validator->errors()->add('category',
                    "Cannot change category with {$futureBookingsCount} future bookings.");
            }

            // Validate category-specific requirements for new category
            $this->validateCategorySpecificRequirements($validator, $newCategory, $addOn);
        }
    }

    /**
     * Validate category-specific requirements
     */
    private function validateCategorySpecificRequirements($validator, $category, $addOn): void
    {
        $durationMinutes = $this->input('duration_minutes', $addOn->duration_minutes ?? 0);
        $setupTimeMinutes = $this->input('setup_time_minutes', $addOn->setup_time_minutes ?? 0);
        $requiresConsultation = $this->input('requires_consultation', $addOn->requires_consultation ?? false);
        $price = $this->input('price', $addOn->price);

        switch ($category) {
            case 'consultation':
                if (!$requiresConsultation) {
                    $validator->errors()->add('requires_consultation', 'Consultation category add-ons must require consultation.');
                }
                break;

            case 'premium':
                if ($price < 1000) { // £10.00
                    $validator->errors()->add('price', 'Premium add-ons should have a significant price (minimum £10.00).');
                }
                break;
        }
    }

    /**
     * Validate name uniqueness within service
     */
    private function validateNameUniqueness($validator, $addOn): void
    {
        if ($this->has('name')) {
            $existingAddOn = $addOn->service->addOns()
                ->where('name', $this->input('name'))
                ->where('id', '!=', $addOn->id)
                ->exists();

            if ($existingAddOn) {
                $validator->errors()->add('name', 'An add-on with this name already exists for this service.');
            }
        }
    }

    /**
     * Validate dependency chain integrity
     */
    private function validateDependencyChainIntegrity($validator, $addOn): void
    {
        $prerequisiteAddOns = $this->input('prerequisite_add_ons', []);

        if (!empty($prerequisiteAddOns)) {
            // Check for circular dependencies
            foreach ($prerequisiteAddOns as $prerequisiteId) {
                if ($this->wouldCreateCircularDependency($addOn->id, $prerequisiteId)) {
                    $validator->errors()->add('prerequisite_add_ons',
                        'Adding this prerequisite would create a circular dependency.');
                    break;
                }
            }
        }
    }

    /**
     * Check if adding a prerequisite would create circular dependency
     */
    private function wouldCreateCircularDependency($addOnId, $prerequisiteId): bool
    {
        // This would need more complex logic to traverse the dependency tree
        // For now, just check direct circular reference
        $prerequisiteAddOn = \App\Models\ServiceAddOn::find($prerequisiteId);

        if ($prerequisiteAddOn && $prerequisiteAddOn->prerequisite_add_ons) {
            return in_array($addOnId, $prerequisiteAddOn->prerequisite_add_ons);
        }

        return false;
    }

    /**
     * Get future bookings count that include this add-on
     */
    private function getFutureBookingsCount($addOn): int
    {
        return \App\Models\BookingAddOn::where('service_add_on_id', $addOn->id)
            ->whereHas('booking', function ($query) {
                $query->whereIn('status', ['pending', 'confirmed'])
                    ->where('scheduled_at', '>', now());
            })
            ->count();
    }

    /**
     * Get count of add-ons that depend on this one
     */
    private function getDependentAddOnsCount($addOn): int
    {
        return \App\Models\ServiceAddOn::where('service_id', $addOn->service_id)
            ->whereJsonContains('prerequisite_add_ons', $addOn->id)
            ->where('is_active', true)
            ->count();
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convert price fields from pounds to pence if needed
        $priceFields = ['price', 'cost_price'];
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
