<?php

namespace App\Http\Controllers\V1\Public;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\ServiceLocation;
use App\Resources\V1\ServiceResource;
use App\Resources\V1\ServiceLocationResource;
use App\Traits\V1\ApiResponses;
use Illuminate\Http\Request;
use Exception;

class ServiceController extends Controller
{
    use ApiResponses;

    public function __construct()
    {
        // Apply rate limiting
        $this->middleware('throttle:api')->except(['index', 'show']);
        $this->middleware('throttle:service-search:30,1')->only(['search']);
    }

    /**
     * Get all active services
     */
    public function index(Request $request)
    {
        try {
            $request->validate([
                'category' => 'nullable|string|max:100',
                'location_id' => 'nullable|exists:service_locations,id',
                'min_price' => 'nullable|integer|min:0',
                'max_price' => 'nullable|integer|min:0',
                'duration' => 'nullable|integer|min:15|max:480',
                'available_date' => 'nullable|date|after_or_equal:today',
                'search' => 'nullable|string|max:100',
                'sort' => 'nullable|in:price_asc,price_desc,duration_asc,duration_desc,name_asc,name_desc,popularity',
                'per_page' => 'nullable|integer|min:1|max:50',
            ]);

            $query = Service::with([
                'serviceLocations' => function ($query) {
                    $query->where('is_active', true);
                },
                'addOns' => function ($query) {
                    $query->where('is_active', true);
                },
                'media'
            ])
                ->where('is_active', true)
                ->where('is_bookable', true);

            // Filter by category
            if ($request->category) {
                $query->where('category', $request->category);
            }

            // Filter by price range
            if ($request->min_price) {
                $query->where('base_price', '>=', $request->min_price);
            }
            if ($request->max_price) {
                $query->where('base_price', '<=', $request->max_price);
            }

            // Filter by duration
            if ($request->duration) {
                $query->where('duration_minutes', '<=', $request->duration);
            }

            // Filter by location
            if ($request->location_id) {
                $query->whereHas('serviceLocations', function ($q) use ($request) {
                    $q->where('service_locations.id', $request->location_id)
                        ->where('is_active', true);
                });
            }

            // Search functionality
            if ($request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                        ->orWhere('description', 'LIKE', "%{$search}%")
                        ->orWhere('short_description', 'LIKE', "%{$search}%")
                        ->orWhere('category', 'LIKE', "%{$search}%");
                });
            }

            // Sorting
            switch ($request->sort) {
                case 'price_asc':
                    $query->orderBy('base_price', 'asc');
                    break;
                case 'price_desc':
                    $query->orderBy('base_price', 'desc');
                    break;
                case 'duration_asc':
                    $query->orderBy('duration_minutes', 'asc');
                    break;
                case 'duration_desc':
                    $query->orderBy('duration_minutes', 'desc');
                    break;
                case 'name_asc':
                    $query->orderBy('name', 'asc');
                    break;
                case 'name_desc':
                    $query->orderBy('name', 'desc');
                    break;
                case 'popularity':
                    $query->withCount(['bookings' => function ($q) {
                        $q->where('status', 'completed');
                    }])->orderBy('bookings_count', 'desc');
                    break;
                default:
                    $query->orderBy('sort_order', 'asc')->orderBy('name', 'asc');
            }

            $perPage = $request->input('per_page', 20);
            $services = $query->paginate($perPage);

