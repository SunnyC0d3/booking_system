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

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('view_shipments')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        $query = Shipment::with(['order.user', 'shippingMethod']);

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('carrier')) {
            $query->where('carrier', 'like', '%' . $request->input('carrier') . '%');
        }

        if ($request->has('tracking_number')) {
            $query->where('tracking_number', 'like', '%' . $request->input('tracking_number') . '%');
        }

        if ($request->has('order_id')) {
            $query->where('order_id', $request->input('order_id'));
        }

        if ($request->has('shipped_date_from')) {
            $query->where('shipped_at', '>=', $request->input('shipped_date_from'));
        }

        if ($request->has('shipped_date_to')) {
            $query->where('shipped_at', '<=', $request->input('shipped_date_to'));
        }

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

    public function show(Request $request, Shipment $shipment): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('view_shipments')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        $shipment->load(['order.user', 'shippingMethod']);

        try {
            if ($shipment->tracking_number && !$shipment->isDelivered()) {
                $this->shippingService->updateTrackingStatus($shipment);
                $shipment->refresh();
            }
        } catch (\Exception $e) {
        }

        return $this->ok(
            'Shipment retrieved successfully.',
            new ShipmentResource($shipment)
        );
    }

    public function update(UpdateShipmentRequest $request, Shipment $shipment): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('edit_shipments')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        $data = $request->validated();

        try {
            $shipment->update($data);

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
