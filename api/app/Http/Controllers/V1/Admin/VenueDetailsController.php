<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceLocation;
use App\Models\VenueDetails;
use App\Requests\V1\StoreVenueDetailsRequest;
use App\Requests\V1\UpdateVenueDetailsRequest;
use App\Resources\V1\VenueDetailsResource;
use App\Services\V1\Venues\VenueDetailsService;
use App\Traits\V1\ApiResponses;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VenueDetailsController extends Controller
{
    use ApiResponses;

    private VenueDetailsService $venueDetailsService;

    public function __construct(VenueDetailsService $venueDetailsService)
    {
        $this->venueDetailsService = $venueDetailsService;
    }

    /**
     * Display venue details for a service location
     *
     * @group Admin - Venue Management
     * @authenticated
     *
     * @urlParam location integer required The service location ID. Example: 1
     */
    public function show(Request $request, ServiceLocation $location)
    {
        try {
            $user = $request->user();

            if (!$user->hasPermission('view_venue_details')) {
                return $this->error('You do not have permission to view venue details.', 403);
            }

            // Get venue details or return empty structure if none exist
            $venueDetails = $location->venueDetails;

            if (!$venueDetails) {
                return $this->ok('No venue details found for this location', [
                    'venue_details' => null,
                    'service_location' => [
                        'id' => $location->id,
                        'name' => $location->name,
                        'type' => $location->type,
                    ],
                    'can_create' => $user->hasPermission('create_venue_details'),
                ]);
            }

            Log::info('Admin viewed venue details', [
                'admin_id' => $user->id,
                'location_id' => $location->id,
                'venue_details_id' => $venueDetails->id,
            ]);

            return $this->ok(
                'Venue details retrieved successfully',
                new VenueDetailsResource($venueDetails->load('serviceLocation'))
            );

        } catch (Exception $e) {
            Log::error('Failed to retrieve venue details', [
                'admin_id' => $request->user()?->id,
                'location_id' => $location->id,
                'error' => $e->getMessage(),
            ]);

            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Store venue details for a service location
     *
     * @group Admin - Venue Management
     * @authenticated
     *
     * @urlParam location integer required The service location ID. Example: 1
     */
    public function store(StoreVenueDetailsRequest $request, ServiceLocation $location)
    {
        try {
            $user = $request->user();

            if (!$user->hasPermission('create_venue_details')) {
                return $this->error('You do not have permission to create venue details.', 403);
            }

            // Check if venue details already exist
            if ($location->venueDetails) {
                return $this->error('Venue details already exist for this location. Use PUT to update.', 409);
            }

            $data = $request->validated();
            $data['service_location_id'] = $location->id;

            return DB::transaction(function () use ($data, $location, $user) {
                // Create venue details
                $venueDetails = $this->venueDetailsService->createVenueDetails($data);

                Log::info('Admin created venue details', [
                    'admin_id' => $user->id,
                    'location_id' => $location->id,
                    'venue_details_id' => $venueDetails->id,
                    'venue_type' => $venueDetails->venue_type,
                ]);

                return $this->created(
                    'Venue details created successfully',
                    new VenueDetailsResource($venueDetails->load('serviceLocation'))
                );
            });

        } catch (Exception $e) {
            Log::error('Failed to create venue details', [
                'admin_id' => $request->user()?->id,
                'location_id' => $location->id,
                'error' => $e->getMessage(),
                'data' => $request->validated(),
            ]);

            return $this->error($e->getMessage(), $e->getCode() ?: 422);
        }
    }

    /**
     * Update venue details
     *
     * @group Admin - Venue Management
     * @authenticated
     *
     * @urlParam location integer required The service location ID. Example: 1
     * @urlParam details integer required The venue details ID. Example: 1
     */
    public function update(UpdateVenueDetailsRequest $request, ServiceLocation $location, VenueDetails $details)
    {
        try {
            $user = $request->user();

            if (!$user->hasPermission('edit_venue_details')) {
                return $this->error('You do not have permission to edit venue details.', 403);
            }

            // Verify the venue details belong to the specified location
            if ($details->service_location_id !== $location->id) {
                return $this->error('Venue details do not belong to the specified location.', 400);
            }

            $data = $request->validated();

            return DB::transaction(function () use ($details, $data, $location, $user) {
                // Update venue details
                $updatedDetails = $this->venueDetailsService->updateVenueDetails($details, $data);

                Log::info('Admin updated venue details', [
                    'admin_id' => $user->id,
                    'location_id' => $location->id,
                    'venue_details_id' => $details->id,
                    'updated_fields' => array_keys($data),
                ]);

                return $this->ok(
                    'Venue details updated successfully',
                    new VenueDetailsResource($updatedDetails->load('serviceLocation'))
                );
            });

        } catch (Exception $e) {
            Log::error('Failed to update venue details', [
                'admin_id' => $request->user()?->id,
                'location_id' => $location->id,
                'venue_details_id' => $details->id,
                'error' => $e->getMessage(),
            ]);

            return $this->error($e->getMessage(), $e->getCode() ?: 422);
        }
    }

    /**
     * Remove venue details
     *
     * @group Admin - Venue Management
     * @authenticated
     *
     * @urlParam location integer required The service location ID. Example: 1
     * @urlParam details integer required The venue details ID. Example: 1
     */
    public function destroy(Request $request, ServiceLocation $location, VenueDetails $details)
    {
        try {
            $user = $request->user();

            if (!$user->hasPermission('delete_venue_details')) {
                return $this->error('You do not have permission to delete venue details.', 403);
            }

            // Verify the venue details belong to the specified location
            if ($details->service_location_id !== $location->id) {
                return $this->error('Venue details do not belong to the specified location.', 400);
            }

            return DB::transaction(function () use ($details, $location, $user) {
                // Check if there are any active bookings using these venue details
                $hasActiveBookings = $this->venueDetailsService->hasActiveBookings($details);

                if ($hasActiveBookings) {
                    return $this->error(
                        'Cannot delete venue details with active bookings. Cancel or complete existing bookings first.',
                        409
                    );
                }

                // Delete venue details
                $this->venueDetailsService->deleteVenueDetails($details);

                Log::info('Admin deleted venue details', [
                    'admin_id' => $user->id,
                    'location_id' => $location->id,
                    'venue_details_id' => $details->id,
                ]);

                return $this->ok('Venue details deleted successfully');
            });

        } catch (Exception $e) {
            Log::error('Failed to delete venue details', [
                'admin_id' => $request->user()?->id,
                'location_id' => $location->id,
                'venue_details_id' => $details->id,
                'error' => $e->getMessage(),
            ]);

            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Get venue analytics and usage statistics
     *
     * @group Admin - Venue Management
     * @authenticated
     *
     * @urlParam location integer required The service location ID. Example: 1
     */
    public function getAnalytics(Request $request, ServiceLocation $location)
    {
        try {
            $user = $request->user();

            if (!$user->hasPermission('view_venue_analytics')) {
                return $this->error('You do not have permission to view venue analytics.', 403);
            }

            $request->validate([
                'date_range' => 'nullable|in:week,month,quarter,year',
                'include_forecasts' => 'boolean',
            ]);

            $dateRange = $request->input('date_range', 'month');
            $includeForecast = $request->boolean('include_forecasts', false);

            $analytics = $this->venueDetailsService->getVenueAnalytics(
                $location,
                $dateRange,
                $includeForecast
            );

            Log::info('Admin viewed venue analytics', [
                'admin_id' => $user->id,
                'location_id' => $location->id,
                'date_range' => $dateRange,
            ]);

            return $this->ok('Venue analytics retrieved successfully', $analytics);

        } catch (Exception $e) {
            Log::error('Failed to retrieve venue analytics', [
                'admin_id' => $request->user()?->id,
                'location_id' => $location->id,
                'error' => $e->getMessage(),
            ]);

            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Validate venue details configuration
     *
     * @group Admin - Venue Management
     * @authenticated
     *
     * @urlParam location integer required The service location ID. Example: 1
     */
    public function validateConfiguration(Request $request, ServiceLocation $location)
    {
        try {
            $user = $request->user();

            if (!$user->hasPermission('edit_venue_details')) {
                return $this->error('You do not have permission to validate venue configuration.', 403);
            }

            $venueDetails = $location->venueDetails;

            if (!$venueDetails) {
                return $this->error('No venue details found for validation.', 404);
            }

            $validation = $this->venueDetailsService->validateVenueConfiguration($venueDetails);

            Log::info('Admin validated venue configuration', [
                'admin_id' => $user->id,
                'location_id' => $location->id,
                'venue_details_id' => $venueDetails->id,
                'validation_score' => $validation['score'],
            ]);

            return $this->ok('Venue configuration validated', $validation);

        } catch (Exception $e) {
            Log::error('Failed to validate venue configuration', [
                'admin_id' => $request->user()?->id,
                'location_id' => $location->id,
                'error' => $e->getMessage(),
            ]);

            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Generate venue setup instructions
     *
     * @group Admin - Venue Management
     * @authenticated
     *
     * @urlParam location integer required The service location ID. Example: 1
     */
    public function generateSetupInstructions(Request $request, ServiceLocation $location)
    {
        try {
            $user = $request->user();

            if (!$user->hasPermission('view_venue_details')) {
                return $this->error('You do not have permission to generate setup instructions.', 403);
            }

            $request->validate([
                'service_type' => 'nullable|string|in:balloon_arch,decoration,catering,photography',
                'event_size' => 'nullable|string|in:small,medium,large,extra_large',
                'include_breakdown' => 'boolean',
            ]);

            $venueDetails = $location->venueDetails;

            if (!$venueDetails) {
                return $this->error('No venue details available for setup instructions.', 404);
            }

            $options = [
                'service_type' => $request->input('service_type', 'balloon_arch'),
                'event_size' => $request->input('event_size', 'medium'),
                'include_breakdown' => $request->boolean('include_breakdown', true),
            ];

            $instructions = $this->venueDetailsService->generateSetupInstructions(
                $venueDetails,
                $options
            );

            Log::info('Admin generated venue setup instructions', [
                'admin_id' => $user->id,
                'location_id' => $location->id,
                'service_type' => $options['service_type'],
                'event_size' => $options['event_size'],
            ]);

            return $this->ok('Setup instructions generated successfully', $instructions);

        } catch (Exception $e) {
            Log::error('Failed to generate setup instructions', [
                'admin_id' => $request->user()?->id,
                'location_id' => $location->id,
                'error' => $e->getMessage(),
            ]);

            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }
}
