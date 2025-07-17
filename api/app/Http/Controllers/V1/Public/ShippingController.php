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
