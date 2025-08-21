<?php

namespace App\Services\V1\Venues;

use App\Models\VenueDetails;
use App\Models\ServiceLocation;
use App\Models\Booking;
use App\Models\VenueAmenity;
use App\Resources\V1\VenueDetailsResource;
use App\Traits\V1\ApiResponses;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VenueDetailsService
{
    use ApiResponses;

    /**
     * Create venue details for a service location
     */
    public function createVenueDetails(array $data): VenueDetails
    {
        try {
            // Validate service location exists
            $serviceLocation = ServiceLocation::findOrFail($data['service_location_id']);

            // Check if venue details already exist
            if ($serviceLocation->venueDetails) {
                throw new Exception('Venue details already exist for this location', 409);
            }

            return DB::transaction(function () use ($data, $serviceLocation) {
                // Process and validate venue data
                $processedData = $this->processVenueData($data);

                // Create venue details
                $venueDetails = VenueDetails::create($processedData);

                // Log venue creation
                Log::info('Venue details created', [
                    'venue_details_id' => $venueDetails->id,
                    'service_location_id' => $serviceLocation->id,
                    'venue_type' => $venueDetails->venue_type,
                ]);

                // Clear related caches
                $this->clearVenueCaches($serviceLocation);

                return $venueDetails->fresh();
            });

        } catch (Exception $e) {
            Log::error('Failed to create venue details', [
                'service_location_id' => $data['service_location_id'] ?? null,
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            throw $e;
        }
    }

    /**
     * Update venue details
     */
    public function updateVenueDetails(VenueDetails $venueDetails, array $data): VenueDetails
    {
        try {
            return DB::transaction(function () use ($venueDetails, $data) {
                // Process and validate update data
                $processedData = $this->processVenueData($data);

                // Track changes for logging
                $originalData = $venueDetails->toArray();

                // Update venue details
                $venueDetails->update($processedData);

                // Log significant changes
                $this->logVenueChanges($venueDetails, $originalData, $processedData);

                // Clear related caches
                $this->clearVenueCaches($venueDetails->serviceLocation);

                return $venueDetails->fresh();
            });

        } catch (Exception $e) {
            Log::error('Failed to update venue details', [
                'venue_details_id' => $venueDetails->id,
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            throw $e;
        }
    }

    /**
     * Delete venue details (soft delete if applicable)
     */
    public function deleteVenueDetails(VenueDetails $venueDetails, bool $forceDelete = false): bool
    {
        try {
            return DB::transaction(function () use ($venueDetails, $forceDelete) {
                $serviceLocationId = $venueDetails->service_location_id;

                // Check for dependent data
                $dependencyCheck = $this->checkVenueDependencies($venueDetails);

                if ($dependencyCheck['has_dependencies'] && !$forceDelete) {
                    throw new Exception(
                        'Cannot delete venue details with active dependencies: ' .
                        implode(', ', $dependencyCheck['dependencies'])
                    );
                }

                // Perform deletion
                $deleted = $venueDetails->delete();

                if ($deleted) {
                    Log::info('Venue details deleted', [
                        'venue_details_id' => $venueDetails->id,
                        'service_location_id' => $serviceLocationId,
                        'force_delete' => $forceDelete,
                    ]);

                    // Clear related caches
                    $serviceLocation = ServiceLocation::find($serviceLocationId);
                    if ($serviceLocation) {
                        $this->clearVenueCaches($serviceLocation);
                    }
                }

                return $deleted;
            });

        } catch (Exception $e) {
            Log::error('Failed to delete venue details', [
                'venue_details_id' => $venueDetails->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Validate venue availability for a specific booking
     */
    public function validateVenueAvailability(
        VenueDetails $venueDetails,
        Carbon $startTime,
        Carbon $endTime,
        array $requirements = []
    ): array {
        try {
            $validation = [
                'available' => true,
                'conflicts' => [],
                'warnings' => [],
                'requirements_met' => true,
                'capacity_check' => true,
            ];

            // Check time-based availability
            $timeConflicts = $this->checkTimeAvailability($venueDetails, $startTime, $endTime);
            if (!empty($timeConflicts)) {
                $validation['available'] = false;
                $validation['conflicts'] = array_merge($validation['conflicts'], $timeConflicts);
            }

            // Check capacity requirements
            if (isset($requirements['guest_count'])) {
                $capacityCheck = $this->validateVenueCapacity($venueDetails, $requirements['guest_count']);
                if (!$capacityCheck['meets_capacity']) {
                    $validation['capacity_check'] = false;
                    $validation['warnings'][] = $capacityCheck['message'];
                }
            }

            // Check specific venue requirements
            if (!empty($requirements)) {
                $requirementCheck = $this->validateVenueRequirements($venueDetails, $requirements);
                if (!$requirementCheck['all_met']) {
                    $validation['requirements_met'] = false;
                    $validation['warnings'] = array_merge($validation['warnings'], $requirementCheck['unmet_requirements']);
                }
            }

            // Check setup/breakdown time requirements
            $setupCheck = $this->validateSetupTime($venueDetails, $startTime, $endTime);
            if (!$setupCheck['adequate_time']) {
                $validation['warnings'][] = $setupCheck['message'];
            }

            return $validation;

        } catch (Exception $e) {
            Log::error('Failed to validate venue availability', [
                'venue_details_id' => $venueDetails->id,
                'start_time' => $startTime->toISOString(),
                'end_time' => $endTime->toISOString(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Get venue capacity information and limitations
     */
    public function getVenueCapacityInfo(VenueDetails $venueDetails): array
    {
        try {
            $serviceLocation = $venueDetails->serviceLocation;

            return [
                'venue_id' => $venueDetails->id,
                'location_id' => $serviceLocation->id,
                'maximum_capacity' => $serviceLocation->max_capacity,
                'recommended_capacity' => $this->getRecommendedCapacity($venueDetails),
                'floor_area_sqm' => $venueDetails->floor_area_sqm,
                'capacity_per_sqm' => $this->calculateCapacityPerSquareMeter($venueDetails),
                'venue_type' => $venueDetails->venue_type,
                'space_style' => $venueDetails->space_style,
                'accessibility_considerations' => [
                    'step_free_access' => $venueDetails->step_free_access,
                    'lift_access' => $venueDetails->lift_access,
                    'stairs_count' => $venueDetails->stairs_count,
                ],
                'setup_considerations' => [
                    'setup_time_minutes' => $venueDetails->setup_time_minutes,
                    'breakdown_time_minutes' => $venueDetails->breakdown_time_minutes,
                    'setup_restrictions' => $venueDetails->setup_restrictions,
                ],
                'limitations' => $this->getVenueLimitations($venueDetails),
            ];

        } catch (Exception $e) {
            Log::error('Failed to get venue capacity info', [
                'venue_details_id' => $venueDetails->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Validate venue configuration completeness and correctness
     */
    public function validateVenueConfiguration(VenueDetails $venueDetails): array
    {
        try {
            $validation = [
                'score' => 0,
                'max_score' => 100,
                'status' => 'incomplete',
                'issues' => [],
                'warnings' => [],
                'recommendations' => [],
                'categories' => [],
            ];

            // Basic information completeness (25 points)
            $basicInfo = $this->validateBasicInformation($venueDetails);
            $validation['categories']['basic_information'] = $basicInfo;
            $validation['score'] += $basicInfo['score'];

            // Safety and accessibility (25 points)
            $safety = $this->validateSafetyAccessibility($venueDetails);
            $validation['categories']['safety_accessibility'] = $safety;
            $validation['score'] += $safety['score'];

            // Setup and logistics (25 points)
            $logistics = $this->validateSetupLogistics($venueDetails);
            $validation['categories']['setup_logistics'] = $logistics;
            $validation['score'] += $logistics['score'];

            // Amenities and features (25 points)
            $amenities = $this->validateAmenities($venueDetails);
            $validation['categories']['amenities_features'] = $amenities;
            $validation['score'] += $amenities['score'];

            // Collect all issues and warnings
            foreach ($validation['categories'] as $category) {
                $validation['issues'] = array_merge($validation['issues'], $category['issues'] ?? []);
                $validation['warnings'] = array_merge($validation['warnings'], $category['warnings'] ?? []);
                $validation['recommendations'] = array_merge($validation['recommendations'], $category['recommendations'] ?? []);
            }

            // Determine overall status
            if ($validation['score'] >= 90) {
                $validation['status'] = 'excellent';
            } elseif ($validation['score'] >= 75) {
                $validation['status'] = 'good';
            } elseif ($validation['score'] >= 50) {
                $validation['status'] = 'adequate';
            } else {
                $validation['status'] = 'incomplete';
            }

            return $validation;

        } catch (Exception $e) {
            Log::error('Failed to validate venue configuration', [
                'venue_details_id' => $venueDetails->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Generate venue setup instructions based on service type and event details
     */
    public function generateSetupInstructions(
        VenueDetails $venueDetails,
        string $serviceType = null,
        array $eventDetails = []
    ): array {
        try {
            $instructions = [
                'venue_id' => $venueDetails->id,
                'service_type' => $serviceType,
                'event_details' => $eventDetails,
                'generated_at' => now()->toISOString(),
                'pre_arrival' => [],
                'setup_sequence' => [],
                'safety_considerations' => [],
                'breakdown_sequence' => [],
                'estimated_times' => [],
                'required_equipment' => [],
                'venue_specific_notes' => [],
            ];

            // Pre-arrival preparation
            $instructions['pre_arrival'] = $this->generatePreArrivalInstructions($venueDetails, $eventDetails);

            // Setup sequence based on service type
            $instructions['setup_sequence'] = $this->generateSetupSequence($venueDetails, $serviceType, $eventDetails);

            // Safety and access considerations
            $instructions['safety_considerations'] = $this->generateSafetyInstructions($venueDetails);

            // Breakdown sequence
            if ($eventDetails['include_breakdown'] ?? true) {
                $instructions['breakdown_sequence'] = $this->generateBreakdownSequence($venueDetails, $serviceType);
            }

            // Time estimates
            $instructions['estimated_times'] = $this->calculateSetupTimes($venueDetails, $serviceType, $eventDetails);

            // Required equipment based on venue
            $instructions['required_equipment'] = $this->getRequiredEquipment($venueDetails, $serviceType);

            // Venue-specific notes
            $instructions['venue_specific_notes'] = $this->getVenueSpecificNotes($venueDetails);

            return $instructions;

        } catch (Exception $e) {
            Log::error('Failed to generate setup instructions', [
                'venue_details_id' => $venueDetails->id,
                'service_type' => $serviceType,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    // ================================
    // PRIVATE HELPER METHODS
    // ================================

    /**
     * Process and validate venue data before saving
     */
    private function processVenueData(array $data): array
    {
        // Ensure proper data types for casted fields
        if (isset($data['room_dimensions']) && is_string($data['room_dimensions'])) {
            $data['room_dimensions'] = json_decode($data['room_dimensions'], true);
        }

        if (isset($data['color_scheme']) && is_string($data['color_scheme'])) {
            $data['color_scheme'] = json_decode($data['color_scheme'], true);
        }

        if (isset($data['power_outlets']) && is_string($data['power_outlets'])) {
            $data['power_outlets'] = json_decode($data['power_outlets'], true);
        }

        if (isset($data['setup_restrictions']) && is_string($data['setup_restrictions'])) {
            $data['setup_restrictions'] = json_decode($data['setup_restrictions'], true);
        }

        if (isset($data['prohibited_items']) && is_string($data['prohibited_items'])) {
            $data['prohibited_items'] = json_decode($data['prohibited_items'], true);
        }

        if (isset($data['venue_contacts']) && is_string($data['venue_contacts'])) {
            $data['venue_contacts'] = json_decode($data['venue_contacts'], true);
        }

        // Validate numeric ranges
        if (isset($data['ceiling_height_meters']) && $data['ceiling_height_meters'] < 0) {
            throw new Exception('Ceiling height cannot be negative');
        }

        if (isset($data['floor_area_sqm']) && $data['floor_area_sqm'] < 0) {
            throw new Exception('Floor area cannot be negative');
        }

        if (isset($data['setup_time_minutes']) && $data['setup_time_minutes'] < 0) {
            throw new Exception('Setup time cannot be negative');
        }

        if (isset($data['breakdown_time_minutes']) && $data['breakdown_time_minutes'] < 0) {
            throw new Exception('Breakdown time cannot be negative');
        }

        return $data;
    }

    /**
     * Check for dependencies before deleting venue details
     */
    private function checkVenueDependencies(VenueDetails $venueDetails): array
    {
        $dependencies = [];
        $hasDependencies = false;

        // Check for active bookings
        $activeBookings = Booking::where('service_location_id', $venueDetails->service_location_id)
            ->whereIn('status', ['pending', 'confirmed', 'in_progress'])
            ->count();

        if ($activeBookings > 0) {
            $dependencies[] = "Active bookings ({$activeBookings})";
            $hasDependencies = true;
        }

        // Check for future bookings
        $futureBookings = Booking::where('service_location_id', $venueDetails->service_location_id)
            ->where('scheduled_at', '>', now())
            ->count();

        if ($futureBookings > 0) {
            $dependencies[] = "Future bookings ({$futureBookings})";
            $hasDependencies = true;
        }

        return [
            'has_dependencies' => $hasDependencies,
            'dependencies' => $dependencies,
            'active_bookings' => $activeBookings ?? 0,
            'future_bookings' => $futureBookings ?? 0,
        ];
    }

    /**
     * Clear venue-related caches
     */
    private function clearVenueCaches(ServiceLocation $serviceLocation): void
    {
        $cacheKeys = [
            "venue_details_{$serviceLocation->id}",
            "venue_capacity_{$serviceLocation->id}",
            "venue_availability_{$serviceLocation->id}",
        ];

        foreach ($cacheKeys as $key) {
            Cache::forget($key);
        }
    }

    /**
     * Log significant venue changes
     */
    private function logVenueChanges(VenueDetails $venueDetails, array $original, array $updated): void
    {
        $significantFields = [
            'venue_type', 'floor_area_sqm', 'ceiling_height_meters',
            'setup_time_minutes', 'breakdown_time_minutes', 'setup_restrictions'
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
            Log::info('Significant venue details changes', [
                'venue_details_id' => $venueDetails->id,
                'changes' => $changes,
            ]);
        }
    }


    /**
     * Get venue booking statistics for the given date range
     */
    private function getVenueBookingStatistics(VenueDetails $venueDetails, array $dateRange): array
    {
        $bookings = Booking::where('service_location_id', $venueDetails->service_location_id)
            ->whereBetween('scheduled_at', [$dateRange['start'], $dateRange['end']])
            ->get();

        $totalBookings = $bookings->count();
        $completedBookings = $bookings->where('status', 'completed')->count();
        $cancelledBookings = $bookings->where('status', 'cancelled')->count();
        $noShowBookings = $bookings->where('status', 'no_show')->count();

        return [
            'total_bookings' => $totalBookings,
            'completed_bookings' => $completedBookings,
            'cancelled_bookings' => $cancelledBookings,
            'no_show_bookings' => $noShowBookings,
            'completion_rate' => $totalBookings > 0 ? round(($completedBookings / $totalBookings) * 100, 1) : 0,
            'cancellation_rate' => $totalBookings > 0 ? round(($cancelledBookings / $totalBookings) * 100, 1) : 0,
            'no_show_rate' => $totalBookings > 0 ? round(($noShowBookings / $totalBookings) * 100, 1) : 0,
        ];
    }

    /**
     * Get venue setup efficiency statistics
     */
    private function getVenueSetupStatistics(VenueDetails $venueDetails, array $dateRange): array
    {
        // This would analyze actual setup times vs planned times
        return [
            'average_setup_time' => $venueDetails->setup_time_minutes,
            'average_breakdown_time' => $venueDetails->breakdown_time_minutes,
            'setup_efficiency_score' => 87.5,
            'common_delays' => [],
            'recommendations' => [],
        ];
    }

    /**
     * Check time-based availability conflicts
     */
    private function checkTimeAvailability(VenueDetails $venueDetails, Carbon $startTime, Carbon $endTime): array
    {
        $conflicts = [];

        // Check for existing bookings that would conflict
        $conflictingBookings = Booking::where('service_location_id', $venueDetails->service_location_id)
            ->where('status', '!=', 'cancelled')
            ->where(function ($query) use ($startTime, $endTime) {
                $query->whereBetween('scheduled_at', [$startTime, $endTime])
                    ->orWhereBetween('ends_at', [$startTime, $endTime])
                    ->orWhere(function ($q) use ($startTime, $endTime) {
                        $q->where('scheduled_at', '<=', $startTime)
                            ->where('ends_at', '>=', $endTime);
                    });
            })
            ->get();

        foreach ($conflictingBookings as $booking) {
            $conflicts[] = [
                'type' => 'booking_conflict',
                'booking_id' => $booking->id,
                'booking_reference' => $booking->booking_reference,
                'scheduled_at' => $booking->scheduled_at->toISOString(),
                'ends_at' => $booking->ends_at->toISOString(),
            ];
        }

        return $conflicts;
    }

    /**
     * Validate venue capacity for guest count
     */
    private function validateVenueCapacity(VenueDetails $venueDetails, int $guestCount): array
    {
        $maxCapacity = $venueDetails->serviceLocation->max_capacity ?? 0;
        $recommendedCapacity = $this->getRecommendedCapacity($venueDetails);

        $result = [
            'meets_capacity' => true,
            'max_capacity' => $maxCapacity,
            'recommended_capacity' => $recommendedCapacity,
            'guest_count' => $guestCount,
            'message' => '',
        ];

        if ($maxCapacity > 0 && $guestCount > $maxCapacity) {
            $result['meets_capacity'] = false;
            $result['message'] = "Guest count ({$guestCount}) exceeds maximum capacity ({$maxCapacity})";
        } elseif ($recommendedCapacity > 0 && $guestCount > $recommendedCapacity) {
            $result['message'] = "Guest count ({$guestCount}) exceeds recommended capacity ({$recommendedCapacity})";
        }

        return $result;
    }

    /**
     * Get recommended capacity based on venue type and space
     */
    private function getRecommendedCapacity(VenueDetails $venueDetails): int
    {
        $maxCapacity = $venueDetails->serviceLocation->max_capacity ?? 0;

        // Return 80% of max capacity as recommended for comfort
        return $maxCapacity > 0 ? intval($maxCapacity * 0.8) : 0;
    }

    /**
     * Calculate capacity per square meter
     */
    private function calculateCapacityPerSquareMeter(VenueDetails $venueDetails): float
    {
        $maxCapacity = $venueDetails->serviceLocation->max_capacity ?? 0;
        $floorArea = $venueDetails->floor_area_sqm ?? 0;

        return ($floorArea > 0 && $maxCapacity > 0) ? round($maxCapacity / $floorArea, 2) : 0;
    }

    /**
     * Get venue limitations and restrictions
     */
    private function getVenueLimitations(VenueDetails $venueDetails): array
    {
        $limitations = [];

        if (!$venueDetails->step_free_access) {
            $limitations[] = 'No step-free access available';
        }

        if (!$venueDetails->lift_access && $venueDetails->stairs_count > 0) {
            $limitations[] = "Requires climbing {$venueDetails->stairs_count} stairs";
        }

        if (!$venueDetails->climate_controlled) {
            $limitations[] = 'No climate control - weather dependent';
        }

        if ($venueDetails->noise_restrictions) {
            $limitations[] = 'Noise restrictions apply';
        }

        if (!empty($venueDetails->prohibited_items)) {
            $limitations[] = 'Restricted items: ' . implode(', ', $venueDetails->prohibited_items);
        }

        return $limitations;
    }

    /**
     * Validate basic venue information completeness
     */
    private function validateBasicInformation(VenueDetails $venueDetails): array
    {
        $score = 0;
        $maxScore = 25;
        $issues = [];
        $warnings = [];

        // Required fields (15 points)
        $requiredFields = [
            'venue_type' => 3,
            'floor_area_sqm' => 3,
            'ceiling_height_meters' => 3,
            'setup_time_minutes' => 3,
            'breakdown_time_minutes' => 3,
        ];

        foreach ($requiredFields as $field => $points) {
            if (!empty($venueDetails->$field)) {
                $score += $points;
            } else {
                $issues[] = "Missing {$field}";
            }
        }

        // Optional but recommended fields (10 points)
        $recommendedFields = [
            'space_style' => 2,
            'color_scheme' => 2,
            'access_instructions' => 2,
            'parking_information' => 2,
            'special_instructions' => 2,
        ];

        foreach ($recommendedFields as $field => $points) {
            if (!empty($venueDetails->$field)) {
                $score += $points;
            } else {
                $warnings[] = "Consider adding {$field}";
            }
        }

        return [
            'score' => $score,
            'max_score' => $maxScore,
            'issues' => $issues,
            'warnings' => $warnings,
        ];
    }

    /**
     * Validate safety and accessibility features
     */
    private function validateSafetyAccessibility(VenueDetails $venueDetails): array
    {
        $score = 0;
        $maxScore = 25;
        $issues = [];
        $warnings = [];

        // Accessibility features (15 points)
        if ($venueDetails->step_free_access) {
            $score += 5;
        } else {
            $warnings[] = 'No step-free access available';
        }

        if ($venueDetails->lift_access) {
            $score += 5;
        }

        if (isset($venueDetails->stairs_count)) {
            $score += 2;
            if ($venueDetails->stairs_count > 10) {
                $warnings[] = 'High stair count may limit accessibility';
            }
        } else {
            $issues[] = 'Stair count not specified';
        }

        if (!empty($venueDetails->power_outlets)) {
            $score += 3;
        } else {
            $issues[] = 'Power outlet information missing';
        }

        // Safety considerations (10 points)
        if ($venueDetails->has_adequate_lighting) {
            $score += 5;
        } else {
            $warnings[] = 'Lighting adequacy not confirmed';
        }

        if (!empty($venueDetails->prohibited_items)) {
            $score += 3;
        }

        if (!empty($venueDetails->venue_contacts)) {
            $score += 2;
        } else {
            $issues[] = 'Emergency contact information missing';
        }

        return [
            'score' => $score,
            'max_score' => $maxScore,
            'issues' => $issues,
            'warnings' => $warnings,
        ];
    }

    /**
     * Validate setup and logistics information
     */
    private function validateSetupLogistics(VenueDetails $venueDetails): array
    {
        $score = 0;
        $maxScore = 25;
        $issues = [];
        $warnings = [];
        $recommendations = [];

        // Setup timing (10 points)
        if ($venueDetails->setup_time_minutes > 0) {
            $score += 5;
            if ($venueDetails->setup_time_minutes < 30) {
                $warnings[] = 'Very short setup time may be insufficient';
            }
        } else {
            $issues[] = 'Setup time not specified';
        }

        if ($venueDetails->breakdown_time_minutes > 0) {
            $score += 5;
        } else {
            $issues[] = 'Breakdown time not specified';
        }

        // Access and logistics (15 points)
        if (!empty($venueDetails->access_instructions)) {
            $score += 5;
        } else {
            $issues[] = 'Access instructions missing';
        }

        if (!empty($venueDetails->loading_instructions)) {
            $score += 5;
        } else {
            $warnings[] = 'Loading instructions not provided';
        }

        if (!empty($venueDetails->parking_information)) {
            $score += 3;
        } else {
            $warnings[] = 'Parking information missing';
        }

        if (!empty($venueDetails->setup_restrictions)) {
            $score += 2;
        } else {
            $recommendations[] = 'Consider documenting setup restrictions';
        }

        return [
            'score' => $score,
            'max_score' => $maxScore,
            'issues' => $issues,
            'warnings' => $warnings,
            'recommendations' => $recommendations,
        ];
    }

    /**
     * Validate amenities and features
     */
    private function validateAmenities(VenueDetails $venueDetails): array
    {
        $score = 0;
        $maxScore = 25;
        $issues = [];
        $warnings = [];
        $recommendations = [];

        // Climate and environment (10 points)
        if (isset($venueDetails->climate_controlled)) {
            $score += 5;
        }

        if (isset($venueDetails->typical_temperature)) {
            $score += 3;
        }

        if (isset($venueDetails->has_adequate_lighting)) {
            $score += 2;
        }

        // Venue amenities via relationship (15 points)
        $amenityCount = $venueDetails->serviceLocation->venueAmenities()->count();
        if ($amenityCount > 0) {
            $score += min(15, $amenityCount * 3); // 3 points per amenity, max 15
        } else {
            $warnings[] = 'No amenities documented';
            $recommendations[] = 'Add venue amenities to improve guest experience';
        }

        return [
            'score' => $score,
            'max_score' => $maxScore,
            'issues' => $issues,
            'warnings' => $warnings,
            'recommendations' => $recommendations,
        ];
    }

    /**
     * Validate venue requirements against client needs
     */
    private function validateVenueRequirements(VenueDetails $venueDetails, array $requirements): array
    {
        $result = [
            'all_met' => true,
            'met_requirements' => [],
            'unmet_requirements' => [],
        ];

        // Check accessibility requirements
        if (isset($requirements['accessibility_needs'])) {
            foreach ($requirements['accessibility_needs'] as $need) {
                switch ($need) {
                    case 'wheelchair_access':
                        if ($venueDetails->step_free_access && $venueDetails->lift_access) {
                            $result['met_requirements'][] = 'Wheelchair access available';
                        } else {
                            $result['unmet_requirements'][] = 'Wheelchair access not available';
                            $result['all_met'] = false;
                        }
                        break;

                    case 'step_free':
                        if ($venueDetails->step_free_access) {
                            $result['met_requirements'][] = 'Step-free access available';
                        } else {
                            $result['unmet_requirements'][] = 'Step-free access not available';
                            $result['all_met'] = false;
                        }
                        break;
                }
            }
        }

        // Check prohibited items
        if (isset($requirements['items_needed'])) {
            $prohibitedItems = $venueDetails->prohibited_items ?? [];
            foreach ($requirements['items_needed'] as $item) {
                if (in_array($item, $prohibitedItems)) {
                    $result['unmet_requirements'][] = "Item '{$item}' is prohibited at this venue";
                    $result['all_met'] = false;
                } else {
                    $result['met_requirements'][] = "Item '{$item}' is permitted";
                }
            }
        }

        return $result;
    }

    /**
     * Validate setup time adequacy
     */
    private function validateSetupTime(VenueDetails $venueDetails, Carbon $startTime, Carbon $endTime): array
    {
        $requiredSetupTime = $venueDetails->setup_time_minutes ?? 60;
        $requiredBreakdownTime = $venueDetails->breakdown_time_minutes ?? 30;

        $eventDuration = $endTime->diffInMinutes($startTime);
        $totalRequiredTime = $requiredSetupTime + $eventDuration + $requiredBreakdownTime;

        return [
            'adequate_time' => true, // This would check against venue availability windows
            'required_setup_minutes' => $requiredSetupTime,
            'required_breakdown_minutes' => $requiredBreakdownTime,
            'total_required_minutes' => $totalRequiredTime,
            'message' => "Requires {$requiredSetupTime} min setup + {$requiredBreakdownTime} min breakdown",
        ];
    }

    /**
     * Generate pre-arrival instructions
     */
    private function generatePreArrivalInstructions(VenueDetails $venueDetails, array $eventDetails): array
    {
        $instructions = [];

        // Access and parking
        if ($venueDetails->access_instructions) {
            $instructions[] = [
                'category' => 'access',
                'instruction' => $venueDetails->access_instructions,
                'priority' => 'high',
            ];
        }

        if ($venueDetails->parking_information) {
            $instructions[] = [
                'category' => 'parking',
                'instruction' => $venueDetails->parking_information,
                'priority' => 'medium',
            ];
        }

        // Contact information
        if ($venueDetails->venue_contacts) {
            foreach ($venueDetails->venue_contacts as $contact) {
                $instructions[] = [
                    'category' => 'contact',
                    'instruction' => "Contact {$contact['name']} at {$contact['phone']} for venue access",
                    'priority' => 'high',
                ];
            }
        }

        return $instructions;
    }

    /**
     * Generate setup sequence based on service type
     */
    private function generateSetupSequence(VenueDetails $venueDetails, ?string $serviceType, array $eventDetails): array
    {
        $sequence = [];

        // Base venue preparation
        $sequence[] = [
            'step' => 1,
            'task' => 'Venue inspection and safety check',
            'duration_minutes' => 10,
            'description' => 'Check venue condition and identify any safety concerns',
        ];

        $sequence[] = [
            'step' => 2,
            'task' => 'Equipment and materials staging',
            'duration_minutes' => 15,
            'description' => 'Position all equipment and materials in designated areas',
        ];

        // Service-specific setup
        if ($serviceType === 'balloon_arch') {
            $sequence[] = [
                'step' => 3,
                'task' => 'Anchor point installation',
                'duration_minutes' => 20,
                'description' => 'Secure anchor points for balloon arch structure',
            ];

            $sequence[] = [
                'step' => 4,
                'task' => 'Frame assembly',
                'duration_minutes' => 30,
                'description' => 'Assemble the balloon arch framework',
            ];

            $sequence[] = [
                'step' => 5,
                'task' => 'Balloon installation',
                'duration_minutes' => 45,
                'description' => 'Attach balloons to create the arch design',
            ];
        }

        // Final touches
        $sequence[] = [
            'step' => count($sequence) + 1,
            'task' => 'Final inspection and adjustments',
            'duration_minutes' => 10,
            'description' => 'Final quality check and minor adjustments',
        ];

        return $sequence;
    }

    /**
     * Generate safety instructions specific to the venue
     */
    private function generateSafetyInstructions(VenueDetails $venueDetails): array
    {
        $instructions = [];

        // Access safety
        if (!$venueDetails->step_free_access || $venueDetails->stairs_count > 0) {
            $instructions[] = [
                'category' => 'access',
                'instruction' => 'Exercise caution with stairs and uneven surfaces',
                'severity' => 'warning',
            ];
        }

        // Power safety
        if (!empty($venueDetails->power_outlets)) {
            $instructions[] = [
                'category' => 'electrical',
                'instruction' => 'Ensure all electrical equipment is properly grounded',
                'severity' => 'important',
            ];
        }

        // Prohibited items
        if (!empty($venueDetails->prohibited_items)) {
            $instructions[] = [
                'category' => 'restrictions',
                'instruction' => 'Prohibited items: ' . implode(', ', $venueDetails->prohibited_items),
                'severity' => 'critical',
            ];
        }

        // Climate considerations
        if (!$venueDetails->climate_controlled) {
            $instructions[] = [
                'category' => 'environment',
                'instruction' => 'Weather-dependent venue - monitor conditions and have contingency plans',
                'severity' => 'warning',
            ];
        }

        return $instructions;
    }

    /**
     * Generate breakdown sequence
     */
    private function generateBreakdownSequence(VenueDetails $venueDetails, ?string $serviceType): array
    {
        $sequence = [];

        // Service-specific breakdown
        if ($serviceType === 'balloon_arch') {
            $sequence[] = [
                'step' => 1,
                'task' => 'Balloon removal',
                'duration_minutes' => 20,
                'description' => 'Carefully remove balloons and dispose of properly',
            ];

            $sequence[] = [
                'step' => 2,
                'task' => 'Frame disassembly',
                'duration_minutes' => 15,
                'description' => 'Disassemble framework components',
            ];
        }

        // General cleanup
        $sequence[] = [
            'step' => count($sequence) + 1,
            'task' => 'Equipment collection',
            'duration_minutes' => 10,
            'description' => 'Collect all equipment and materials',
        ];

        $sequence[] = [
            'step' => count($sequence) + 1,
            'task' => 'Venue restoration',
            'duration_minutes' => 10,
            'description' => 'Return venue to original condition',
        ];

        $sequence[] = [
            'step' => count($sequence) + 1,
            'task' => 'Final inspection',
            'duration_minutes' => 5,
            'description' => 'Ensure venue is clean and undamaged',
        ];

        return $sequence;
    }

    /**
     * Calculate setup times based on venue and service
     */
    private function calculateSetupTimes(VenueDetails $venueDetails, ?string $serviceType, array $eventDetails): array
    {
        $baseSetupTime = $venueDetails->setup_time_minutes ?? 60;
        $baseBreakdownTime = $venueDetails->breakdown_time_minutes ?? 30;

        // Adjust for service type
        $multiplier = match ($serviceType) {
            'balloon_arch' => 1.2,
            'decoration' => 1.0,
            'catering' => 1.5,
            'photography' => 0.8,
            default => 1.0,
        };

        // Adjust for event size
        $sizeMultiplier = match ($eventDetails['event_size'] ?? 'medium') {
            'small' => 0.8,
            'medium' => 1.0,
            'large' => 1.3,
            'extra_large' => 1.6,
            default => 1.0,
        };

        $adjustedSetupTime = intval($baseSetupTime * $multiplier * $sizeMultiplier);
        $adjustedBreakdownTime = intval($baseBreakdownTime * $multiplier * $sizeMultiplier);

        return [
            'base_setup_minutes' => $baseSetupTime,
            'base_breakdown_minutes' => $baseBreakdownTime,
            'adjusted_setup_minutes' => $adjustedSetupTime,
            'adjusted_breakdown_minutes' => $adjustedBreakdownTime,
            'service_multiplier' => $multiplier,
            'size_multiplier' => $sizeMultiplier,
            'total_service_window' => $adjustedSetupTime + $adjustedBreakdownTime,
        ];
    }

    /**
     * Get required equipment based on venue characteristics
     */
    private function getRequiredEquipment(VenueDetails $venueDetails, ?string $serviceType): array
    {
        $equipment = [];

        // Basic equipment for all setups
        $equipment[] = [
            'item' => 'Basic tools',
            'description' => 'Standard assembly tools and fasteners',
            'required' => true,
        ];

        // Venue-specific equipment
        if (!$venueDetails->has_adequate_lighting) {
            $equipment[] = [
                'item' => 'Portable lighting',
                'description' => 'Additional lighting equipment for adequate visibility',
                'required' => true,
            ];
        }

        if (!$venueDetails->climate_controlled) {
            $equipment[] = [
                'item' => 'Weather protection',
                'description' => 'Covers and protection for equipment and decorations',
                'required' => false,
            ];
        }

        if (empty($venueDetails->power_outlets)) {
            $equipment[] = [
                'item' => 'Portable power',
                'description' => 'Battery packs or generators for power needs',
                'required' => true,
            ];
        }

        // Service-specific equipment
        if ($serviceType === 'balloon_arch') {
            $equipment[] = [
                'item' => 'Balloon arch kit',
                'description' => 'Framework, balloons, and assembly materials',
                'required' => true,
            ];

            $equipment[] = [
                'item' => 'Anchor system',
                'description' => 'Weights or anchoring system for stability',
                'required' => true,
            ];
        }

        return $equipment;
    }

    /**
     * Get venue-specific notes and considerations
     */
    private function getVenueSpecificNotes(VenueDetails $venueDetails): array
    {
        $notes = [];

        if ($venueDetails->special_instructions) {
            $notes[] = [
                'category' => 'special_instructions',
                'note' => $venueDetails->special_instructions,
                'importance' => 'high',
            ];
        }

        if ($venueDetails->noise_restrictions) {
            $notes[] = [
                'category' => 'noise',
                'note' => $venueDetails->noise_restrictions,
                'importance' => 'medium',
            ];
        }

        if (!$venueDetails->photography_allowed) {
            $notes[] = [
                'category' => 'photography',
                'note' => 'Photography restrictions apply',
                'importance' => 'medium',
            ];
        }

        if ($venueDetails->photography_restrictions) {
            $notes[] = [
                'category' => 'photography',
                'note' => $venueDetails->photography_restrictions,
                'importance' => 'medium',
            ];
        }

        return $notes;
    }
}
