<?php

namespace App\Http\Controllers\V1\Public;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Models\Order;
use App\Services\V1\Shipping\ShippingService;
use App\Traits\V1\ApiResponses;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ShippingController extends Controller
{
    use ApiResponses;

    protected ShippingService $shippingService;

    public function __construct(ShippingService $shippingService)
    {
        $this->shippingService = $shippingService;
    }

    /**
     * Track shipment by tracking number
     *
     * Track a shipment using its tracking number without requiring authentication.
     * This endpoint fetches the latest tracking information from the carrier and
     * returns the current status, delivery information, and tracking history.
     * Public endpoint for customer tracking pages.
     *
     * @group Public Shipping Tracking
     *
     * @urlParam trackingNumber string required The tracking number to look up. Example: AB123456789GB
     *
     * @response 200 scenario="Shipment tracking information retrieved successfully" {
     *   "message": "Shipment tracking information retrieved successfully.",
     *   "data": {
     *     "tracking_number": "AB123456789GB",
     *     "status": "in_transit",
     *     "status_label": "In Transit",
     *     "carrier": "Royal Mail",
     *     "service_name": "Tracked 48",
     *     "shipped_at": "2025-01-15T10:00:00.000000Z",
     *     "delivered_at": null,
     *     "estimated_delivery": "2025-01-17T17:00:00.000000Z",
     *     "tracking_url": "https://track.royalmail.com/AB123456789GB",
     *     "tracking_history": [
     *       {
     *         "timestamp": "2025-01-15T10:00:00.000000Z",
     *         "status": "shipped",
     *         "description": "Item dispatched",
     *         "location": "London Mail Centre",
     *         "carrier_status": "DISPATCHED"
     *       },
     *       {
     *         "timestamp": "2025-01-16T08:30:00.000000Z",
     *         "status": "in_transit",
     *         "description": "Item in transit",
     *         "location": "Birmingham Mail Centre",
     *         "carrier_status": "IN_TRANSIT"
     *       },
     *       {
     *         "timestamp": "2025-01-16T14:45:00.000000Z",
     *         "status": "out_for_delivery",
     *         "description": "Out for delivery",
     *         "location": "Manchester Delivery Office",
     *         "carrier_status": "OUT_FOR_DELIVERY"
     *       }
     *     ],
     *     "order_id": 123
     *   }
     * }
     *
     * @response 404 scenario="Tracking number not found" {
     *   "message": "Tracking number not found."
     * }
     *
     * @response 503 scenario="Tracking service unavailable" {
     *   "message": "Unable to retrieve tracking information at this time."
     * }
     */
    public function trackShipment(Request $request, string $trackingNumber): JsonResponse
    {
        $shipment = Shipment::where('tracking_number', $trackingNumber)->first();

        if (!$shipment) {
            return $this->error('Tracking number not found.', 404);
        }

        try {
            $trackingData = $this->shippingService->updateTrackingStatus($shipment);

            return $this->ok('Shipment tracking information retrieved successfully.', [
                'tracking_number' => $trackingNumber,
                'status' => $shipment->fresh()->status,
                'status_label' => $shipment->fresh()->getStatusLabel(),
                'carrier' => $shipment->carrier,
                'service_name' => $shipment->service_name,
                'shipped_at' => $shipment->shipped_at,
                'delivered_at' => $shipment->delivered_at,
                'estimated_delivery' => $shipment->estimated_delivery,
                'tracking_url' => $shipment->getTrackingUrl(),
                'tracking_history' => $trackingData['tracking_history'] ?? [],
                'order_id' => $shipment->order_id,
            ]);

        } catch (\Exception $e) {
            return $this->error('Unable to retrieve tracking information at this time.', 503);
        }
    }

    /**
     * Get basic shipment status
     *
     * Get basic status information for a shipment by tracking number without fetching
     * full tracking updates from the carrier. This is a faster, lightweight endpoint
     * for checking shipment status without detailed tracking history.
     *
     * @group Public Shipping Tracking
     *
     * @urlParam trackingNumber string required The tracking number to look up. Example: AB123456789GB
     *
     * @response 200 scenario="Shipment status retrieved successfully" {
     *   "message": "Shipment status retrieved successfully.",
     *   "data": {
     *     "tracking_number": "AB123456789GB",
     *     "status": "shipped",
     *     "status_label": "Shipped",
     *     "status_color": "blue",
     *     "is_delivered": false,
     *     "estimated_delivery": "2025-01-17T17:00:00.000000Z",
     *     "delivered_at": null,
     *     "days_in_transit": 2,
     *     "is_overdue": false
     *   }
     * }
     *
     * @response 404 scenario="Tracking number not found" {
     *   "message": "Tracking number not found."
     * }
     */
    public function getShipmentStatus(Request $request, string $trackingNumber): JsonResponse
    {
        $shipment = Shipment::where('tracking_number', $trackingNumber)->first();

        if (!$shipment) {
            return $this->error('Tracking number not found.', 404);
        }

        return $this->ok('Shipment status retrieved successfully.', [
            'tracking_number' => $trackingNumber,
            'status' => $shipment->status,
            'status_label' => $shipment->getStatusLabel(),
            'status_color' => $shipment->getStatusColor(),
            'is_delivered' => $shipment->isDelivered(),
            'estimated_delivery' => $shipment->estimated_delivery,
            'delivered_at' => $shipment->delivered_at,
            'days_in_transit' => $shipment->getDaysInTransit(),
            'is_overdue' => $shipment->isOverdue(),
        ]);
    }

    /**
     * Get user's shipments
     *
     * Retrieve all shipments for the authenticated user, ordered by creation date.
     * This endpoint provides a complete list of the user's shipments with basic
     * information and pagination support. Essential for customer account pages.
     *
     * @group Customer Shipment Management
     * @authenticated
     *
     * @queryParam per_page integer optional Number of shipments per page. Default: 15. Example: 10
     * @queryParam page integer optional Page number for pagination. Default: 1. Example: 1
     *
     * @response 200 scenario="Shipments retrieved successfully" {
     *   "message": "Shipments retrieved successfully.",
     *   "data": {
     *     "shipments": [
     *       {
     *         "id": 1,
     *         "tracking_number": "AB123456789GB",
     *         "carrier": "Royal Mail",
     *         "service_name": "Tracked 48",
     *         "status": "delivered",
     *         "status_label": "Delivered",
     *         "status_color": "green",
     *         "shipping_cost": 599,
     *         "shipping_cost_formatted": "£5.99",
     *         "tracking_url": "https://track.royalmail.com/AB123456789GB",
     *         "shipped_at": "2025-01-15T10:00:00.000000Z",
     *         "delivered_at": "2025-01-17T14:30:00.000000Z",
     *         "estimated_delivery": "2025-01-17T17:00:00.000000Z",
     *         "is_overdue": false,
     *         "order": {
     *           "id": 123,
     *           "total_amount_formatted": "£29.99",
     *           "created_at": "2025-01-14T14:30:00.000000Z"
     *         },
     *         "shipping_method": {
     *           "id": 1,
     *           "name": "Standard Delivery",
     *           "carrier": "Royal Mail",
     *           "estimated_delivery": "2-3 days"
     *         },
     *         "created_at": "2025-01-15T09:45:00.000000Z"
     *       },
     *       {
     *         "id": 2,
     *         "tracking_number": "CD987654321GB",
     *         "carrier": "DPD",
     *         "service_name": "Next Day",
     *         "status": "shipped",
     *         "status_label": "Shipped",
     *         "status_color": "blue",
     *         "shipping_cost": 999,
     *         "shipping_cost_formatted": "£9.99",
     *         "tracking_url": "https://track.dpd.co.uk/CD987654321GB",
     *         "shipped_at": "2025-01-16T11:00:00.000000Z",
     *         "delivered_at": null,
     *         "estimated_delivery": "2025-01-17T13:00:00.000000Z",
     *         "is_overdue": false,
     *         "order": {
     *           "id": 124,
     *           "total_amount_formatted": "£75.50",
     *           "created_at": "2025-01-16T09:20:00.000000Z"
     *         },
     *         "shipping_method": {
     *           "id": 2,
     *           "name": "Express Delivery",
     *           "carrier": "DPD",
     *           "estimated_delivery": "1 day"
     *         },
     *         "created_at": "2025-01-16T10:30:00.000000Z"
     *       }
     *     ],
     *     "pagination": {
     *       "current_page": 1,
     *       "per_page": 15,
     *       "total": 8,
     *       "last_page": 1
     *     }
     *   }
     * }
     *
     * @response 200 scenario="No shipments found" {
     *   "message": "Shipments retrieved successfully.",
     *   "data": {
     *     "shipments": [],
     *     "pagination": {
     *       "current_page": 1,
     *       "per_page": 15,
     *       "total": 0,
     *       "last_page": 1
     *     }
     *   }
     * }
     */
    public function getUserShipments(Request $request): JsonResponse
    {
        $user = $request->user();

        $shipments = Shipment::whereHas('order', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })
            ->with(['order', 'shippingMethod'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 15));

        return $this->ok('Shipments retrieved successfully.', [
            'shipments' => $shipments->items(),
            'pagination' => [
                'current_page' => $shipments->currentPage(),
                'per_page' => $shipments->perPage(),
                'total' => $shipments->total(),
                'last_page' => $shipments->lastPage(),
            ],
        ]);
    }

    /**
     * Get specific user shipment
     *
     * Retrieve detailed information about a specific shipment belonging to the authenticated
     * user. This endpoint includes complete shipment details, order information, and
     * attempts to fetch the latest tracking information from the carrier.
     *
     * @group Customer Shipment Management
     * @authenticated
     *
     * @urlParam shipment integer required The ID of the shipment to retrieve. Example: 1
     *
     * @response 200 scenario="Shipment retrieved successfully" {
     *   "message": "Shipment retrieved successfully.",
     *   "data": {
     *     "id": 1,
     *     "tracking_number": "AB123456789GB",
     *     "carrier": "Royal Mail",
     *     "service_name": "Tracked 48",
     *     "status": "delivered",
     *     "status_label": "Delivered",
     *     "status_color": "green",
     *     "shipping_cost": 599,
     *     "shipping_cost_formatted": "£5.99",
     *     "tracking_url": "https://track.royalmail.com/AB123456789GB",
     *     "shipped_at": "2025-01-15T10:00:00.000000Z",
     *     "delivered_at": "2025-01-17T14:30:00.000000Z",
     *     "estimated_delivery": "2025-01-17T17:00:00.000000Z",
     *     "days_in_transit": 2,
     *     "is_overdue": false,
     *     "notes": "Handle with care",
     *     "order": {
     *       "id": 123,
     *       "total_amount_formatted": "£29.99",
     *       "created_at": "2025-01-14T14:30:00.000000Z"
     *     },
     *     "shipping_method": {
     *       "name": "Standard Delivery",
     *       "carrier": "Royal Mail",
     *       "estimated_delivery": "2-3 days"
     *     },
     *     "created_at": "2025-01-15T09:45:00.000000Z",
     *     "updated_at": "2025-01-17T14:30:00.000000Z"
     *   }
     * }
     *
     * @response 403 scenario="Access denied" {
     *   "message": "You can only view your own shipments."
     * }
     *
     * @response 404 scenario="Shipment not found" {
     *   "message": "No query results for model [App\\Models\\Shipment] 999"
     * }
     */
    public function getUserShipment(Request $request, Shipment $shipment): JsonResponse
    {
        $user = $request->user();

        if ($shipment->order->user_id !== $user->id) {
            return $this->error('You can only view your own shipments.', 403);
        }

        $shipment->load(['order', 'shippingMethod']);

        try {
            if ($shipment->tracking_number && !$shipment->isDelivered()) {
                $this->shippingService->updateTrackingStatus($shipment);
                $shipment->refresh();
            }
        } catch (\Exception $e) {
            // Continue without updated tracking if service fails
        }

        return $this->ok('Shipment retrieved successfully.', [
            'id' => $shipment->id,
            'tracking_number' => $shipment->tracking_number,
            'carrier' => $shipment->carrier,
            'service_name' => $shipment->service_name,
            'status' => $shipment->status,
            'status_label' => $shipment->getStatusLabel(),
            'status_color' => $shipment->getStatusColor(),
            'shipping_cost' => $shipment->shipping_cost,
            'shipping_cost_formatted' => $shipment->getShippingCostFormatted(),
            'tracking_url' => $shipment->getTrackingUrl(),
            'shipped_at' => $shipment->shipped_at,
            'delivered_at' => $shipment->delivered_at,
            'estimated_delivery' => $shipment->estimated_delivery,
            'days_in_transit' => $shipment->getDaysInTransit(),
            'is_overdue' => $shipment->isOverdue(),
            'notes' => $shipment->notes,
            'order' => [
                'id' => $shipment->order->id,
                'total_amount_formatted' => '£' . number_format($shipment->order->total_amount / 100, 2),
                'created_at' => $shipment->order->created_at,
            ],
            'shipping_method' => [
                'name' => $shipment->shippingMethod->name,
                'carrier' => $shipment->shippingMethod->carrier,
                'estimated_delivery' => $shipment->shippingMethod->getEstimatedDeliveryAttribute(),
            ],
            'created_at' => $shipment->created_at,
            'updated_at' => $shipment->updated_at,
        ]);
    }
}
