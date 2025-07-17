<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Models\Order;
use App\Services\V1\Shipping\ShippingService;
use App\Requests\V1\CreateShipmentRequest;
use App\Requests\V1\UpdateShipmentRequest;
use App\Resources\V1\ShipmentResource;
use App\Traits\V1\ApiResponses;
use App\Constants\ShippingStatuses;
use App\Constants\FulfillmentStatuses;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ShipmentController extends Controller
{
    use ApiResponses;

    protected ShippingService $shippingService;

    public function __construct(ShippingService $shippingService)
    {
        $this->shippingService = $shippingService;
    }

    /**
     * Retrieve paginated list of shipments
     *
     * Get a paginated list of all shipments in the system with comprehensive filtering options.
     * This endpoint supports filtering by status, carrier, tracking number, order ID, shipping dates,
     * and can identify overdue shipments. Essential for shipment management and monitoring.
     *
     * @group Shipment Management
     * @authenticated
     *
     * @queryParam status string optional Filter by shipment status (pending, processing, shipped, delivered, etc.). Example: shipped
     * @queryParam carrier string optional Filter by carrier name (partial match supported). Example: royal-mail
     * @queryParam tracking_number string optional Filter by tracking number (partial match supported). Example: AB123456789GB
     * @queryParam order_id integer optional Filter shipments for specific order ID. Example: 123
     * @queryParam shipped_date_from string optional Filter shipments shipped from this date (YYYY-MM-DD). Example: 2025-01-01
     * @queryParam shipped_date_to string optional Filter shipments shipped until this date (YYYY-MM-DD). Example: 2025-01-31
     * @queryParam overdue boolean optional Show only overdue shipments (past estimated delivery). Example: true
     * @queryParam page integer optional Page number for pagination. Default: 1. Example: 1
     * @queryParam per_page integer optional Number of shipments per page (max 100). Default: 15. Example: 25
     *
     * @response 200 scenario="Success with shipments" {
     *   "message": "Shipments retrieved successfully.",
     *   "data": {
     *     "data": [
     *       {
     *         "id": 1,
     *         "order_id": 123,
     *         "shipping_method_id": 2,
     *         "tracking_number": "AB123456789GB",
     *         "carrier": "Royal Mail",
     *         "service_name": "Tracked 48",
     *         "status": "shipped",
     *         "status_label": "Shipped",
     *         "status_color": "blue",
     *         "shipping_cost": 599,
     *         "shipping_cost_formatted": "£5.99",
     *         "label_url": "https://example.com/labels/123.pdf",
     *         "tracking_url": "https://track.royalmail.com/AB123456789GB",
     *         "shipped_at": "2025-01-15T10:00:00.000000Z",
     *         "delivered_at": null,
     *         "estimated_delivery": "2025-01-17T17:00:00.000000Z",
     *         "days_in_transit": 2,
     *         "is_overdue": false,
     *         "is_delivered": false,
     *         "is_shipped": true,
     *         "is_pending": false,
     *         "is_cancelled": false,
     *         "has_tracking_number": true,
     *         "has_label": true,
     *         "notes": "Handle with care",
     *         "order": {
     *           "id": 123,
     *           "total_amount": 2999,
     *           "total_amount_formatted": "£29.99",
     *           "created_at": "2025-01-14T14:30:00.000000Z",
     *           "user": {
     *             "id": 45,
     *             "name": "John Smith",
     *             "email": "john@example.com"
     *           }
     *         },
     *         "shipping_method": {
     *           "id": 2,
     *           "name": "Standard Delivery",
     *           "carrier": "Royal Mail",
     *           "estimated_delivery": "3-5 days"
     *         },
     *         "created_at": "2025-01-15T09:45:00.000000Z",
     *         "updated_at": "2025-01-15T10:00:00.000000Z"
     *       }
     *     ],
     *     "current_page": 1,
     *     "per_page": 15,
     *     "total": 47,
     *     "last_page": 4
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('view_shipments')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        $query = Shipment::with(['order.user', 'shippingMethod']);

        // Apply status filter
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Apply carrier filter with partial matching
        if ($request->has('carrier')) {
            $query->where('carrier', 'like', '%' . $request->input('carrier') . '%');
        }

        // Apply tracking number filter with partial matching
        if ($request->has('tracking_number')) {
            $query->where('tracking_number', 'like', '%' . $request->input('tracking_number') . '%');
        }

        // Apply order ID filter
        if ($request->has('order_id')) {
            $query->where('order_id', $request->input('order_id'));
        }

        // Apply shipped date range filters
        if ($request->has('shipped_date_from')) {
            $query->where('shipped_at', '>=', $request->input('shipped_date_from'));
        }

        if ($request->has('shipped_date_to')) {
            $query->where('shipped_at', '<=', $request->input('shipped_date_to'));
        }

        // Apply overdue filter - shipments past estimated delivery that aren't delivered/cancelled
        if ($request->has('overdue')) {
            $query->where('estimated_delivery', '<', now())
                ->whereNotIn('status', [ShippingStatuses::DELIVERED, ShippingStatuses::CANCELLED]);
        }

        $perPage = min($request->input('per_page', 15), 100);
        $shipments = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return $this->ok(
            'Shipments retrieved successfully.',
            ShipmentResource::collection($shipments)->response()->getData()
        );
    }

    /**
     * Create a new shipment
     *
     * Create a new shipment for an order with specified shipping method and carrier details.
     * The system validates that the order can be shipped and doesn't already have active shipments.
     * Optionally auto-purchase shipping labels and send notifications to customers.
     *
     * @group Shipment Management
     * @authenticated
     *
     * @bodyParam order_id integer required The ID of the order to create shipment for. Example: 123
     * @bodyParam shipping_method_id integer required The shipping method to use. Example: 2
     * @bodyParam carrier string required The carrier name (e.g., "Royal Mail", "DPD"). Example: Royal Mail
     * @bodyParam service_name string optional The specific service name (e.g., "Tracked 48"). Example: Tracked 48
     * @bodyParam shipping_cost numeric required The shipping cost in pounds. Example: 5.99
     * @bodyParam estimated_delivery string optional Estimated delivery date (YYYY-MM-DD HH:MM:SS). Example: 2025-01-20 17:00:00
     * @bodyParam notes string optional Additional notes for the shipment. Example: Handle with care
     * @bodyParam auto_purchase_label boolean optional Whether to automatically purchase shipping label. Default: false. Example: true
     * @bodyParam send_notification boolean optional Whether to send notification to customer. Default: true. Example: true
     *
     * @response 200 scenario="Shipment created successfully" {
     *   "message": "Shipment created successfully.",
     *   "data": {
     *     "id": 48,
     *     "order_id": 123,
     *     "shipping_method_id": 2,
     *     "tracking_number": null,
     *     "carrier": "Royal Mail",
     *     "service_name": "Tracked 48",
     *     "status": "pending",
     *     "status_label": "Pending",
     *     "status_color": "orange",
     *     "shipping_cost": 599,
     *     "shipping_cost_formatted": "£5.99",
     *     "label_url": null,
     *     "tracking_url": null,
     *     "shipped_at": null,
     *     "delivered_at": null,
     *     "estimated_delivery": "2025-01-20T17:00:00.000000Z",
     *     "days_in_transit": null,
     *     "is_overdue": false,
     *     "is_delivered": false,
     *     "is_shipped": false,
     *     "is_pending": true,
     *     "is_cancelled": false,
     *     "has_tracking_number": false,
     *     "has_label": false,
     *     "notes": "Handle with care",
     *     "order": {
     *       "id": 123,
     *       "total_amount": 2999,
     *       "total_amount_formatted": "£29.99",
     *       "created_at": "2025-01-14T14:30:00.000000Z"
     *     },
     *     "shipping_method": {
     *       "id": 2,
     *       "name": "Standard Delivery",
     *       "carrier": "Royal Mail"
     *     },
     *     "created_at": "2025-01-15T14:20:00.000000Z",
     *     "updated_at": "2025-01-15T14:20:00.000000Z"
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 422 scenario="Validation errors" {
     *   "errors": [
     *     "The order id field is required.",
     *     "The shipping method id field is required.",
     *     "The carrier field is required.",
     *     "The shipping cost field is required."
     *   ]
     * }
     *
     * @response 400 scenario="Order cannot be shipped" {
     *   "message": "Order already has an active shipment."
     * }
     */
    public function store(CreateShipmentRequest $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('create_shipments')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        $data = $request->validated();
        $order = Order::findOrFail($data['order_id']);

        try {
            $shipment = $this->shippingService->createShipment($order, $data);

            return $this->ok(
                'Shipment created successfully.',
                new ShipmentResource($shipment->load(['order', 'shippingMethod']))
            );

        } catch (\Exception $e) {
            return $this->error('Failed to create shipment: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Retrieve a specific shipment
     *
     * Get detailed information about a specific shipment including order details, shipping method,
     * and current tracking status. If the shipment has a tracking number and isn't delivered,
     * the system will attempt to update tracking information from the carrier.
     *
     * @group Shipment Management
     * @authenticated
     *
     * @urlParam shipment integer required The ID of the shipment to retrieve. Example: 48
     *
     * @response 200 scenario="Shipment found" {
     *   "message": "Shipment retrieved successfully.",
     *   "data": {
     *     "id": 48,
     *     "order_id": 123,
     *     "shipping_method_id": 2,
     *     "tracking_number": "AB123456789GB",
     *     "carrier": "Royal Mail",
     *     "service_name": "Tracked 48",
     *     "status": "shipped",
     *     "status_label": "Shipped",
     *     "status_color": "blue",
     *     "shipping_cost": 599,
     *     "shipping_cost_formatted": "£5.99",
     *     "label_url": "https://example.com/labels/123.pdf",
     *     "tracking_url": "https://track.royalmail.com/AB123456789GB",
     *     "shipped_at": "2025-01-15T10:00:00.000000Z",
     *     "delivered_at": null,
     *     "estimated_delivery": "2025-01-17T17:00:00.000000Z",
     *     "days_in_transit": 2,
     *     "is_overdue": false,
     *     "is_delivered": false,
     *     "is_shipped": true,
     *     "is_pending": false,
     *     "is_cancelled": false,
     *     "has_tracking_number": true,
     *     "has_label": true,
     *     "notes": "Handle with care",
     *     "order": {
     *       "id": 123,
     *       "total_amount": 2999,
     *       "total_amount_formatted": "£29.99",
     *       "created_at": "2025-01-14T14:30:00.000000Z",
     *       "user": {
     *         "id": 45,
     *         "name": "John Smith",
     *         "email": "john@example.com"
     *       }
     *     },
     *     "shipping_method": {
     *       "id": 2,
     *       "name": "Standard Delivery",
     *       "carrier": "Royal Mail",
     *       "estimated_delivery": "3-5 days"
     *     },
     *     "tracking_events": [
     *       {
     *         "timestamp": "2025-01-15T10:00:00.000000Z",
     *         "status": "shipped",
     *         "description": "Item dispatched",
     *         "location": "London Mail Centre"
     *       }
     *     ],
     *     "shipping_address": {
     *       "id": 67,
     *       "name": "John Smith",
     *       "full_address": "123 Main St, London, SW1A 1AA",
     *       "country": "GB",
     *       "postcode": "SW1A 1AA"
     *     },
     *     "estimated_delivery_window": {
     *       "date": "2025-01-17T17:00:00.000000Z",
     *       "is_overdue": false,
     *       "days_remaining": 2
     *     },
     *     "created_at": "2025-01-15T09:45:00.000000Z",
     *     "updated_at": "2025-01-15T10:00:00.000000Z"
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Shipment not found" {
     *   "message": "No query results for model [App\\Models\\Shipment] 999"
     * }
     */
    public function show(Request $request, Shipment $shipment): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('view_shipments')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        $shipment->load(['order.user', 'shippingMethod']);

        // Attempt to update tracking status if shipment has tracking number and isn't delivered
        try {
            if ($shipment->tracking_number && !$shipment->isDelivered()) {
                $this->shippingService->updateTrackingStatus($shipment);
                $shipment->refresh();
            }
        } catch (\Exception $e) {
            // Silently handle tracking update failures - not critical for viewing
        }

        return $this->ok(
            'Shipment retrieved successfully.',
            new ShipmentResource($shipment)
        );
    }

    /**
     * Update an existing shipment
     *
     * Update shipment details including status, tracking information, and carrier data.
     * The system validates status transitions and automatically updates order fulfillment
     * status when shipment is marked as shipped. Cannot update delivered or cancelled shipments.
     *
     * @group Shipment Management
     * @authenticated
     *
     * @urlParam shipment integer required The ID of the shipment to update. Example: 48
     *
     * @bodyParam tracking_number string optional The tracking number from carrier. Example: AB123456789GB
     * @bodyParam carrier string optional The carrier name. Example: Royal Mail
     * @bodyParam service_name string optional The specific service name. Example: Tracked 48
     * @bodyParam status string optional The shipment status. Example: shipped
     * @bodyParam shipping_cost numeric optional The shipping cost in pounds. Example: 5.99
     * @bodyParam label_url string optional URL to the shipping label PDF. Example: https://example.com/labels/123.pdf
     * @bodyParam tracking_url string optional URL to carrier tracking page. Example: https://track.royalmail.com/AB123456789GB
     * @bodyParam shipped_at string optional Date/time when shipment was shipped. Example: 2025-01-15 10:00:00
     * @bodyParam delivered_at string optional Date/time when shipment was delivered. Example: 2025-01-17 14:30:00
     * @bodyParam estimated_delivery string optional Estimated delivery date/time. Example: 2025-01-17 17:00:00
     * @bodyParam notes string optional Additional notes about the shipment. Example: Left at front door
     * @bodyParam carrier_data object optional Additional carrier-specific data. Example: {"signature": "J.Smith"}
     *
     * @response 200 scenario="Shipment updated successfully" {
     *   "message": "Shipment updated successfully.",
     *   "data": {
     *     "id": 48,
     *     "order_id": 123,
     *     "tracking_number": "AB123456789GB",
     *     "carrier": "Royal Mail",
     *     "service_name": "Tracked 48",
     *     "status": "shipped",
     *     "status_label": "Shipped",
     *     "shipping_cost": 599,
     *     "shipping_cost_formatted": "£5.99",
     *     "label_url": "https://example.com/labels/123.pdf",
     *     "tracking_url": "https://track.royalmail.com/AB123456789GB",
     *     "shipped_at": "2025-01-15T10:00:00.000000Z",
     *     "estimated_delivery": "2025-01-17T17:00:00.000000Z",
     *     "notes": "Left at front door",
     *     "order": {
     *       "id": 123,
     *       "fulfillment_status": "shipped"
     *     },
     *     "updated_at": "2025-01-15T15:30:00.000000Z"
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 400 scenario="Invalid status transition" {
     *   "message": "Invalid status transition from pending to delivered."
     * }
     *
     * @response 422 scenario="Validation errors" {
     *   "errors": [
     *     "The tracking number must be at least 8 characters.",
     *     "The delivered at must be after or equal to ship date."
     *   ]
     * }
     */
    public function update(UpdateShipmentRequest $request, Shipment $shipment): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('edit_shipments')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        $data = $request->validated();

        try {
            $shipment->update($data);

            // Update order fulfillment status when shipment is marked as shipped
            if (isset($data['status']) && $data['status'] === ShippingStatuses::SHIPPED) {
                $shipment->order->update([
                    'fulfillment_status' => FulfillmentStatuses::SHIPPED
                ]);
            }

            return $this->ok(
                'Shipment updated successfully.',
                new ShipmentResource($shipment->load(['order', 'shippingMethod']))
            );

        } catch (\Exception $e) {
            return $this->error('Failed to update shipment: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Cancel a shipment
     *
     * Cancel an active shipment with an optional reason. This marks the shipment as cancelled
     * and can trigger refund processes for shipping costs if applicable. Cannot cancel
     * shipments that have already been delivered or are in transit.
     *
     * @group Shipment Management
     * @authenticated
     *
     * @urlParam shipment integer required The ID of the shipment to cancel. Example: 48
     *
     * @bodyParam reason string optional Reason for cancellation. Example: Customer requested cancellation
     *
     * @response 200 scenario="Shipment cancelled successfully" {
     *   "message": "Shipment cancelled successfully."
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 400 scenario="Cannot cancel shipment" {
     *   "message": "Cannot cancel shipment that has already been shipped."
     * }
     */
    public function destroy(Request $request, Shipment $shipment): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('delete_shipments')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        try {
            $reason = $request->input('reason', 'Cancelled by admin');
            $this->shippingService->cancelShipment($shipment, $reason);

            return $this->ok('Shipment cancelled successfully.');

        } catch (\Exception $e) {
            return $this->error('Failed to cancel shipment: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Purchase shipping label
     *
     * Purchase a shipping label for the specified shipment using the configured carrier API.
     * This generates a printable label PDF and may provide tracking information. The label
     * cost is typically included in the shipping cost calculation.
     *
     * @group Shipment Management
     * @authenticated
     *
     * @urlParam shipment integer required The ID of the shipment to purchase label for. Example: 48
     *
     * @response 200 scenario="Label purchased successfully" {
     *   "message": "Shipping label purchased successfully.",
     *   "data": {
     *     "shipment": {
     *       "id": 48,
     *       "tracking_number": "AB123456789GB",
     *       "label_url": "https://example.com/labels/123.pdf",
     *       "status": "ready_to_ship"
     *     },
     *     "label_data": {
     *       "label_url": "https://example.com/labels/123.pdf",
     *       "tracking_number": "AB123456789GB",
     *       "label_format": "PDF",
     *       "cost": 599,
     *       "cost_formatted": "£5.99"
     *     }
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 400 scenario="Label already purchased" {
     *   "message": "Shipping label already exists for this shipment."
     * }
     *
     * @response 500 scenario="Carrier API error" {
     *   "message": "Failed to purchase label: Carrier API unavailable."
     * }
     */
    public function purchaseLabel(Request $request, Shipment $shipment): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('purchase_labels')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        try {
            $labelData = $this->shippingService->purchaseLabel($shipment);

            return $this->ok(
                'Shipping label purchased successfully.',
                [
                    'shipment' => new ShipmentResource($shipment->refresh()),
                    'label_data' => $labelData
                ]
            );

        } catch (\Exception $e) {
            return $this->error('Failed to purchase label: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update tracking information
     *
     * Manually trigger an update of tracking information from the carrier API. This fetches
     * the latest tracking events and updates the shipment status accordingly. Useful for
     * getting real-time delivery updates.
     *
     * @group Shipment Management
     * @authenticated
     *
     * @urlParam shipment integer required The ID of the shipment to update tracking for. Example: 48
     *
     * @response 200 scenario="Tracking updated successfully" {
     *   "message": "Tracking information updated successfully.",
     *   "data": {
     *     "shipment": {
     *       "id": 48,
     *       "tracking_number": "AB123456789GB",
     *       "status": "in_transit",
     *       "delivered_at": null,
     *       "tracking_events": [
     *         {
     *           "timestamp": "2025-01-15T10:00:00.000000Z",
     *           "status": "shipped",
     *           "description": "Item dispatched",
     *           "location": "London Mail Centre"
     *         },
     *         {
     *           "timestamp": "2025-01-16T08:30:00.000000Z",
     *           "status": "in_transit",
     *           "description": "Item in transit",
     *           "location": "Birmingham Mail Centre"
     *         }
     *       ]
     *     },
     *     "tracking_data": {
     *       "status": "in_transit",
     *       "last_updated": "2025-01-16T08:30:00.000000Z",
     *       "events_count": 2,
     *       "estimated_delivery": "2025-01-17T17:00:00.000000Z"
     *     }
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 400 scenario="No tracking number" {
     *   "message": "Shipment has no tracking number."
     * }
     *
     * @response 500 scenario="Tracking API error" {
     *   "message": "Failed to update tracking: Carrier API unavailable."
     * }
     */
    public function trackingUpdate(Request $request, Shipment $shipment): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('track_shipments')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        try {
            $trackingData = $this->shippingService->updateTrackingStatus($shipment);

            return $this->ok(
                'Tracking information updated successfully.',
                [
                    'shipment' => new ShipmentResource($shipment->refresh()),
                    'tracking_data' => $trackingData
                ]
            );

        } catch (\Exception $e) {
            return $this->error('Failed to update tracking: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Bulk update multiple shipments
     *
     * Perform bulk operations on multiple shipments such as updating status, purchasing labels,
     * or updating tracking information. This is useful for processing multiple shipments
     * efficiently. Returns detailed results and errors for each shipment.
     *
     * @group Shipment Management
     * @authenticated
     *
     * @bodyParam shipment_ids array required Array of shipment IDs to update. Example: [1, 2, 3]
     * @bodyParam shipment_ids.* integer required Each shipment ID must be a valid integer. Example: 1
     * @bodyParam action string required The action to perform (update_status, purchase_labels, update_tracking). Example: update_status
     * @bodyParam status string optional Required if action is update_status. The new status to set. Example: shipped
     *
     * @response 200 scenario="Bulk operation completed" {
     *   "message": "Bulk operation completed.",
     *   "data": {
     *     "success_count": 2,
     *     "error_count": 1,
     *     "results": [
     *       "Shipment 1 status updated",
     *       "Shipment 2 status updated"
     *     ],
     *     "errors": [
     *       "Shipment 3: Cannot update delivered shipment"
     *     ]
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 422 scenario="Validation errors" {
     *   "errors": [
     *     "The shipment ids field is required.",
     *     "The action field is required.",
     *     "The status field is required when action is update_status."
     *   ]
     * }
     */
    public function bulkUpdate(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('manage_shipments')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        $request->validate([
            'shipment_ids' => ['required', 'array', 'min:1'],
            'shipment_ids.*' => ['integer', 'exists:shipments,id'],
            'action' => ['required', 'string', 'in:update_status,purchase_labels,update_tracking'],
            'status' => ['required_if:action,update_status', 'string', 'in:' . implode(',', ShippingStatuses::all())],
        ]);

        $shipmentIds = $request->input('shipment_ids');
        $action = $request->input('action');
        $results = [];
        $errors = [];

        foreach ($shipmentIds as $shipmentId) {
            try {
                $shipment = Shipment::findOrFail($shipmentId);

                switch ($action) {
                    case 'update_status':
                        $shipment->update(['status' => $request->input('status')]);
                        $results[] = "Shipment {$shipmentId} status updated";
                        break;

                    case 'purchase_labels':
                        if (!$shipment->hasLabel()) {
                            $this->shippingService->purchaseLabel($shipment);
                            $results[] = "Label purchased for shipment {$shipmentId}";
                        } else {
                            $results[] = "Shipment {$shipmentId} already has a label";
                        }
                        break;

                    case 'update_tracking':
                        if ($shipment->tracking_number) {
                            $this->shippingService->updateTrackingStatus($shipment);
                            $results[] = "Tracking updated for shipment {$shipmentId}";
                        } else {
                            $results[] = "Shipment {$shipmentId} has no tracking number";
                        }
                        break;
                }

            } catch (\Exception $e) {
                $errors[] = "Shipment {$shipmentId}: " . $e->getMessage();
            }
        }

        return $this->ok(
            'Bulk operation completed.',
            [
                'success_count' => count($results),
                'error_count' => count($errors),
                'results' => $results,
                'errors' => $errors,
            ]
        );
    }

    /**
     * Mark shipment as shipped
     *
     * Mark a shipment as shipped with tracking number and optional label URL. This updates
     * the shipment status, sets the shipped timestamp, and can trigger customer notifications.
     * This is typically used when labels are purchased through external systems.
     *
     * @group Shipment Management
     * @authenticated
     *
     * @urlParam shipment integer required The ID of the shipment to mark as shipped. Example: 48
     *
     * @bodyParam tracking_number string required The tracking number from the carrier. Example: AB123456789GB
     * @bodyParam label_url string optional URL to the shipping label PDF. Example: https://example.com/labels/123.pdf
     * @bodyParam send_notification boolean optional Whether to send notification to customer. Default: true. Example: true
     *
     * @response 200 scenario="Shipment marked as shipped" {
     *   "message": "Shipment marked as shipped successfully.",
     *   "data": {
     *     "id": 48,
     *     "tracking_number": "AB123456789GB",
     *     "status": "shipped",
     *     "status_label": "Shipped",
     *     "shipped_at": "2025-01-15T14:30:00.000000Z",
     *     "label_url": "https://example.com/labels/123.pdf",
     *     "tracking_url": "https://track.royalmail.com/AB123456789GB",
     *     "order": {
     *       "id": 123,
     *       "fulfillment_status": "shipped"
     *     }
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 400 scenario="Already shipped" {
     *   "message": "Shipment has already been shipped."
     * }
     *
     * @response 422 scenario="Validation errors" {
     *   "errors": [
     *     "The tracking number field is required.",
     *     "The tracking number must be at least 8 characters."
     *   ]
     * }
     */
    public function markAsShipped(Request $request, Shipment $shipment): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('edit_shipments')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        $request->validate([
            'tracking_number' => ['required', 'string', 'min:8', 'max:50'],
            'label_url' => ['nullable', 'url'],
            'send_notification' => ['nullable', 'boolean'],
        ]);

        try {
            $this->shippingService->markAsShipped($shipment, $request->validated());

            return $this->ok(
                'Shipment marked as shipped successfully.',
                new ShipmentResource($shipment->refresh())
            );

        } catch (\Exception $e) {
            return $this->error('Failed to mark as shipped: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get shipment statistics
     *
     * Retrieve comprehensive statistics about shipments including totals, status breakdowns,
     * carrier performance, and delivery metrics. Useful for dashboard displays and reporting.
     * Data can be filtered by date range for specific periods.
     *
     * @group Shipment Management
     * @authenticated
     *
     * @queryParam date_from string optional Start date for statistics (YYYY-MM-DD). Default: 30 days ago. Example: 2025-01-01
     * @queryParam date_to string optional End date for statistics (YYYY-MM-DD). Default: now. Example: 2025-01-31
     *
     * @response 200 scenario="Statistics retrieved successfully" {
     *   "message": "Shipment statistics retrieved successfully.",
     *   "data": {
     *     "total_shipments": 156,
     *     "pending_shipments": 12,
     *     "shipped_today": 8,
     *     "delivered_today": 15,
     *     "overdue_shipments": 3,
     *     "status_breakdown": {
     *       "pending": 12,
     *       "processing": 8,
     *       "shipped": 45,
     *       "delivered": 89,
     *       "cancelled": 2
     *     },
     *     "carrier_breakdown": {
     *       "Royal Mail": 78,
     *       "DPD": 45,
     *       "UPS": 23,
     *       "Parcelforce": 10
     *     },
     *     "average_delivery_time": 2.8
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     */
    public function getStats(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('view_shipments')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        $dateFrom = $request->input('date_from', now()->subDays(30));
        $dateTo = $request->input('date_to', now());

        $stats = [
            'total_shipments' => Shipment::whereBetween('created_at', [$dateFrom, $dateTo])->count(),
            'pending_shipments' => Shipment::where('status', ShippingStatuses::PENDING)->count(),
            'shipped_today' => Shipment::whereDate('shipped_at', today())->count(),
            'delivered_today' => Shipment::whereDate('delivered_at', today())->count(),
            'overdue_shipments' => Shipment::where('estimated_delivery', '<', now())
                ->whereNotIn('status', [ShippingStatuses::DELIVERED, ShippingStatuses::CANCELLED])
                ->count(),
            'status_breakdown' => Shipment::selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray(),
            'carrier_breakdown' => Shipment::selectRaw('carrier, COUNT(*) as count')
                ->groupBy('carrier')
                ->pluck('count', 'carrier')
                ->toArray(),
            'average_delivery_time' => Shipment::whereNotNull('shipped_at')
                ->whereNotNull('delivered_at')
                ->selectRaw('AVG(DATEDIFF(delivered_at, shipped_at)) as avg_days')
                ->value('avg_days'),
        ];

        return $this->ok('Shipment statistics retrieved successfully.', $stats);
    }

    /**
     * Get overdue shipments
     *
     * Retrieve all shipments that are past their estimated delivery date and haven't been
     * delivered or cancelled. This is critical for customer service and proactive communication
     * about delayed shipments. Results are ordered by estimated delivery date.
     *
     * @group Shipment Management
     * @authenticated
     *
     * @response 200 scenario="Overdue shipments retrieved" {
     *   "message": "Overdue shipments retrieved successfully.",
     *   "data": {
     *     "data": [
     *       {
     *         "id": 23,
     *         "tracking_number": "CD987654321GB",
     *         "carrier": "Royal Mail",
     *         "status": "shipped",
     *         "estimated_delivery": "2025-01-10T17:00:00.000000Z",
     *         "days_overdue": 5,
     *         "order": {
     *           "id": 89,
     *           "user": {
     *             "id": 34,
     *             "name": "Jane Doe",
     *             "email": "jane@example.com"
     *           }
     *         },
     *         "shipping_method": {
     *           "id": 1,
     *           "name": "Standard Delivery",
     *           "carrier": "Royal Mail"
     *         }
     *       }
     *     ],
     *     "current_page": 1,
     *     "per_page": 50,
     *     "total": 3
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     */
    public function getOverdue(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('view_shipments')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        $overdueShipments = Shipment::with(['order.user', 'shippingMethod'])
            ->where('estimated_delivery', '<', now())
            ->whereNotIn('status', [ShippingStatuses::DELIVERED, ShippingStatuses::CANCELLED])
            ->orderBy('estimated_delivery', 'asc')
            ->paginate(50);

        return $this->ok(
            'Overdue shipments retrieved successfully.',
            ShipmentResource::collection($overdueShipments)->response()->getData()
        );
    }

    /**
     * Create shipment from order
     *
     * Create a new shipment directly from an order with simplified parameters. This is a
     * convenience method that uses order details to pre-populate shipment information.
     * Useful for quick shipment creation from order management interfaces.
     *
     * @group Shipment Management
     * @authenticated
     *
     * @urlParam order integer required The ID of the order to create shipment for. Example: 123
     *
     * @bodyParam auto_purchase_label boolean optional Whether to automatically purchase shipping label. Default: false. Example: true
     * @bodyParam send_notification boolean optional Whether to send notification to customer. Default: true. Example: true
     * @bodyParam notes string optional Additional notes for the shipment. Example: Express delivery requested
     *
     * @response 200 scenario="Shipment created from order" {
     *   "message": "Shipment created from order successfully.",
     *   "data": {
     *     "id": 49,
     *     "order_id": 123,
     *     "shipping_method_id": 2,
     *     "carrier": "Royal Mail",
     *     "status": "pending",
     *     "shipping_cost": 599,
     *     "shipping_cost_formatted": "£5.99",
     *     "notes": "Express delivery requested",
     *     "order": {
     *       "id": 123,
     *       "total_amount": 2999,
     *       "total_amount_formatted": "£29.99",
     *       "shipping_method": {
     *         "id": 2,
     *         "name": "Standard Delivery",
     *         "carrier": "Royal Mail"
     *       }
     *     },
     *     "shipping_method": {
     *       "id": 2,
     *       "name": "Standard Delivery",
     *       "carrier": "Royal Mail"
     *     },
     *     "created_at": "2025-01-15T16:20:00.000000Z",
     *     "updated_at": "2025-01-15T16:20:00.000000Z"
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 400 scenario="Order cannot be shipped" {
     *   "message": "Order does not have a shipping method selected."
     * }
     */
    public function createFromOrder(Request $request, Order $order): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('create_shipments')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        $request->validate([
            'auto_purchase_label' => ['nullable', 'boolean'],
            'send_notification' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $shipment = $this->shippingService->createShipment($order, $request->validated());

            return $this->ok(
                'Shipment created from order successfully.',
                new ShipmentResource($shipment->load(['order', 'shippingMethod']))
            );

        } catch (\Exception $e) {
            return $this->error('Failed to create shipment: ' . $e->getMessage(), 500);
        }
    }
}