            return ServiceResource::collection($services)->additional([
                'message' => 'Services retrieved successfully',
                'status' => 200,
                'filters_applied' => [
                    'category' => $request->category,
                    'location_id' => $request->location_id,
                    'price_range' => $request->min_price || $request->max_price ? [
                        'min' => $request->min_price,
                        'max' => $request->max_price,
                    ] : null,
                    'max_duration' => $request->duration,
                    'search' => $request->search,
                    'sort' => $request->sort,
                ],
            ]);

        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Get service details
     */
    public function show(Request $request, Service $service)
    {
        try {
            // Check if service is active and bookable
            if (!$service->is_active || !$service->is_bookable) {
                return $this->error('Service not available', 404);
            }

            $service->load([
                'serviceLocations' => function ($query) {
                    $query->where('is_active', true);
                },
                'addOns' => function ($query) {
                    $query->where('is_active', true);
                },
                'availabilityWindows' => function ($query) {
                    $query->where('is_active', true);
                },
                'media',
                'vendor'
            ]);

            // Get recent reviews if they exist
            $service->loadCount(['bookings as completed_bookings_count' => function ($query) {
                $query->where('status', 'completed');
            }]);

            // Calculate average rating if reviews exist
            // $service->load(['reviews' => function ($query) {
            //     $query->where('is_approved', true)->latest()->limit(5);
            // }]);

            return $this->ok('Service details retrieved', [
                'service' => new ServiceResource($service),
            ]);

        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Get service locations
     */
    public function getLocations(Request $request, Service $service)
    {
        try {
            if (!$service->is_active || !$service->is_bookable) {
                return $this->error('Service not available', 404);
            }

            $locations = $service->serviceLocations()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();

            return $this->ok('Service locations retrieved', [
                'service_id' => $service->id,
                'service_name' => $service->name,
                'locations' => ServiceLocationResource::collection($locations),
                'total_locations' => $locations->count(),
            ]);

        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Get service add-ons
     */
    public function getAddOns(Request $request, Service $service)
    {
        try {
            if (!$service->is_active || !$service->is_bookable) {
                return $this->error('Service not available', 404);
            }

            $addOns = $service->addOns()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();

            return $this->ok('Service add-ons retrieved', [
                'service_id' => $service->id,
                'service_name' => $service->name,
                'add_ons' => $addOns->map(function ($addOn) {
                    return [
                        'id' => $addOn->id,
                        'name' => $addOn->name,
                        'description' => $addOn->description,
                        'price' => $addOn->price,
                        'formatted_price' => '£' . number_format($addOn->price / 100, 2),
                        'duration_minutes' => $addOn->duration_minutes,
                        'is_required' => $addOn->is_required,
                        'max_quantity' => $addOn->max_quantity,
                        'sort_order' => $addOn->sort_order,
                    ];
                }),
                'total_add_ons' => $addOns->count(),
            ]);

        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Get service categories
     */
    public function getCategories(Request $request)
    {
        try {
            $categories = Service::where('is_active', true)
                ->where('is_bookable', true)
                ->select('category')
                ->whereNotNull('category')
                ->groupBy('category')
                ->withCount(['services as services_count' => function ($query) {
                    $query->where('is_active', true)->where('is_bookable', true);
                }])
                ->orderBy('category')
                ->get()
                ->map(function ($item) {
                    return [
                        'name' => $item->category,
                        'services_count' => $item->services_count,
                        'slug' => \Str::slug($item->category),
                    ];
                });

            return $this->ok('Service categories retrieved', [
                'categories' => $categories,
                'total_categories' => $categories->count(),
            ]);

        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Search services
     */
    public function search(Request $request)
    {
        try {
            $request->validate([
                'q' => 'required|string|min:2|max:100',
                'category' => 'nullable|string|max:100',
                'min_price' => 'nullable|integer|min:0',
                'max_price' => 'nullable|integer|min:0',
                'per_page' => 'nullable|integer|min:1|max:20',
            ]);

            $searchTerm = $request->q;
            $query = Service::with(['serviceLocations', 'addOns', 'media'])
                ->where('is_active', true)
                ->where('is_bookable', true);

            // Full-text search
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('description', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('short_description', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('category', 'LIKE', "%{$searchTerm}%");
            });

            // Apply additional filters
            if ($request->category) {
                $query->where('category', $request->category);
            }

            if ($request->min_price) {
                $query->where('base_price', '>=', $request->min_price);
            }

            if ($request->max_price) {
                $query->where('base_price', '<=', $request->max_price);
            }

            // Order by relevance (name matches first, then description)
            $query->orderByRaw("
                CASE
                    WHEN name LIKE ? THEN 1
                    WHEN short_description LIKE ? THEN 2
                    WHEN description LIKE ? THEN 3
                    WHEN category LIKE ? THEN 4
                    ELSE 5
                END,
                base_price ASC
            ", [
                "%{$searchTerm}%",
                "%{$searchTerm}%",
                "%{$searchTerm}%",
                "%{$searchTerm}%"
            ]);

            $perPage = $request->input('per_page', 10);
            $services = $query->paginate($perPage);

            return ServiceResource::collection($services)->additional([
                'message' => 'Search results retrieved',
                'status' => 200,
                'search_query' => $searchTerm,
                'total_results' => $services->total(),
            ]);

        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Get service pricing calculator
     */
    public function getPricing(Request $request, Service $service)
    {
        try {
            $request->validate([
                'duration_minutes' => 'nullable|integer|min:15|max:480',
                'add_ons' => 'nullable|array',
                'add_ons.*.id' => 'required|exists:service_add_ons,id',
                'add_ons.*.quantity' => 'required|integer|min:1|max:10',
            ]);

            if (!$service->is_active || !$service->is_bookable) {
                return $this->error('Service not available', 404);
            }

            $durationMinutes = $request->duration_minutes ?? $service->duration_minutes;
            $basePrice = $service->base_price;
            $addOnTotal = 0;
            $addOnDetails = [];

            // Calculate add-on pricing
            if ($request->add_ons) {
                $service->load('addOns');

                foreach ($request->add_ons as $addOnData) {
                    $addOn = $service->addOns()->find($addOnData['id']);

                    if ($addOn && $addOn->is_active) {
                        $quantity = min($addOnData['quantity'], $addOn->max_quantity);
                        $lineTotal = $addOn->price * $quantity;
                        $addOnTotal += $lineTotal;

                        $addOnDetails[] = [
                            'id' => $addOn->id,
                            'name' => $addOn->name,
                            'unit_price' => $addOn->price,
                            'formatted_unit_price' => '£' . number_format($addOn->price / 100, 2),
                            'quantity' => $quantity,
                            'line_total' => $lineTotal,
                            'formatted_line_total' => '£' . number_format($lineTotal / 100, 2),
                            'duration_minutes' => $addOn->duration_minutes,
                        ];
                    }
                }
            }

            $totalAmount = $basePrice + $addOnTotal;
            $totalDuration = $durationMinutes + collect($addOnDetails)->sum('duration_minutes');

            return $this->ok('Pricing calculated', [
                'service' => [
                    'id' => $service->id,
                    'name' => $service->name,
                    'base_duration' => $service->duration_minutes,
                ],
                'pricing' => [
                    'base_price' => $basePrice,
                    'formatted_base_price' => '£' . number_format($basePrice / 100, 2),
                    'add_ons_total' => $addOnTotal,
                    'formatted_add_ons_total' => '£' . number_format($addOnTotal / 100, 2),
                    'total_amount' => $totalAmount,
                    'formatted_total' => '£' . number_format($totalAmount / 100, 2),
                ],
                'duration' => [
                    'service_duration' => $durationMinutes,
                    'add_ons_duration' => collect($addOnDetails)->sum('duration_minutes'),
                    'total_duration' => $totalDuration,
                    'formatted_total_duration' => $this->formatDuration($totalDuration),
                ],
                'add_ons' => $addOnDetails,
                'calculation_date' => now()->toDateTimeString(),
            ]);

        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Get popular services
     */
    public function getPopular(Request $request)
    {
        try {
            $request->validate([
                'limit' => 'nullable|integer|min:1|max:20',
                'category' => 'nullable|string|max:100',
            ]);

            $limit = $request->input('limit', 8);

            $query = Service::with(['serviceLocations', 'media'])
                ->where('is_active', true)
                ->where('is_bookable', true)
                ->withCount(['bookings as completed_bookings_count' => function ($q) {
                    $q->where('status', 'completed')
                        ->where('created_at', '>=', now()->subMonths(3)); // Last 3 months
                }]);

            if ($request->category) {
                $query->where('category', $request->category);
            }

            $services = $query->orderBy('completed_bookings_count', 'desc')
                ->orderBy('sort_order', 'asc')
                ->limit($limit)
                ->get();

            return $this->ok('Popular services retrieved', [
                'services' => ServiceResource::collection($services),
                'total_services' => $services->count(),
                'period' => 'Last 3 months',
            ]);

        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Helper method to format duration
     */
    private function formatDuration(int $minutes): string
    {
        if ($minutes < 60) {
            return "{$minutes} minutes";
        }

        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;

        if ($remainingMinutes === 0) {
            return $hours === 1 ? "1 hour" : "{$hours} hours";
        }

        $hoursText = $hours === 1 ? "1 hour" : "{$hours} hours";
        $minutesText = "{$remainingMinutes} minutes";

        return "{$hoursText} {$minutesText}";
    }
}

