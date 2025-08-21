<?php

namespace App\Services\V1\Venues;

use App\Models\VenueAmenity;
use App\Models\ServiceLocation;
use App\Models\Booking;
use App\Models\ClientVenueRequirement;
use App\Constants\BookingStatuses;
use App\Traits\V1\ApiResponses;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VenueAmenityService
{
    use ApiResponses;

    /**
     * Create new venue amenity
     */
    public function createAmenity(array $data): VenueAmenity
    {
        try {
            // Validate service location exists
            $serviceLocation = ServiceLocation::findOrFail($data['service_location_id']);

            return DB::transaction(function () use ($data, $serviceLocation) {
                // Process and validate amenity data
                $processedData = $this->processAmenityData($data);

                // Validate business rules
                $this->validateAmenityRules($processedData);

                // Set sort order if not provided
                if (!isset($processedData['sort_order'])) {
                    $processedData['sort_order'] = $this->getNextSortOrder($serviceLocation, $processedData['amenity_type']);
                }

                // Create amenity
                $amenity = VenueAmenity::create($processedData);

                Log::info('Venue amenity created', [
                    'amenity_id' => $amenity->id,
                    'service_location_id' => $serviceLocation->id,
                    'amenity_type' => $amenity->amenity_type,
                    'name' => $amenity->name,
                ]);

                // Clear related caches
                $this->clearAmenityCaches($serviceLocation);

                return $amenity->fresh();
            });

        } catch (Exception $e) {
            Log::error('Failed to create venue amenity', [
                'service_location_id' => $data['service_location_id'] ?? null,
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            throw $e;
        }
    }

    /**
     * Update venue amenity
     */
    public function updateAmenity(VenueAmenity $amenity, array $data): VenueAmenity
    {
        try {
            return DB::transaction(function () use ($amenity, $data) {
                // Process and validate update data
                $processedData = $this->processAmenityData($data);

                // Validate business rules
                $this->validateAmenityRules($processedData, $amenity->id);

                // Track changes for logging
                $originalData = $amenity->toArray();

                // Update amenity
                $amenity->update($processedData);

                // Log significant changes
                $this->logAmenityChanges($amenity, $originalData, $processedData);

                // Clear related caches
                $this->clearAmenityCaches($amenity->serviceLocation);

                return $amenity->fresh();
            });

        } catch (Exception $e) {
            Log::error('Failed to update venue amenity', [
                'amenity_id' => $amenity->id,
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            throw $e;
        }
    }

    /**
     * Delete venue amenity
     */
    public function deleteAmenity(VenueAmenity $amenity): bool
    {
        try {
            return DB::transaction(function () use ($amenity) {
                $serviceLocationId = $amenity->service_location_id;
                $amenityId = $amenity->id;

                // Check for dependencies
                $dependencies = $this->checkAmenityDependencies($amenity);

                if ($dependencies['has_dependencies']) {
                    throw new Exception(
                        'Cannot delete amenity with active dependencies: ' .
                        implode(', ', $dependencies['dependencies'])
                    );
                }

                // Perform deletion
                $deleted = $amenity->delete();

                if ($deleted) {
                    Log::info('Venue amenity deleted', [
                        'amenity_id' => $amenityId,
                        'service_location_id' => $serviceLocationId,
                        'amenity_name' => $amenity->name,
                    ]);

                    // Clear related caches
                    $serviceLocation = ServiceLocation::find($serviceLocationId);
                    if ($serviceLocation) {
                        $this->clearAmenityCaches($serviceLocation);
                    }
                }

                return $deleted;
            });

        } catch (Exception $e) {
            Log::error('Failed to delete venue amenity', [
                'amenity_id' => $amenity->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Match client requirements to available amenities
     */
    public function matchRequirements(
        ServiceLocation $location,
        array $clientRequirements,
        ?Carbon $eventDate = null
    ): array {
        try {
            $matchResults = [
                'fully_matched' => [],
                'partially_matched' => [],
                'unmatched' => [],
                'additional_amenities' => [],
                'cost_breakdown' => [],
                'notice_requirements' => [],
                'restrictions' => [],
            ];

            // Get all active amenities for the location
            $availableAmenities = VenueAmenity::where('service_location_id', $location->id)
                ->where('is_active', true)
                ->get();

            // Process each client requirement
            foreach ($clientRequirements as $requirement) {
                $match = $this->findAmenityMatch($requirement, $availableAmenities, $eventDate);

                if ($match['match_quality'] === 'full') {
                    $matchResults['fully_matched'][] = $match;
                } elseif ($match['match_quality'] === 'partial') {
                    $matchResults['partially_matched'][] = $match;
                } else {
                    $matchResults['unmatched'][] = $match;
                }
            }

            // Calculate costs and requirements
            $matchResults['cost_breakdown'] = $this->calculateAmenityPricing($matchResults);
            $matchResults['notice_requirements'] = $this->calculateNoticeRequirements($matchResults, $eventDate);
            $matchResults['restrictions'] = $this->compileRestrictions($matchResults);
            $matchResults['additional_amenities'] = $this->suggestAdditionalAmenities($location, $clientRequirements);

            return $matchResults;

        } catch (Exception $e) {
            Log::error('Failed to match amenity requirements', [
                'location_id' => $location->id,
                'error' => $e->getMessage(),
                'requirements' => $clientRequirements,
            ]);

            throw $e;
        }
    }

    /**
     * Get amenity availability for specific dates
     */
    public function getAmenityAvailability(
        VenueAmenity $amenity,
        Carbon $startDate,
        Carbon $endDate
    ): array {
        try {
            $availability = [
                'amenity_id' => $amenity->id,
                'amenity_name' => $amenity->name,
                'total_quantity' => $amenity->quantity_available,
                'date_availability' => [],
                'conflicts' => [],
                'recommendations' => [],
            ];

            $current = $startDate->copy();

            while ($current->lte($endDate)) {
                $dayAvailability = $this->calculateDayAvailability($amenity, $current);
                $availability['date_availability'][$current->toDateString()] = $dayAvailability;

                if ($dayAvailability['available_quantity'] < $amenity->quantity_available) {
                    $availability['conflicts'][] = [
                        'date' => $current->toDateString(),
                        'available' => $dayAvailability['available_quantity'],
                        'total' => $amenity->quantity_available,
                        'conflicting_bookings' => $dayAvailability['conflicting_bookings'],
                    ];
                }

                $current->addDay();
            }

            // Add recommendations for better availability
            $availability['recommendations'] = $this->generateAvailabilityRecommendations($amenity, $availability);

            return $availability;

        } catch (Exception $e) {
            Log::error('Failed to get amenity availability', [
                'amenity_id' => $amenity->id,
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Calculate additional pricing for amenities
     */
    public function calculateAmenityPricing(array $selectedAmenities): array
    {
        try {
            $pricing = [
                'included_amenities' => [],
                'additional_amenities' => [],
                'total_additional_cost' => 0,
                'cost_breakdown' => [],
                'notice_fees' => [],
                'quantity_discounts' => [],
            ];

            foreach ($selectedAmenities as $category => $amenities) {
                if (!is_array($amenities)) continue;

                foreach ($amenities as $amenityData) {
                    $amenity = $amenityData['amenity'] ?? null;
                    $quantity = $amenityData['quantity'] ?? 1;

                    if (!$amenity instanceof VenueAmenity) continue;

                    if ($amenity->included_in_booking) {
                        $pricing['included_amenities'][] = [
                            'amenity_id' => $amenity->id,
                            'name' => $amenity->name,
                            'quantity' => $quantity,
                            'unit_cost' => 0,
                            'total_cost' => 0,
                        ];
                    } else {
                        $unitCost = $amenity->additional_cost;
                        $totalCost = $unitCost * $quantity;

                        // Apply quantity discounts if applicable
                        $discount = $this->calculateQuantityDiscount($amenity, $quantity);
                        if ($discount['applicable']) {
                            $totalCost = $totalCost * (1 - $discount['percentage'] / 100);
                            $pricing['quantity_discounts'][] = $discount;
                        }

                        $pricing['additional_amenities'][] = [
                            'amenity_id' => $amenity->id,
                            'name' => $amenity->name,
                            'quantity' => $quantity,
                            'unit_cost' => $unitCost,
                            'total_cost' => $totalCost,
                            'formatted_cost' => '£' . number_format($totalCost / 100, 2),
                        ];

                        $pricing['total_additional_cost'] += $totalCost;
                    }
                }
            }

            $pricing['formatted_total'] = '£' . number_format($pricing['total_additional_cost'] / 100, 2);

            return $pricing;

        } catch (Exception $e) {
            Log::error('Failed to calculate amenity pricing', [
                'error' => $e->getMessage(),
                'selected_amenities' => $selectedAmenities,
            ]);

            throw $e;
        }
    }

    /**
     * Get amenity usage statistics
     */
    public function getAmenityUsageStats(VenueAmenity $amenity): array
    {
        try {
            $dateRange = $this->getStatsDateRange();

            return [
                'amenity_id' => $amenity->id,
                'period' => 'last_30_days',
                'total_bookings' => $this->getAmenityBookingCount($amenity, $dateRange),
                'utilization_rate' => $this->calculateAmenityUtilization($amenity, $dateRange),
                'revenue_generated' => $this->getAmenityRevenue($amenity, $dateRange),
                'average_quantity_used' => $this->getAverageQuantityUsed($amenity, $dateRange),
                'peak_usage_periods' => $this->getAmenityPeakUsage($amenity, $dateRange),
                'customer_satisfaction' => $this->getAmenitySatisfactionScore($amenity, $dateRange),
                'maintenance_requirements' => $this->getMaintenanceRequirements($amenity),
            ];

        } catch (Exception $e) {
            Log::error('Failed to get amenity usage stats', [
                'amenity_id' => $amenity->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'amenity_id' => $amenity->id,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get amenity requirements and restrictions
     */
    public function getAmenityRequirements(VenueAmenity $amenity): array
    {
        try {
            return [
                'amenity_id' => $amenity->id,
                'advance_notice' => [
                    'required' => $amenity->requires_advance_notice,
                    'hours_required' => $amenity->notice_hours_required,
                    'formatted_notice' => $this->formatNoticeRequirement($amenity),
                ],
                'specifications' => $amenity->specifications ?? [],
                'usage_instructions' => $amenity->usage_instructions,
                'restrictions' => $amenity->restrictions,
                'safety_requirements' => $this->extractSafetyRequirements($amenity),
                'setup_requirements' => $this->extractSetupRequirements($amenity),
                'environmental_requirements' => $this->extractEnvironmentalRequirements($amenity),
                'compatibility' => $this->getAmenityCompatibility($amenity),
            ];

        } catch (Exception $e) {
            Log::error('Failed to get amenity requirements', [
                'amenity_id' => $amenity->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'amenity_id' => $amenity->id,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Bulk update amenity availability
     */
    public function bulkUpdateAvailability(array $amenityUpdates): array
    {
        try {
            $results = [
                'successful_updates' => [],
                'failed_updates' => [],
                'total_processed' => count($amenityUpdates),
            ];

            DB::transaction(function () use ($amenityUpdates, &$results) {
                foreach ($amenityUpdates as $update) {
                    try {
                        $amenity = VenueAmenity::findOrFail($update['amenity_id']);

                        $amenity->update([
                            'quantity_available' => $update['quantity_available'] ?? $amenity->quantity_available,
                            'is_active' => $update['is_active'] ?? $amenity->is_active,
                        ]);

                        $results['successful_updates'][] = [
                            'amenity_id' => $amenity->id,
                            'name' => $amenity->name,
                            'updated_fields' => array_keys($update),
                        ];

                        // Clear cache for this amenity's location
                        $this->clearAmenityCaches($amenity->serviceLocation);

                    } catch (Exception $e) {
                        $results['failed_updates'][] = [
                            'amenity_id' => $update['amenity_id'] ?? 'unknown',
                            'error' => $e->getMessage(),
                        ];
                    }
                }
            });

            Log::info('Bulk amenity availability update completed', [
                'total_processed' => $results['total_processed'],
                'successful' => count($results['successful_updates']),
                'failed' => count($results['failed_updates']),
            ]);

            return $results;

        } catch (Exception $e) {
            Log::error('Failed bulk amenity availability update', [
                'error' => $e->getMessage(),
                'updates' => $amenityUpdates,
            ]);

            throw $e;
        }
    }

    // ================================
    // PRIVATE HELPER METHODS
    // ================================

    /**
     * Process and validate amenity data
     */
    private function processAmenityData(array $data): array
    {
        // Process specifications JSON
        if (isset($data['specifications']) && is_string($data['specifications'])) {
            $data['specifications'] = json_decode($data['specifications'], true);
        }

        // Ensure additional_cost is in pence
        if (isset($data['additional_cost']) && $data['additional_cost'] > 0) {
            // Convert to pence if it looks like pounds (decimal values)
            if (is_float($data['additional_cost']) || strpos($data['additional_cost'], '.') !== false) {
                $data['additional_cost'] = intval($data['additional_cost'] * 100);
            }
        }

        // Validate numeric values
        if (isset($data['quantity_available']) && $data['quantity_available'] < 1) {
            throw new Exception('Quantity available must be at least 1');
        }

        if (isset($data['notice_hours_required']) && $data['notice_hours_required'] < 0) {
            throw new Exception('Notice hours required cannot be negative');
        }

        return $data;
    }

    /**
     * Validate amenity business rules
     */
    private function validateAmenityRules(array $data, ?int $excludeId = null): void
    {
        // Validate amenity type
        $validTypes = ['equipment', 'furniture', 'infrastructure', 'service', 'restriction'];
        if (!in_array($data['amenity_type'], $validTypes)) {
            throw new Exception('Invalid amenity type');
        }

        // Check for duplicate names within the same location and type
        $duplicate = VenueAmenity::where('service_location_id', $data['service_location_id'])
            ->where('amenity_type', $data['amenity_type'])
            ->where('name', $data['name'])
            ->when($excludeId, function ($query, $excludeId) {
                return $query->where('id', '!=', $excludeId);
            })
            ->exists();

        if ($duplicate) {
            throw new Exception('An amenity with this name already exists for this location and type');
        }

        // Validate specifications based on amenity type
        $this->validateTypeSpecificRules($data);
    }

    /**
     * Validate type-specific business rules
     */
    private function validateTypeSpecificRules(array $data): void
    {
        $specifications = $data['specifications'] ?? [];

        switch ($data['amenity_type']) {
            case 'equipment':
                // Equipment should have setup time
                if (!isset($specifications['setup_time'])) {
                    throw new Exception('Equipment amenities should specify setup time');
                }
                break;

            case 'furniture':
                // Furniture should have dimensions
                if (!isset($specifications['dimensions'])) {
                    throw new Exception('Furniture amenities should include dimensions');
                }
                break;

            case 'infrastructure':
                // Infrastructure is typically included
                if (!($data['included_in_booking'] ?? false)) {
                    Log::warning('Infrastructure amenity not marked as included in booking', [
                        'amenity_name' => $data['name'],
                    ]);
                }
                break;

            case 'restriction':
                // Restrictions shouldn't have cost
                if (($data['additional_cost'] ?? 0) > 0) {
                    throw new Exception('Restriction amenities should not have additional cost');
                }
                break;
        }
    }

    /**
     * Get next sort order for amenity type
     */
    private function getNextSortOrder(ServiceLocation $location, string $amenityType): int
    {
        $maxOrder = VenueAmenity::where('service_location_id', $location->id)
            ->where('amenity_type', $amenityType)
            ->max('sort_order');

        return ($maxOrder ?? 0) + 10;
    }

    /**
     * Check amenity dependencies before deletion
     */
    private function checkAmenityDependencies(VenueAmenity $amenity): array
    {
        $dependencies = [];
        $hasDependencies = false;

        // Check for active bookings using this amenity
        $activeBookings = Booking::where('service_location_id', $amenity->service_location_id)
            ->whereIn('status', [BookingStatuses::PENDING, BookingStatuses::CONFIRMED])
            ->where('scheduled_at', '>', now())
            ->count();

        // Note: This is simplified - in a full implementation, you'd have a
        // booking_amenities pivot table to track specific amenity usage
        if ($activeBookings > 0) {
            $dependencies[] = "Future bookings at this location ({$activeBookings})";
            $hasDependencies = true;
        }

        return [
            'has_dependencies' => $hasDependencies,
            'dependencies' => $dependencies,
        ];
    }

    /**
     * Find matching amenity for a client requirement
     */
    private function findAmenityMatch(
        array $requirement,
        Collection $availableAmenities,
        ?Carbon $eventDate
    ): array {
        $bestMatch = [
            'requirement' => $requirement,
            'amenity' => null,
            'match_quality' => 'none',
            'match_score' => 0,
            'availability' => [],
            'cost' => 0,
            'notes' => [],
        ];

        foreach ($availableAmenities as $amenity) {
            $matchScore = $this->calculateMatchScore($requirement, $amenity);

            if ($matchScore['score'] > $bestMatch['match_score']) {
                $bestMatch = [
                    'requirement' => $requirement,
                    'amenity' => $amenity,
                    'match_quality' => $matchScore['quality'],
                    'match_score' => $matchScore['score'],
                    'quantity' => $requirement['quantity'] ?? 1,
                    'availability' => $eventDate ? $this->calculateDayAvailability($amenity, $eventDate) : [],
                    'cost' => $amenity->additional_cost * ($requirement['quantity'] ?? 1),
                    'notes' => $matchScore['notes'],
                ];
            }
        }

        return $bestMatch;
    }

    /**
     * Calculate match score between requirement and amenity
     */
    private function calculateMatchScore(array $requirement, VenueAmenity $amenity): array
    {
        $score = 0;
        $notes = [];
        $quality = 'none';

        // Name/description matching
        $nameMatch = $this->calculateNameMatch($requirement['name'] ?? '', $amenity->name);
        $score += $nameMatch * 40;

        // Category matching
        if (($requirement['category'] ?? '') === $amenity->amenity_type) {
            $score += 30;
        }

        // Specification matching
        if (isset($requirement['specifications']) && $amenity->specifications) {
            $specMatch = $this->calculateSpecificationMatch($requirement['specifications'], $amenity->specifications);
            $score += $specMatch * 20;
        }

        // Availability matching
        if (isset($requirement['quantity'])) {
            if ($amenity->quantity_available >= $requirement['quantity']) {
                $score += 10;
            } else {
                $notes[] = 'Insufficient quantity available';
            }
        }

        // Determine quality
        if ($score >= 80) {
            $quality = 'full';
        } elseif ($score >= 50) {
            $quality = 'partial';
        }

        return [
            'score' => $score,
            'quality' => $quality,
            'notes' => $notes,
        ];
    }

    /**
     * Calculate name similarity score
     */
    private function calculateNameMatch(string $requirement, string $amenityName): float
    {
        $requirement = strtolower(trim($requirement));
        $amenityName = strtolower(trim($amenityName));

        // Exact match
        if ($requirement === $amenityName) {
            return 1.0;
        }

        // Contains match
        if (strpos($amenityName, $requirement) !== false || strpos($requirement, $amenityName) !== false) {
            return 0.8;
        }

        // Word matching
        $reqWords = explode(' ', $requirement);
        $amenityWords = explode(' ', $amenityName);

        $matchingWords = count(array_intersect($reqWords, $amenityWords));
        $totalWords = max(count($reqWords), count($amenityWords));

        return $totalWords > 0 ? $matchingWords / $totalWords : 0;
    }

    /**
     * Calculate specification matching score
     */
    private function calculateSpecificationMatch(array $reqSpecs, array $amenitySpecs): float
    {
        $totalSpecs = count($reqSpecs);
        $matchingSpecs = 0;

        foreach ($reqSpecs as $key => $value) {
            if (isset($amenitySpecs[$key])) {
                if (is_numeric($value) && is_numeric($amenitySpecs[$key])) {
                    // Numeric comparison with tolerance
                    $tolerance = 0.1;
                    if (abs($value - $amenitySpecs[$key]) / max($value, $amenitySpecs[$key]) <= $tolerance) {
                        $matchingSpecs++;
                    }
                } elseif ($value === $amenitySpecs[$key]) {
                    $matchingSpecs++;
                }
            }
        }

        return $totalSpecs > 0 ? $matchingSpecs / $totalSpecs : 0;
    }

    /**
     * Calculate day availability for an amenity
     */
    private function calculateDayAvailability(VenueAmenity $amenity, Carbon $date): array
    {
        // Get bookings for this location on this date
        $dayBookings = Booking::where('service_location_id', $amenity->service_location_id)
            ->whereDate('scheduled_at', $date)
            ->whereNotIn('status', [BookingStatuses::CANCELLED])
            ->get();

        // For simplicity, assume each booking uses 1 quantity of each amenity
        // In a full implementation, you'd track specific amenity usage per booking
        $usedQuantity = $dayBookings->count();
        $availableQuantity = max(0, $amenity->quantity_available - $usedQuantity);

        return [
            'date' => $date->toDateString(),
            'total_quantity' => $amenity->quantity_available,
            'used_quantity' => $usedQuantity,
            'available_quantity' => $availableQuantity,
            'is_available' => $availableQuantity > 0,
            'conflicting_bookings' => $dayBookings->count(),
        ];
    }

    /**
     * Calculate notice requirements
     */
    private function calculateNoticeRequirements(array $matchResults, ?Carbon $eventDate): array
    {
        $requirements = [];
        $maxNoticeHours = 0;

        foreach (['fully_matched', 'partially_matched'] as $category) {
            foreach (($matchResults[$category] ?? []) as $match) {
                $amenity = $match['amenity'] ?? null;

                if ($amenity && $amenity->requires_advance_notice) {
                    $noticeHours = $amenity->notice_hours_required;
                    $maxNoticeHours = max($maxNoticeHours, $noticeHours);

                    $requirements[] = [
                        'amenity_id' => $amenity->id,
                        'amenity_name' => $amenity->name,
                        'notice_hours' => $noticeHours,
                        'formatted_notice' => $this->formatNoticeRequirement($amenity),
                    ];
                }
            }
        }

        $result = [
            'max_notice_hours' => $maxNoticeHours,
            'formatted_max_notice' => $this->formatHoursToText($maxNoticeHours),
            'individual_requirements' => $requirements,
        ];

        if ($eventDate) {
            $requiredNoticeDate = $eventDate->copy()->subHours($maxNoticeHours);
            $result['booking_deadline'] = $requiredNoticeDate->toISOString();
            $result['booking_deadline_formatted'] = $requiredNoticeDate->format('Y-m-d H:i');
            $result['can_book_now'] = now()->lte($requiredNoticeDate);
        }

        return $result;
    }

    /**
     * Compile restrictions from matched amenities
     */
    private function compileRestrictions(array $matchResults): array
    {
        $restrictions = [];

        foreach (['fully_matched', 'partially_matched'] as $category) {
            foreach (($matchResults[$category] ?? []) as $match) {
                $amenity = $match['amenity'] ?? null;

                if ($amenity && $amenity->restrictions) {
                    $restrictions[] = [
                        'amenity_id' => $amenity->id,
                        'amenity_name' => $amenity->name,
                        'restrictions' => $amenity->restrictions,
                    ];
                }
            }
        }

        return $restrictions;
    }

    /**
     * Suggest additional amenities that might be useful
     */
    private function suggestAdditionalAmenities(ServiceLocation $location, array $clientRequirements): array
    {
        $suggestions = [];

        // Get popular amenities for this location
        $popularAmenities = VenueAmenity::where('service_location_id', $location->id)
            ->where('is_active', true)
            ->where('amenity_type', '!=', 'restriction')
            ->orderBy('sort_order')
            ->limit(5)
            ->get();

        foreach ($popularAmenities as $amenity) {
            // Check if this amenity is already matched
            $alreadyMatched = false;
            foreach ($clientRequirements as $req) {
                if (stripos($amenity->name, $req['name'] ?? '') !== false) {
                    $alreadyMatched = true;
                    break;
                }
            }

            if (!$alreadyMatched) {
                $suggestions[] = [
                    'amenity' => $amenity,
                    'reason' => 'Popular choice for similar events',
                    'cost' => $amenity->additional_cost,
                    'formatted_cost' => '£' . number_format($amenity->additional_cost / 100, 2),
                ];
            }
        }

        return $suggestions;
    }

    /**
     * Calculate quantity discounts
     */
    private function calculateQuantityDiscount(VenueAmenity $amenity, int $quantity): array
    {
        $discount = [
            'applicable' => false,
            'percentage' => 0,
            'description' => '',
        ];

        // Simple quantity discount rules
        if ($quantity >= 10) {
            $discount = [
                'applicable' => true,
                'percentage' => 15,
                'description' => '15% discount for 10+ quantities',
            ];
        } elseif ($quantity >= 5) {
            $discount = [
                'applicable' => true,
                'percentage' => 10,
                'description' => '10% discount for 5+ quantities',
            ];
        } elseif ($quantity >= 3) {
            $discount = [
                'applicable' => true,
                'percentage' => 5,
                'description' => '5% discount for 3+ quantities',
            ];
        }

        return $discount;
    }

    /**
     * Get statistics date range
     */
    private function getStatsDateRange(): array
    {
        return [
            'start' => now()->subDays(30)->startOfDay(),
            'end' => now()->endOfDay(),
        ];
    }

    /**
     * Get amenity booking count
     */
    private function getAmenityBookingCount(VenueAmenity $amenity, array $dateRange): int
    {
        // Simplified - in full implementation, you'd track specific amenity usage
        return Booking::where('service_location_id', $amenity->service_location_id)
            ->whereBetween('scheduled_at', [$dateRange['start'], $dateRange['end']])
            ->whereIn('status', [BookingStatuses::COMPLETED])
            ->count();
    }

    /**
     * Calculate amenity utilization rate
     */
    private function calculateAmenityUtilization(VenueAmenity $amenity, array $dateRange): float
    {
        $totalPossibleUse = $amenity->quantity_available * $dateRange['end']->diffInDays($dateRange['start']);
        $actualUsage = $this->getAmenityBookingCount($amenity, $dateRange);

        return $totalPossibleUse > 0 ? round(($actualUsage / $totalPossibleUse) * 100, 1) : 0;
    }

    /**
     * Get amenity revenue
     */
    private function getAmenityRevenue(VenueAmenity $amenity, array $dateRange): array
    {
        $bookingCount = $this->getAmenityBookingCount($amenity, $dateRange);
        $estimatedRevenue = $bookingCount * $amenity->additional_cost;

        return [
            'total_revenue' => $estimatedRevenue,
            'formatted_revenue' => '£' . number_format($estimatedRevenue / 100, 2),
            'booking_count' => $bookingCount,
            'average_per_booking' => $amenity->additional_cost,
        ];
    }

    /**
     * Get average quantity used per booking
     */
    private function getAverageQuantityUsed(VenueAmenity $amenity, array $dateRange): float
    {
        // Simplified - would need actual tracking data
        return 1.0;
    }

    /**
     * Get peak usage periods for amenity
     */
    private function getAmenityPeakUsage(VenueAmenity $amenity, array $dateRange): array
    {
        $bookings = Booking::where('service_location_id', $amenity->service_location_id)
            ->whereBetween('scheduled_at', [$dateRange['start'], $dateRange['end']])
            ->whereIn('status', [BookingStatuses::COMPLETED])
            ->get();

        $dayOfWeekUsage = [];
        $hourOfDayUsage = [];

        foreach ($bookings as $booking) {
            $dayOfWeek = $booking->scheduled_at->format('l');
            $hourOfDay = $booking->scheduled_at->format('H:00');

            $dayOfWeekUsage[$dayOfWeek] = ($dayOfWeekUsage[$dayOfWeek] ?? 0) + 1;
            $hourOfDayUsage[$hourOfDay] = ($hourOfDayUsage[$hourOfDay] ?? 0) + 1;
        }

        arsort($dayOfWeekUsage);
        arsort($hourOfDayUsage);

        return [
            'peak_days' => array_slice($dayOfWeekUsage, 0, 3, true),
            'peak_hours' => array_slice($hourOfDayUsage, 0, 3, true),
        ];
    }

    /**
     * Get amenity satisfaction score
     */
    private function getAmenitySatisfactionScore(VenueAmenity $amenity, array $dateRange): array
    {
        // Placeholder - would integrate with review/feedback system
        return [
            'average_score' => 4.2,
            'total_reviews' => 15,
            'score_distribution' => [
                5 => 8,
                4 => 5,
                3 => 2,
                2 => 0,
                1 => 0,
            ],
        ];
    }

    /**
     * Get maintenance requirements
     */
    private function getMaintenanceRequirements(VenueAmenity $amenity): array
    {
        $requirements = [];

        $specifications = $amenity->specifications ?? [];

        // Equipment maintenance
        if ($amenity->amenity_type === 'equipment') {
            $requirements[] = [
                'type' => 'regular_inspection',
                'frequency' => 'monthly',
                'description' => 'Monthly safety and functionality check',
            ];

            if (isset($specifications['power_requirements'])) {
                $requirements[] = [
                    'type' => 'electrical_testing',
                    'frequency' => 'annually',
                    'description' => 'Annual electrical safety testing',
                ];
            }
        }

        // Furniture maintenance
        if ($amenity->amenity_type === 'furniture') {
            $requirements[] = [
                'type' => 'cleaning',
                'frequency' => 'after_each_use',
                'description' => 'Clean and sanitize after each event',
            ];

            $requirements[] = [
                'type' => 'structural_check',
                'frequency' => 'quarterly',
                'description' => 'Check for wear, damage, and stability',
            ];
        }

        return $requirements;
    }

    /**
     * Format notice requirement
     */
    private function formatNoticeRequirement(VenueAmenity $amenity): string
    {
        if (!$amenity->requires_advance_notice) {
            return 'No advance notice required';
        }

        return $this->formatHoursToText($amenity->notice_hours_required);
    }

    /**
     * Format hours to human-readable text
     */
    private function formatHoursToText(int $hours): string
    {
        if ($hours < 24) {
            return "{$hours} hours";
        }

        $days = intval($hours / 24);
        $remainingHours = $hours % 24;

        if ($remainingHours === 0) {
            return $days === 1 ? "1 day" : "{$days} days";
        }

        return $days === 1 ? "1 day, {$remainingHours} hours" : "{$days} days, {$remainingHours} hours";
    }

    /**
     * Extract safety requirements from amenity
     */
    private function extractSafetyRequirements(VenueAmenity $amenity): array
    {
        $safety = [];

        $specifications = $amenity->specifications ?? [];

        // Power safety
        if (isset($specifications['power_requirements'])) {
            $safety[] = [
                'category' => 'electrical',
                'requirement' => 'Proper electrical grounding required',
                'voltage' => $specifications['power_requirements']['voltage'] ?? 'Unknown',
            ];
        }

        // Weight/load safety
        if (isset($specifications['dimensions']['weight']) && $specifications['dimensions']['weight'] > 50) {
            $safety[] = [
                'category' => 'handling',
                'requirement' => 'Heavy item - requires proper lifting technique',
                'weight' => $specifications['dimensions']['weight'] . 'kg',
            ];
        }

        // Height safety
        if (isset($specifications['dimensions']['height']) && $specifications['dimensions']['height'] > 2) {
            $safety[] = [
                'category' => 'height',
                'requirement' => 'Working at height - safety equipment recommended',
                'height' => $specifications['dimensions']['height'] . 'm',
            ];
        }

        // General restrictions
        if ($amenity->restrictions) {
            $safety[] = [
                'category' => 'general',
                'requirement' => $amenity->restrictions,
            ];
        }

        return $safety;
    }

    /**
     * Extract setup requirements from amenity
     */
    private function extractSetupRequirements(VenueAmenity $amenity): array
    {
        $setup = [];

        $specifications = $amenity->specifications ?? [];

        // Setup time
        if (isset($specifications['setup_time'])) {
            $setup['setup_time'] = [
                'minutes' => $specifications['setup_time'],
                'formatted' => $this->formatMinutesToText($specifications['setup_time']),
            ];
        }

        // Breakdown time
        if (isset($specifications['breakdown_time'])) {
            $setup['breakdown_time'] = [
                'minutes' => $specifications['breakdown_time'],
                'formatted' => $this->formatMinutesToText($specifications['breakdown_time']),
            ];
        }

        // Space requirements
        if (isset($specifications['dimensions'])) {
            $setup['space_required'] = $specifications['dimensions'];
        }

        // Power requirements
        if (isset($specifications['power_requirements'])) {
            $setup['power_required'] = $specifications['power_requirements'];
        }

        // Usage instructions
        if ($amenity->usage_instructions) {
            $setup['instructions'] = $amenity->usage_instructions;
        }

        return $setup;
    }

    /**
     * Extract environmental requirements from amenity
     */
    private function extractEnvironmentalRequirements(VenueAmenity $amenity): array
    {
        $environmental = [];

        $specifications = $amenity->specifications ?? [];

        // Temperature requirements
        if (isset($specifications['temperature_range'])) {
            $environmental['temperature'] = $specifications['temperature_range'];
        }

        // Weather requirements
        if (isset($specifications['weather_resistant'])) {
            $environmental['weather_resistant'] = $specifications['weather_resistant'];
        }

        if (isset($specifications['indoor_only'])) {
            $environmental['indoor_only'] = $specifications['indoor_only'];
        }

        if (isset($specifications['max_wind_speed'])) {
            $environmental['max_wind_speed'] = $specifications['max_wind_speed'] . ' mph';
        }

        return $environmental;
    }

    /**
     * Get amenity compatibility information
     */
    private function getAmenityCompatibility(VenueAmenity $amenity): array
    {
        $compatibility = [
            'compatible_with' => [],
            'incompatible_with' => [],
            'recommendations' => [],
        ];

        // Get other amenities at the same location
        $otherAmenities = VenueAmenity::where('service_location_id', $amenity->service_location_id)
            ->where('id', '!=', $amenity->id)
            ->where('is_active', true)
            ->get();

        foreach ($otherAmenities as $other) {
            $compatibility['compatible_with'][] = [
                'amenity_id' => $other->id,
                'name' => $other->name,
                'type' => $other->amenity_type,
                'synergy' => $this->calculateAmenitySynergy($amenity, $other),
            ];
        }

        // Add specific recommendations based on amenity type
        $compatibility['recommendations'] = $this->getCompatibilityRecommendations($amenity);

        return $compatibility;
    }

    /**
     * Calculate synergy between two amenities
     */
    private function calculateAmenitySynergy(VenueAmenity $amenity1, VenueAmenity $amenity2): string
    {
        // Balloon arch specific synergies
        if (stripos($amenity1->name, 'balloon') !== false || stripos($amenity2->name, 'balloon') !== false) {
            if ($amenity1->amenity_type === 'equipment' || $amenity2->amenity_type === 'equipment') {
                return 'Complements balloon setup';
            }
        }

        // Equipment + furniture synergy
        if (($amenity1->amenity_type === 'equipment' && $amenity2->amenity_type === 'furniture') ||
            ($amenity1->amenity_type === 'furniture' && $amenity2->amenity_type === 'equipment')) {
            return 'Equipment and furniture work well together';
        }

        // Service synergy
        if ($amenity1->amenity_type === 'service' || $amenity2->amenity_type === 'service') {
            return 'Service enhancement';
        }

        return 'Compatible';
    }

    /**
     * Get compatibility recommendations
     */
    private function getCompatibilityRecommendations(VenueAmenity $amenity): array
    {
        $recommendations = [];

        switch ($amenity->amenity_type) {
            case 'equipment':
                $recommendations[] = 'Consider adding furniture to complete the setup';
                $recommendations[] = 'Ensure adequate power supply if electrical';
                break;

            case 'furniture':
                $recommendations[] = 'May require additional equipment for setup';
                $recommendations[] = 'Consider weather protection for outdoor events';
                break;

            case 'service':
                $recommendations[] = 'Coordinate timing with other services';
                $recommendations[] = 'Ensure venue access for service providers';
                break;
        }

        // Balloon arch specific recommendations
        if (stripos($amenity->name, 'balloon') !== false) {
            $recommendations[] = 'Ensure adequate ceiling height';
            $recommendations[] = 'Consider wind conditions for outdoor setups';
            $recommendations[] = 'Allow extra time for balloon inflation';
        }

        return $recommendations;
    }

    /**
     * Format minutes to human-readable text
     */
    private function formatMinutesToText(int $minutes): string
    {
        if ($minutes < 60) {
            return "{$minutes} minutes";
        }

        $hours = intval($minutes / 60);
        $remainingMinutes = $minutes % 60;

        if ($remainingMinutes === 0) {
            return $hours === 1 ? "1 hour" : "{$hours} hours";
        }

        return $hours === 1 ? "1 hour, {$remainingMinutes} minutes" : "{$hours} hours, {$remainingMinutes} minutes";
    }

    /**
     * Generate availability recommendations
     */
    private function generateAvailabilityRecommendations(VenueAmenity $amenity, array $availability): array
    {
        $recommendations = [];

        // Check for high conflict periods
        $conflictRate = count($availability['conflicts']) / count($availability['date_availability']);

        if ($conflictRate > 0.3) {
            $recommendations[] = [
                'type' => 'inventory',
                'priority' => 'high',
                'description' => 'Consider increasing quantity available due to high demand',
                'suggested_quantity' => $amenity->quantity_available + 2,
            ];
        }

        // Check for advance notice issues
        if ($amenity->requires_advance_notice && $amenity->notice_hours_required > 72) {
            $recommendations[] = [
                'type' => 'notice',
                'priority' => 'medium',
                'description' => 'Long notice period may reduce bookings - consider reducing if possible',
                'current_notice' => $this->formatNoticeRequirement($amenity),
            ];
        }

        // Check for pricing optimization
        if (!$amenity->included_in_booking && $amenity->additional_cost === 0) {
            $recommendations[] = [
                'type' => 'pricing',
                'priority' => 'low',
                'description' => 'Consider adding cost for premium amenities to increase revenue',
            ];
        }

        return $recommendations;
    }

    /**
     * Clear amenity-related caches
     */
    private function clearAmenityCaches(ServiceLocation $serviceLocation): void
    {
        $cacheKeys = [
            "venue_amenities_{$serviceLocation->id}",
            "amenity_matches_{$serviceLocation->id}_*",
            "amenity_pricing_{$serviceLocation->id}_*",
            "venue_setup_instructions_{$serviceLocation->id}_*",
        ];

        foreach ($cacheKeys as $pattern) {
            if (str_contains($pattern, '*')) {
                // Clear pattern-based cache keys
                Cache::flush(); // For simplicity, flush all cache. In production, use more targeted approach
            } else {
                Cache::forget($pattern);
            }
        }
    }

    /**
     * Log significant amenity changes
     */
    private function logAmenityChanges(VenueAmenity $amenity, array $original, array $updated): void
    {
        $significantFields = [
            'amenity_type', 'additional_cost', 'quantity_available',
            'requires_advance_notice', 'is_active'
        ];

        $changes = [];
        foreach ($significantFields as $field) {
            if (isset($updated[$field]) && ($original[$field] ?? null) !== $updated[$field]) {
                $changes[$field] = [
                    'from' => $original[$field] ?? null,
                    'to' => $updated[$field],
                ];
            }
        }

        if (!empty($changes)) {
            Log::info('Significant amenity changes', [
                'amenity_id' => $amenity->id,
                'changes' => $changes,
            ]);
        }
    }
}
