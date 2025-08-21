<?php

namespace App\Http\Controllers\V1\Public;

use App\Http\Controllers\Controller;
use App\Models\ServiceLocation;
use App\Models\VenueDetails;
use App\Models\VenueAmenity;
use App\Models\VenueAvailabilityWindow;
use App\Resources\V1\VenueDetailsResource;
use App\Resources\V1\VenueAmenityResource;
use App\Resources\V1\VenueAvailabilityWindowResource;
use App\Services\V1\Venues\VenueDetailsService;
use App\Services\V1\Venues\VenueAvailabilityService;
use App\Traits\V1\ApiResponses;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class VenueController extends Controller
{
    use ApiResponses;

    private VenueDetailsService $venueDetailsService;
    private VenueAvailabilityService $availabilityService;

    public function __construct(
        VenueDetailsService $venueDetailsService,
        VenueAvailabilityService $availabilityService
    ) {
        $this->venueDetailsService = $venueDetailsService;
        $this->availabilityService = $availabilityService;
    }

    /**
     * Get public venue details for a service location
     *
     * @group Public - Venue Information
     * @unauthenticated
     *
     * @urlParam location integer required The service location ID. Example: 1
     */
    public function getDetails(Request $request, ServiceLocation $location)
    {
        try {
            // Check if location is public and active
            if (!$location->is_active || !$location->is_public) {
                return $this->error('Location not available for public viewing.', 404);
            }

            $venueDetails = $location->venueDetails;

            if (!$venueDetails) {
                return $this->ok('Basic location information available', [
                    'venue_details' => null,
                    'service_location' => [
                        'id' => $location->id,
                        'name' => $location->name,
                        'type' => $location->type,
                        'description' => $location->description,
                        'max_capacity' => $location->max_capacity,
                        'address' => $this->getPublicAddress($location),
                    ],
                ]);
            }

            // Filter sensitive information for public viewing
            $publicDetails = $this->filterPublicVenueDetails($venueDetails);

            Log::info('Public venue details viewed', [
                'location_id' => $location->id,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return $this->ok(
                'Venue details retrieved successfully',
                [
                    'venue_details' => $publicDetails,
                    'service_location' => [
                        'id' => $location->id,
                        'name' => $location->name,
                        'type' => $location->type,
                        'description' => $location->description,
                        'max_capacity' => $location->max_capacity,
                        'address' => $this->getPublicAddress($location),
                    ],
                ]
            );

        } catch (Exception $e) {
            Log::error('Failed to retrieve public venue details', [
                'location_id' => $location->id,
                'ip' => $request->ip(),
                'error' => $e->getMessage(),
            ]);

            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Get public venue amenities
     *
     * @group Public - Venue Information
     * @unauthenticated
     *
     * @urlParam location integer required The service location ID. Example: 1
     * @queryParam amenity_type string optional Filter by amenity type (equipment, furniture, infrastructure, service). Example: equipment
     * @queryParam included_only boolean optional Show only amenities included in booking. Example: true
     */
    public function getAmenities(Request $request, ServiceLocation $location)
    {
        try {
            // Check if location is public and active
            if (!$location->is_active || !$location->is_public) {
                return $this->error('Location not available for public viewing.', 404);
            }

            $request->validate([
                'amenity_type' => 'nullable|in:equipment,furniture,infrastructure,service',
                'included_only' => 'boolean',
            ]);

            $query = VenueAmenity::where('service_location_id', $location->id)
                ->where('is_active', true)
                ->where('amenity_type', '!=', 'restriction'); // Don't show restrictions in public view

            // Apply filters
            if ($request->has('amenity_type')) {
                $query->where('amenity_type', $request->input('amenity_type'));
            }

            if ($request->boolean('included_only', false)) {
                $query->where('included_in_booking', true);
            }

            $amenities = $query->orderBy('amenity_type')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();

            // Group amenities by type for better organization
            $groupedAmenities = $amenities->groupBy('amenity_type');

            $result = [
                'total_amenities' => $amenities->count(),
                'included_amenities' => $amenities->where('included_in_booking', true)->count(),
                'paid_amenities' => $amenities->where('included_in_booking', false)->count(),
                'amenities_by_type' => [],
            ];

            foreach ($groupedAmenities as $type => $typeAmenities) {
                $result['amenities_by_type'][$type] = [
                    'type_display_name' => ucfirst(str_replace('_', ' ', $type)),
                    'count' => $typeAmenities->count(),
                    'items' => VenueAmenityResource::collection($typeAmenities),
                ];
            }

            Log::info('Public venue amenities viewed', [
                'location_id' => $location->id,
                'amenity_type_filter' => $request->input('amenity_type'),
                'included_only' => $request->boolean('included_only', false),
                'ip' => $request->ip(),
            ]);

            return $this->ok('Venue amenities retrieved successfully', $result);

        } catch (Exception $e) {
            Log::error('Failed to retrieve public venue amenities', [
                'location_id' => $location->id,
                'error' => $e->getMessage(),
            ]);

            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Get public venue availability
     *
     * @group Public - Venue Information
     * @unauthenticated
     *
     * @urlParam location integer required The service location ID. Example: 1
     * @queryParam date_from date optional Check availability from date. Example: 2024-03-01
     * @queryParam date_to date optional Check availability to date. Example: 2024-03-31
     * @queryParam event_duration integer optional Event duration in minutes. Example: 240
     */
    public function getAvailability(Request $request, ServiceLocation $location)
    {
        try {
            // Check if location is public and active
            if (!$location->is_active || !$location->is_public) {
                return $this->error('Location not available for public viewing.', 404);
            }

            $request->validate([
                'date_from' => 'nullable|date|after_or_equal:today',
                'date_to' => 'nullable|date|after_or_equal:date_from',
                'event_duration' => 'nullable|integer|min:30|max:1440', // 30 minutes to 24 hours
            ]);

            $dateFrom = $request->date('date_from') ?? now()->startOfDay();
            $dateTo = $request->date('date_to') ?? now()->addDays(30)->endOfDay();
            $eventDuration = $request->integer('event_duration', 240); // Default 4 hours

            // Get public availability windows (excluding maintenance and restrictions)
            $availabilityWindows = VenueAvailabilityWindow::where('service_location_id', $location->id)
                ->where('is_active', true)
                ->whereIn('window_type', ['regular', 'special_event', 'seasonal'])
                ->where(function ($query) use ($dateFrom, $dateTo) {
                    $query->whereNull('specific_date') // Recurring windows
                    ->orWhereBetween('specific_date', [$dateFrom, $dateTo])
                        ->orWhere(function ($q) use ($dateFrom, $dateTo) {
                            $q->where('date_range_start', '<=', $dateTo)
                                ->where('date_range_end', '>=', $dateFrom);
                        });
                })
                ->get();

            // Generate availability calendar
            $availabilityCalendar = $this->availabilityService->generatePublicAvailabilityCalendar(
                $location,
                $dateFrom,
                $dateTo,
                $eventDuration
            );

            // Get general availability summary
            $availabilitySummary = [
                'location_id' => $location->id,
                'location_name' => $location->name,
                'max_capacity' => $location->max_capacity,
                'typical_setup_time' => $location->venueDetails?->setup_time_minutes ?? 60,
                'typical_breakdown_time' => $location->venueDetails?->breakdown_time_minutes ?? 30,
                'advance_booking_required' => $this->getAdvanceBookingRequirement($location),
                'booking_restrictions' => $this->getPublicRestrictions($location),
            ];

            Log::info('Public venue availability viewed', [
                'location_id' => $location->id,
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
                'event_duration' => $eventDuration,
                'ip' => $request->ip(),
            ]);

            return $this->ok('Venue availability retrieved successfully', [
                'availability_summary' => $availabilitySummary,
                'availability_calendar' => $availabilityCalendar,
                'available_windows' => VenueAvailabilityWindowResource::collection($availabilityWindows),
                'search_parameters' => [
                    'date_from' => $dateFrom->toDateString(),
                    'date_to' => $dateTo->toDateString(),
                    'event_duration_minutes' => $eventDuration,
                ],
            ]);

        } catch (Exception $e) {
            Log::error('Failed to retrieve public venue availability', [
                'location_id' => $location->id,
                'error' => $e->getMessage(),
            ]);

            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Get venue booking requirements and guidelines
     *
     * @group Public - Venue Information
     * @unauthenticated
     *
     * @urlParam location integer required The service location ID. Example: 1
     */
    public function getBookingGuidelines(Request $request, ServiceLocation $location)
    {
        try {
            // Check if location is public and active
            if (!$location->is_active || !$location->is_public) {
                return $this->error('Location not available for public viewing.', 404);
            }

            $venueDetails = $location->venueDetails;

            $guidelines = [
                'location_info' => [
                    'name' => $location->name,
                    'type' => $location->type,
                    'max_capacity' => $location->max_capacity,
                    'requires_approval' => $location->requires_approval ?? false,
                ],
                'booking_requirements' => [
                    'advance_booking_days' => $this->getAdvanceBookingRequirement($location),
                    'minimum_duration_minutes' => 120, // 2 hours minimum
                    'maximum_duration_minutes' => 480, // 8 hours maximum
                    'requires_consultation' => $location->supports_consultation ?? false,
                ],
                'setup_information' => [
                    'setup_time_minutes' => $venueDetails?->setup_time_minutes ?? 60,
                    'breakdown_time_minutes' => $venueDetails?->breakdown_time_minutes ?? 30,
                    'access_instructions' => $this->getPublicAccessInstructions($venueDetails),
                    'parking_information' => $venueDetails?->parking_information,
                ],
                'restrictions' => $this->getPublicRestrictions($location),
                'pricing_info' => [
                    'base_location_fee' => $location->additional_charge ?? 0,
                    'travel_charge' => $location->travel_charge ?? 0,
                    'currency' => 'GBP',
                    'pricing_notes' => 'Additional charges may apply for premium amenities and extended setup time.',
                ],
            ];

            Log::info('Public venue booking guidelines viewed', [
                'location_id' => $location->id,
                'ip' => $request->ip(),
            ]);

            return $this->ok('Venue booking guidelines retrieved successfully', $guidelines);

        } catch (Exception $e) {
            Log::error('Failed to retrieve venue booking guidelines', [
                'location_id' => $location->id,
                'error' => $e->getMessage(),
            ]);

            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Search venues by criteria
     *
     * @group Public - Venue Information
     * @unauthenticated
     *
     * @queryParam location_type string optional Filter by location type. Example: business_premises
     * @queryParam max_capacity integer optional Minimum capacity required. Example: 50
     * @queryParam date date optional Check availability for specific date. Example: 2024-03-15
     * @queryParam duration integer optional Event duration in minutes. Example: 240
     * @queryParam amenities array optional Required amenities. Example: ["chairs","tables"]
     */
    public function searchVenues(Request $request)
    {
        try {
            $request->validate([
                'location_type' => 'nullable|in:business_premises,client_location,virtual,outdoor',
                'max_capacity' => 'nullable|integer|min:1|max:1000',
                'date' => 'nullable|date|after_or_equal:today',
                'duration' => 'nullable|integer|min:30|max:1440',
                'amenities' => 'nullable|array',
                'amenities.*' => 'string|max:100',
                'per_page' => 'integer|min:1|max:20',
            ]);

            $query = ServiceLocation::where('is_active', true)
                ->where('is_public', true)
                ->with(['venueDetails', 'venueAmenities' => function ($q) {
                    $q->where('is_active', true);
                }]);

            // Apply filters
            if ($request->has('location_type')) {
                $query->where('type', $request->input('location_type'));
            }

            if ($request->has('max_capacity')) {
                $query->where('max_capacity', '>=', $request->integer('max_capacity'));
            }

            // Filter by required amenities
            if ($request->has('amenities')) {
                $requiredAmenities = $request->input('amenities');
                $query->whereHas('venueAmenities', function ($q) use ($requiredAmenities) {
                    $q->whereIn('name', $requiredAmenities)
                        ->where('is_active', true);
                }, '>=', count($requiredAmenities));
            }

            $perPage = $request->integer('per_page', 10);
            $locations = $query->paginate($perPage);

            // Check availability if date and duration provided
            $availabilityCheck = null;
            if ($request->has('date') && $request->has('duration')) {
                $checkDate = Carbon::parse($request->input('date'));
                $duration = $request->integer('duration');

                $availabilityCheck = [
                    'date' => $checkDate->toDateString(),
                    'duration_minutes' => $duration,
                ];

                // Filter out unavailable locations
                $locations->getCollection()->transform(function ($location) use ($checkDate, $duration) {
                    $location->is_available_for_date = $this->availabilityService->isAvailableForDateTime(
                        $location,
                        $checkDate,
                        $duration
                    );
                    return $location;
                });
            }

            Log::info('Public venue search performed', [
                'filters' => $request->only(['location_type', 'max_capacity', 'date', 'duration', 'amenities']),
                'results_count' => $locations->count(),
                'ip' => $request->ip(),
            ]);

            return $this->ok('Venue search completed successfully', [
                'search_parameters' => $request->only(['location_type', 'max_capacity', 'date', 'duration', 'amenities']),
                'availability_check' => $availabilityCheck,
                'total_venues' => $locations->total(),
                'venues' => $locations->getCollection()->map(function ($location) {
                    return [
                        'id' => $location->id,
                        'name' => $location->name,
                        'type' => $location->type,
                        'max_capacity' => $location->max_capacity,
                        'address' => $this->getPublicAddress($location),
                        'amenities_count' => $location->venueAmenities->count(),
                        'included_amenities' => $location->venueAmenities->where('included_in_booking', true)->count(),
                        'base_charge' => $location->additional_charge ?? 0,
                        'is_available_for_date' => $location->is_available_for_date ?? null,
                    ];
                }),
                'pagination' => [
                    'current_page' => $locations->currentPage(),
                    'per_page' => $locations->perPage(),
                    'total' => $locations->total(),
                    'last_page' => $locations->lastPage(),
                ],
            ]);

        } catch (Exception $e) {
            Log::error('Failed to search venues', [
                'filters' => $request->all(),
                'error' => $e->getMessage(),
            ]);

            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Helper: Filter venue details for public viewing
     */
    private function filterPublicVenueDetails(VenueDetails $venueDetails): array
    {
        return [
            'venue_type' => $venueDetails->venue_type,
            'space_style' => $venueDetails->space_style,
            'floor_area_sqm' => $venueDetails->floor_area_sqm,
            'ceiling_height_meters' => $venueDetails->ceiling_height_meters,
            'max_capacity' => $venueDetails->max_capacity,
            'has_adequate_lighting' => $venueDetails->has_adequate_lighting,
            'climate_controlled' => $venueDetails->climate_controlled,
            'lift_access' => $venueDetails->lift_access,
            'step_free_access' => $venueDetails->step_free_access,
            'parking_information' => $venueDetails->parking_information,
            'photography_allowed' => $venueDetails->photography_allowed,
            'social_media_allowed' => $venueDetails->social_media_allowed,
            // Exclude sensitive info like access codes, contacts, etc.
        ];
    }

    /**
     * Helper: Get public-safe address
     */
    private function getPublicAddress(ServiceLocation $location): array
    {
        return [
            'city' => $location->city,
            'county' => $location->county,
            'postcode' => $location->postcode,
            'country' => $location->country ?? 'United Kingdom',
            // Exclude full address for privacy
        ];
    }

    /**
     * Helper: Get advance booking requirement
     */
    private function getAdvanceBookingRequirement(ServiceLocation $location): int
    {
        // Return minimum days advance booking required
        return match($location->type) {
            'business_premises' => 2,
            'client_location' => 3,
            'outdoor' => 5,
            default => 3
        };
    }

    /**
     * Helper: Get public restrictions
     */
    private function getPublicRestrictions(ServiceLocation $location): array
    {
        $restrictions = [];

        if ($location->venueDetails) {
            $restrictions = array_merge($restrictions, [
                'noise_restrictions' => $location->venueDetails->noise_restrictions,
                'prohibited_items' => $location->venueDetails->prohibited_items,
                'photography_restrictions' => $location->venueDetails->photography_restrictions,
            ]);
        }

        return array_filter($restrictions);
    }

    /**
     * Helper: Get public access instructions
     */
    private function getPublicAccessInstructions(?VenueDetails $venueDetails): ?string
    {
        if (!$venueDetails || !$venueDetails->access_instructions) {
            return null;
        }

        // Return sanitized access instructions (remove sensitive details)
        return 'Detailed access instructions will be provided upon booking confirmation.';
    }
}
