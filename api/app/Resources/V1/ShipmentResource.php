<?php

namespace App\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShipmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'shipping_method_id' => $this->shipping_method_id,
            'tracking_number' => $this->tracking_number,
            'carrier' => $this->carrier,
            'service_name' => $this->service_name,
            'status' => $this->status,
            'status_label' => $this->getStatusLabel(),
            'status_color' => $this->getStatusColor(),
            'shipping_cost' => $this->shipping_cost,
            'shipping_cost_formatted' => $this->getShippingCostFormatted(),
            'label_url' => $this->label_url,
            'tracking_url' => $this->getTrackingUrl(),
            'shipped_at' => $this->shipped_at,
            'delivered_at' => $this->delivered_at,
            'estimated_delivery' => $this->estimated_delivery,
            'days_in_transit' => $this->getDaysInTransit(),
            'is_overdue' => $this->isOverdue(),
            'is_delivered' => $this->isDelivered(),
            'is_shipped' => $this->isShipped(),
            'is_pending' => $this->isPending(),
            'is_cancelled' => $this->isCancelled(),
            'has_tracking_number' => $this->hasTrackingNumber(),
            'has_label' => $this->hasLabel(),
            'notes' => $this->notes,
            'carrier_data' => $this->when($this->shouldShowCarrierData($request), $this->carrier_data),
            'order' => $this->whenLoaded('order', function () {
                return [
                    'id' => $this->order->id,
                    'total_amount' => $this->order->total_amount,
                    'total_amount_formatted' => '£' . number_format($this->order->total_amount / 100, 2),
                    'created_at' => $this->order->created_at,
                    'user' => $this->whenLoaded('order.user', function () {
                        return [
                            'id' => $this->order->user->id,
                            'name' => $this->order->user->name,
                            'email' => $this->order->user->email,
                        ];
                    }),
                ];
            }),
            'shipping_method' => $this->whenLoaded('shippingMethod', function () {
                return [
                    'id' => $this->shippingMethod->id,
                    'name' => $this->shippingMethod->name,
                    'carrier' => $this->shippingMethod->carrier,
                    'estimated_delivery' => $this->shippingMethod->getEstimatedDeliveryAttribute(),
                ];
            }),
            'tracking_events' => $this->when(
                isset($this->carrier_data['tracking_history']),
                $this->carrier_data['tracking_history'] ?? []
            ),
            'shipping_address' => $this->when(
                $this->relationLoaded('order') && $this->order->relationLoaded('shippingAddress'),
                function () {
                    return $this->order->shippingAddress ? [
                        'id' => $this->order->shippingAddress->id,
                        'name' => $this->order->shippingAddress->name,
                        'full_address' => $this->order->shippingAddress->getFullAddressAttribute(),
                        'country' => $this->order->shippingAddress->country,
                        'postcode' => $this->order->shippingAddress->postcode,
                    ] : null;
                }
            ),
            'estimated_delivery_window' => $this->when(
                $this->estimated_delivery,
                function () {
                    return [
                        'date' => $this->estimated_delivery,
                        'is_overdue' => $this->isOverdue(),
                        'days_remaining' => $this->estimated_delivery ?
                            max(0, now()->diffInDays($this->estimated_delivery, false)) : null,
                    ];
                }
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    protected function shouldShowCarrierData(Request $request): bool
    {
        $user = $request->user();
        return $user && $user->hasPermission('manage_shipments');
    }
}
