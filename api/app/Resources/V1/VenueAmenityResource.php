<?php

namespace App\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VenueAmenityResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'service_location_id' => $this->service_location_id,

            // Basic amenity information
            'amenity_type' => $this->amenity_type,
            'amenity_type_display' => $this->getAmenityTypeDisplay(),
            'amenity_category' => $this->getAmenityCategory(),
            'name' => $this->name,
            'description' => $this->description,

            // Availability and booking information
            'included_in_booking' => $this->included_in_booking,
            'additional_cost' => $this->additional_cost,
            'formatted_cost' => $this->getFormattedCost(),
            'cost_display' => $this->getCostDisplay(),
            'quantity_available' => $this->quantity_available,
            'quantity_display' => $this->getQuantityDisplay(),

            // Advance notice requirements
            'advance_notice' => [
                'required' => $this->requires_advance_notice,
                'hours_required' => $this->notice_hours_required,
                'formatted_notice' => $this->getFormattedNoticeRequirement(),
                'booking_deadline' => $this->getBookingDeadline(),
                'notice_display' => $this->getNoticeDisplay(),
            ],

            // Technical specifications
            'specifications' => $this->getFormattedSpecifications(),
            'dimensions' => $this->getDimensionsInfo(),
            'power_requirements' => $this->getPowerRequirements(),
            'setup_requirements' => $this->getSetupRequirements(),
            'environmental_specs' => $this->getEnvironmentalSpecs(),

            // Usage and safety information
            'usage_instructions' => $this->usage_instructions,
            'formatted_instructions' => $this->getFormattedInstructions(),
            'restrictions' => $this->restrictions,
            'safety_requirements' => $this->getSafetyRequirements(),
            'handling_requirements' => $this->getHandlingRequirements(),

            // Status and organization
            'is_active' => $this->is_active,
            'status_display' => $this->getStatusDisplay(),
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Service location relationship
            'service_location' => $this->whenLoaded('serviceLocation', function () {
                return [
                    'id' => $this->serviceLocation->id,
                    'name' => $this->serviceLocation->name,
                    'type' => $this->serviceLocation->type,
                ];
            }),

            // Compatibility and recommendations
            'compatibility' => [
                'balloon_arch_suitable' => $this->isBalloonArchSuitable(),
                'outdoor_suitable' => $this->isOutdoorSuitable(),
                'indoor_suitable' => $this->isIndoorSuitable(),
                'weather_resistant' => $this->isWeatherResistant(),
                'setup_complexity' => $this->getSetupComplexity(),
                'skill_level_required' => $this->getRequiredSkillLevel(),
            ],

            // Availability and booking info
            'booking_info' => [
                'can_book_now' => $this->canBookNow(),
                'next_available' => $this->getNextAvailableDate(),
                'booking_window' => $this->getBookingWindow(),
                'peak_demand_periods' => $this->getPeakDemandPeriods(),
            ],

            // Pricing and discounts
            'pricing_info' => [
                'base_cost' => $this->additional_cost,
                'formatted_base_cost' => $this->getFormattedCost(),
                'quantity_discounts' => $this->getQuantityDiscounts(),
                'seasonal_pricing' => $this->getSeasonalPricing(),
                'package_deals' => $this->getPackageDeals(),
            ],

            // For admin view only
            'admin_info' => $this->when($this->isAdminView($request), function () {
                return [
                    'usage_statistics' => $this->getUsageStatistics(),
                    'maintenance_info' => $this->getMaintenanceInfo(),
                    'optimization_suggestions' => $this->getOptimizationSuggestions(),
                    'cost_analysis' => $this->getCostAnalysis(),
                ];
            }),

            // Public display information
            'display_info' => [
                'icon' => $this->getAmenityIcon(),
                'color_scheme' => $this->getColorScheme(),
                'featured' => $this->isFeatured(),
                'popular' => $this->isPopular(),
                'recommended_for' => $this->getRecommendedFor(),
                'tags' => $this->getTags(),
            ],

            // Balloon arch specific information
            'balloon_arch_info' => $this->when($this->isBalloonArchRelated(), function () {
                return [
                    'balloon_compatibility' => $this->getBalloonCompatibility(),
                    'arch_support_capability' => $this->getArchSupportCapability(),
                    'wind_resistance' => $this->getWindResistance()
                ];
            }),
        ];
    }

    /**
     * Get amenity type display name
     */
    private function getAmenityTypeDisplay(): string
    {
        return match ($this->amenity_type) {
            'equipment' => 'Equipment',
            'furniture' => 'Furniture',
            'infrastructure' => 'Infrastructure',
            'service' => 'Service',
            'restriction' => 'Restriction',
            default => ucfirst($this->amenity_type),
        };
    }

    /**
     * Get amenity category
     */
    private function getAmenityCategory(): string
    {
        $name = strtolower($this->name);

        // Balloon arch specific categories
        if (str_contains($name, 'balloon') || str_contains($name, 'arch')) {
            return 'balloon_arch';
        }

        // Equipment categories
        if ($this->amenity_type === 'equipment') {
            if (str_contains($name, 'ladder') || str_contains($name, 'scaffold')) return 'access_equipment';
            if (str_contains($name, 'weight') || str_contains($name, 'anchor')) return 'anchoring_equipment';
            if (str_contains($name, 'power') || str_contains($name, 'electrical')) return 'electrical_equipment';
            if (str_contains($name, 'helium') || str_contains($name, 'gas')) return 'gas_equipment';
            return 'general_equipment';
        }

        // Furniture categories
        if ($this->amenity_type === 'furniture') {
            if (str_contains($name, 'chair') || str_contains($name, 'seat')) return 'seating';
            if (str_contains($name, 'table')) return 'tables';
            if (str_contains($name, 'stage') || str_contains($name, 'platform')) return 'staging';
            return 'general_furniture';
        }

        return $this->amenity_type;
    }

    /**
     * Get formatted cost
     */
    private function getFormattedCost(): string
    {
        if ($this->included_in_booking) {
            return 'Included';
        }

        if ($this->additional_cost === 0) {
            return 'Free';
        }

        return '£' . number_format($this->additional_cost / 100, 2);
    }

    /**
     * Get cost display
     */
    private function getCostDisplay(): string
    {
        $cost = $this->getFormattedCost();

        if ($this->included_in_booking) {
            return $cost . ' in booking';
        }

        if ($this->additional_cost === 0) {
            return $cost . ' add-on';
        }

        return $cost . ' additional';
    }

    /**
     * Get quantity display
     */
    private function getQuantityDisplay(): string
    {
        if (!$this->quantity_available) {
            return 'Available on request';
        }

        $item = $this->quantity_available === 1 ? 'item' : 'items';
        return "{$this->quantity_available} {$item} available";
    }

    /**
     * Get formatted notice requirement
     */
    private function getFormattedNoticeRequirement(): string
    {
        if (!$this->requires_advance_notice) {
            return 'No advance notice required';
        }

        return $this->formatHoursToText($this->notice_hours_required);
    }

    /**
     * Get booking deadline for advance notice
     */
    private function getBookingDeadline(): ?string
    {
        if (!$this->requires_advance_notice) {
            return null;
        }

        // This would calculate based on event date in real implementation
        return 'Calculate based on event date';
    }

    /**
     * Get notice display
     */
    private function getNoticeDisplay(): string
    {
        if (!$this->requires_advance_notice) {
            return 'Book anytime';
        }

        $notice = $this->getFormattedNoticeRequirement();
        return "Book at least {$notice} in advance";
    }

    /**
     * Get formatted specifications
     */
    private function getFormattedSpecifications(): array
    {
        $specs = $this->specifications ?? [];
        $formatted = [];

        // Process each specification category
        foreach ($specs as $category => $value) {
            switch ($category) {
                case 'dimensions':
                    $formatted[$category] = $this->formatDimensions($value);
                    break;
                case 'power_requirements':
                    $formatted[$category] = $this->formatPowerRequirements($value);
                    break;
                case 'setup_time':
                case 'breakdown_time':
                    $formatted[$category] = $this->formatTime($value);
                    break;
                case 'temperature_range':
                    $formatted[$category] = $this->formatTemperatureRange($value);
                    break;
                default:
                    $formatted[$category] = $value;
            }
        }

        return $formatted;
    }

    /**
     * Get dimensions information
     */
    private function getDimensionsInfo(): ?array
    {
        $dimensions = $this->specifications['dimensions'] ?? null;

        if (!$dimensions) {
            return null;
        }

        $info = [
            'raw' => $dimensions,
            'formatted' => $this->formatDimensions($dimensions),
            'units' => $dimensions['units'] ?? 'cm',
            'volume' => $this->calculateVolume($dimensions),
            'footprint' => $this->calculateFootprint($dimensions),
            'size_category' => $this->getSizeCategory($dimensions),
        ];

        // Add weight information if available
        if (isset($dimensions['weight'])) {
            $info['weight'] = [
                'value' => $dimensions['weight'],
                'formatted' => $dimensions['weight'] . 'kg',
                'weight_category' => $this->getWeightCategory($dimensions['weight']),
            ];
        }

        return $info;
    }

    /**
     * Get power requirements
     */
    private function getPowerRequirements(): ?array
    {
        $power = $this->specifications['power_requirements'] ?? null;

        if (!$power) {
            return null;
        }

        return [
            'raw' => $power,
            'formatted' => $this->formatPowerRequirements($power),
            'voltage' => $power['voltage'] ?? null,
            'amperage' => $power['amperage'] ?? null,
            'wattage' => $power['wattage'] ?? null,
            'power_type' => $this->getPowerType($power),
            'safety_category' => $this->getPowerSafetyCategory($power),
        ];
    }

    /**
     * Get setup requirements
     */
    private function getSetupRequirements(): array
    {
        $specs = $this->specifications ?? [];

        return [
            'setup_time' => [
                'minutes' => $specs['setup_time'] ?? null,
                'formatted' => isset($specs['setup_time']) ? $this->formatTime($specs['setup_time']) : null,
            ],
            'breakdown_time' => [
                'minutes' => $specs['breakdown_time'] ?? null,
                'formatted' => isset($specs['breakdown_time']) ? $this->formatTime($specs['breakdown_time']) : null,
            ],
            'total_time' => [
                'minutes' => ($specs['setup_time'] ?? 0) + ($specs['breakdown_time'] ?? 0),
                'formatted' => $this->formatTime(($specs['setup_time'] ?? 0) + ($specs['breakdown_time'] ?? 0)),
            ],
            'complexity_level' => $this->getSetupComplexity(),
            'tools_required' => $this->getRequiredTools(),
            'personnel_required' => $this->getRequiredPersonnel(),
        ];
    }

    /**
     * Get environmental specifications
     */
    private function getEnvironmentalSpecs(): array
    {
        $specs = $this->specifications ?? [];

        return [
            'weather_resistant' => $specs['weather_resistant'] ?? null,
            'indoor_only' => $specs['indoor_only'] ?? null,
            'max_wind_speed' => $specs['max_wind_speed'] ?? null,
            'temperature_range' => $specs['temperature_range'] ?? null,
            'formatted_temp_range' => isset($specs['temperature_range']) ?
                $this->formatTemperatureRange($specs['temperature_range']) : null,
            'environmental_category' => $this->getEnvironmentalCategory(),
            'weather_limitations' => $this->getWeatherLimitations(),
        ];
    }

    /**
     * Get formatted instructions
     */
    private function getFormattedInstructions(): ?array
    {
        if (!$this->usage_instructions) {
            return null;
        }

        return [
            'raw' => $this->usage_instructions,
            'steps' => $this->parseInstructionSteps($this->usage_instructions),
            'key_points' => $this->extractKeyPoints($this->usage_instructions),
            'warnings' => $this->extractWarnings($this->usage_instructions),
        ];
    }

    /**
     * Get safety requirements
     */
    private function getSafetyRequirements(): array
    {
        $safety = [];

        // Check for electrical safety
        if (isset($this->specifications['power_requirements'])) {
            $safety[] = [
                'category' => 'electrical',
                'requirement' => 'Proper electrical grounding required',
                'severity' => 'high',
                'icon' => '⚡',
            ];
        }

        // Check for weight safety
        $weight = $this->specifications['dimensions']['weight'] ?? 0;
        if ($weight > 50) {
            $safety[] = [
                'category' => 'handling',
                'requirement' => 'Heavy item - requires proper lifting technique',
                'severity' => 'medium',
                'icon' => '⚠️',
            ];
        }

        // Check for height safety
        $height = $this->specifications['dimensions']['height'] ?? 0;
        if ($height > 2) {
            $safety[] = [
                'category' => 'height',
                'requirement' => 'Working at height - safety equipment recommended',
                'severity' => 'high',
                'icon' => '🪜',
            ];
        }

        // Add restrictions as safety requirements
        if ($this->restrictions) {
            $safety[] = [
                'category' => 'general',
                'requirement' => $this->restrictions,
                'severity' => 'medium',
                'icon' => '🚫',
            ];
        }

        return $safety;
    }

    /**
     * Get handling requirements
     */
    private function getHandlingRequirements(): array
    {
        $requirements = [];

        $weight = $this->specifications['dimensions']['weight'] ?? 0;
        $dimensions = $this->specifications['dimensions'] ?? [];

        // Weight-based requirements
        if ($weight > 100) {
            $requirements[] = 'Mechanical lifting equipment required';
        } elseif ($weight > 25) {
            $requirements[] = 'Two-person lift required';
        }

        // Size-based requirements
        $maxDimension = max(
            $dimensions['length'] ?? 0,
            $dimensions['width'] ?? 0,
            $dimensions['height'] ?? 0
        );

        if ($maxDimension > 300) { // Assuming cm
            $requirements[] = 'Special transport required for large dimensions';
        }

        // Fragility requirements
        if ($this->amenity_type === 'equipment' && str_contains(strtolower($this->name), 'electronic')) {
            $requirements[] = 'Handle with care - electronic components';
        }

        return $requirements;
    }

    /**
     * Get status display
     */
    private function getStatusDisplay(): string
    {
        if (!$this->is_active) {
            return 'Inactive';
        }

        if ($this->quantity_available === 0) {
            return 'Temporarily Unavailable';
        }

        if ($this->requires_advance_notice && $this->notice_hours_required > 72) {
            return 'Advanced Planning Required';
        }

        return 'Available';
    }

    /**
     * Check if balloon arch suitable
     */
    private function isBalloonArchSuitable(): bool
    {
        $name = strtolower($this->name);

        // Direct balloon arch equipment
        if (str_contains($name, 'balloon') || str_contains($name, 'arch')) {
            return true;
        }

        // Supporting equipment
        $supportEquipment = ['ladder', 'weight', 'anchor', 'tie', 'helium', 'compressor'];
        foreach ($supportEquipment as $equipment) {
            if (str_contains($name, $equipment)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if outdoor suitable
     */
    private function isOutdoorSuitable(): bool
    {
        $specs = $this->specifications ?? [];

        // Explicitly indoor only
        if ($specs['indoor_only'] ?? false) {
            return false;
        }

        // Weather resistant equipment is outdoor suitable
        if ($specs['weather_resistant'] ?? false) {
            return true;
        }

        // No specific restrictions mean it's suitable
        return !isset($specs['indoor_only']);
    }

    /**
     * Check if indoor suitable
     */
    private function isIndoorSuitable(): bool
    {
        $specs = $this->specifications ?? [];

        // Check for size restrictions
        $height = $specs['dimensions']['height'] ?? 0;
        if ($height > 300) { // Assuming cm, 3m ceiling height limit
            return false;
        }

        return true;
    }

    /**
     * Check if weather resistant
     */
    private function isWeatherResistant(): bool
    {
        return $this->specifications['weather_resistant'] ?? false;
    }

    /**
     * Get setup complexity
     */
    private function getSetupComplexity(): string
    {
        $setupTime = $this->specifications['setup_time'] ?? 0;
        $hasPower = isset($this->specifications['power_requirements']);
        $isLarge = ($this->specifications['dimensions']['height'] ?? 0) > 200;

        if ($setupTime > 120 || $hasPower || $isLarge) {
            return 'Complex';
        }

        if ($setupTime > 60 || $this->amenity_type === 'equipment') {
            return 'Moderate';
        }

        return 'Simple';
    }

    /**
     * Get required skill level
     */
    private function getRequiredSkillLevel(): string
    {
        $complexity = $this->getSetupComplexity();
        $hasPower = isset($this->specifications['power_requirements']);
        $hasHeight = ($this->specifications['dimensions']['height'] ?? 0) > 200;

        if ($complexity === 'Complex' || $hasPower || $hasHeight) {
            return 'Professional';
        }

        if ($complexity === 'Moderate') {
            return 'Experienced';
        }

        return 'Beginner';
    }

    /**
     * Check if can book now
     */
    private function canBookNow(): bool
    {
        return $this->is_active &&
            $this->quantity_available > 0 &&
            (!$this->requires_advance_notice || $this->notice_hours_required <= 24);
    }

    /**
     * Get next available date
     */
    private function getNextAvailableDate(): string
    {
        if ($this->canBookNow()) {
            return 'Today';
        }

        if ($this->requires_advance_notice) {
            $hoursFromNow = $this->notice_hours_required;
            return now()->addHours($hoursFromNow)->format('Y-m-d');
        }

        return 'Contact for availability';
    }

    /**
     * Get booking window
     */
    private function getBookingWindow(): string
    {
        if (!$this->requires_advance_notice) {
            return 'Same day booking available';
        }

        $notice = $this->getFormattedNoticeRequirement();
        return "Book at least {$notice} in advance";
    }

    /**
     * Get peak demand periods
     */
    private function getPeakDemandPeriods(): array
    {
        // This would be calculated from actual booking data
        return [
            'Wedding season (May-September)',
            'Holiday events (December)',
            'Summer parties (June-August)',
        ];
    }

    /**
     * Get quantity discounts
     */
    private function getQuantityDiscounts(): array
    {
        if ($this->included_in_booking || $this->additional_cost === 0) {
            return [];
        }

        return [
            ['quantity' => 3, 'discount' => '5%'],
            ['quantity' => 5, 'discount' => '10%'],
            ['quantity' => 10, 'discount' => '15%'],
        ];
    }

    /**
     * Get seasonal pricing
     */
    private function getSeasonalPricing(): array
    {
        // This would be configurable in a full implementation
        return [
            'peak_season' => 'May-September (+20%)',
            'off_season' => 'January-March (-10%)',
        ];
    }

    /**
     * Get package deals
     */
    private function getPackageDeals(): array
    {
        // This would be calculated based on related amenities
        return [
            'Balloon Arch Complete Package',
            'Outdoor Event Essentials',
            'Setup Equipment Bundle',
        ];
    }

    /**
     * Check if this is an admin view
     */
    private function isAdminView(Request $request): bool
    {
        return $request->is('*/admin/*') ||
            $request->user()?->hasRole(['super admin', 'admin']);
    }

    /**
     * Get usage statistics (admin only)
     */
    private function getUsageStatistics(): array
    {
        // This would come from actual usage tracking
        return [
            'total_bookings_last_30_days' => 15,
            'utilization_rate' => 65.2,
            'average_booking_duration' => '4 hours',
            'customer_satisfaction' => 4.7,
        ];
    }

    /**
     * Get maintenance information (admin only)
     */
    private function getMaintenanceInfo(): array
    {
        return [
            'last_maintenance' => $this->updated_at?->format('Y-m-d'),
            'next_maintenance_due' => 'Based on usage schedule',
            'maintenance_required' => $this->requiresMaintenance(),
            'maintenance_notes' => $this->getMaintenanceNotes(),
        ];
    }

    /**
     * Get optimization suggestions (admin only)
     */
    private function getOptimizationSuggestions(): array
    {
        $suggestions = [];

        if ($this->quantity_available === 1) {
            $suggestions[] = [
                'type' => 'inventory',
                'suggestion' => 'Consider increasing quantity to meet demand',
                'impact' => 'Medium',
            ];
        }

        if ($this->requires_advance_notice && $this->notice_hours_required > 72) {
            $suggestions[] = [
                'type' => 'availability',
                'suggestion' => 'Long notice period may reduce bookings',
                'impact' => 'Low',
            ];
        }

        return $suggestions;
    }

    /**
     * Get cost analysis (admin only)
     */
    private function getCostAnalysis(): array
    {
        return [
            'revenue_per_booking' => $this->additional_cost,
            'total_revenue_last_30_days' => $this->additional_cost * 15, // Example
            'cost_effectiveness' => 'High',
            'pricing_recommendation' => 'Current pricing optimal',
        ];
    }

    /**
     * Get amenity icon
     */
    private function getAmenityIcon(): string
    {
        $name = strtolower($this->name);

        if (str_contains($name, 'balloon')) return '🎈';
        if (str_contains($name, 'chair')) return '🪑';
        if (str_contains($name, 'table')) return '🗳️';
        if (str_contains($name, 'ladder')) return '🪜';
        if (str_contains($name, 'power') || str_contains($name, 'electrical')) return '⚡';
        if (str_contains($name, 'weight')) return '⚖️';
        if (str_contains($name, 'helium')) return '🎈';

        return match ($this->amenity_type) {
            'equipment' => '🔧',
            'furniture' => '🛋️',
            'service' => '🤝',
            'infrastructure' => '🏗️',
            'restriction' => '🚫',
            default => '📦',
        };
    }

    /**
     * Get color scheme
     */
    private function getColorScheme(): array
    {
        return match ($this->amenity_type) {
            'equipment' => ['primary' => '#3B82F6', 'secondary' => '#DBEAFE'],
            'furniture' => ['primary' => '#8B5CF6', 'secondary' => '#EDE9FE'],
            'service' => ['primary' => '#10B981', 'secondary' => '#D1FAE5'],
            'infrastructure' => ['primary' => '#6B7280', 'secondary' => '#F3F4F6'],
            'restriction' => ['primary' => '#EF4444', 'secondary' => '#FEE2E2'],
            default => ['primary' => '#6B7280', 'secondary' => '#F3F4F6'],
        };
    }

    /**
     * Check if featured
     */
    private function isFeatured(): bool
    {
        // This would be a database field in full implementation
        return $this->isBalloonArchSuitable() || $this->additional_cost > 5000;
    }

    /**
     * Check if popular
     */
    private function isPopular(): bool
    {
        // This would be based on booking frequency
        return in_array($this->amenity_type, ['equipment', 'furniture']);
    }

    /**
     * Get recommended for
     */
    private function getRecommendedFor(): array
    {
        $recommended = [];

        if ($this->isBalloonArchSuitable()) {
            $recommended[] = 'Balloon arch setups';
        }

        if ($this->isOutdoorSuitable()) {
            $recommended[] = 'Outdoor events';
        }

        if ($this->amenity_type === 'furniture') {
            $recommended[] = 'Wedding receptions';
            $recommended[] = 'Corporate events';
        }

        return $recommended;
    }

    /**
     * Get tags
     */
    private function getTags(): array
    {
        $tags = [];

        if ($this->isBalloonArchSuitable()) $tags[] = 'balloon-arch';
        if ($this->isWeatherResistant()) $tags[] = 'weather-resistant';
        if ($this->included_in_booking) $tags[] = 'included';
        if ($this->additional_cost === 0) $tags[] = 'free';
        if ($this->getSetupComplexity() === 'Simple') $tags[] = 'easy-setup';

        return $tags;
    }

    /**
     * Check if balloon arch related
     */
    private function isBalloonArchRelated(): bool
    {
        return $this->isBalloonArchSuitable();
    }

    /**
     * Get balloon compatibility
     */
    private function getBalloonCompatibility(): array
    {
        $name = strtolower($this->name);

        return [
            'latex_balloons' => !str_contains($name, 'sharp'),
            'foil_balloons' => true,
            'helium_compatible' => str_contains($name, 'helium') || str_contains($name, 'weight'),
            'air_filled_compatible' => true,
        ];
    }

    /**
     * Get arch support capability
     */
    private function getArchSupportCapability(): ?array
    {
        if (!$this->isBalloonArchSuitable()) {
            return null;
        }

        $weight = $this->specifications['dimensions']['weight'] ?? 0;
        $height = $this->specifications['dimensions']['height'] ?? 0;

        return [
            'max_arch_size' => $this->calculateMaxArchSize($weight, $height),
            'stability_rating' => $this->getStabilityRating($weight),
            'wind_rating' => $this->getWindResistance(),
        ];
    }

    /**
     * Get wind resistance
     */
    private function getWindResistance(): string
    {
        $maxWind = $this->specifications['max_wind_speed'] ?? 0;

        if ($maxWind > 20) return 'High';
        if ($maxWind > 10) return 'Medium';
        if ($maxWind > 0) return 'Low';

        return 'Indoor only';
    }
}
