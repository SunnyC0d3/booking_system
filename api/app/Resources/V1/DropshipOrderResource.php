<?php

namespace App\Resources\V1;

use App\Constants\DropshipStatuses;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DropshipOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'supplier_id' => $this->supplier_id,
            'supplier_order_id' => $this->supplier_order_id,
            'status' => $this->status,
            'status_label' => DropshipStatuses::labels()[$this->status] ?? $this->status,
            'total_cost' => $this->total_cost,
            'total_cost_formatted' => $this->getTotalCostFormatted(),
            'total_cost_pounds' => $this->getTotalCostInPounds(),
            'total_retail' => $this->total_retail,
            'total_retail_formatted' => $this->getTotalRetailFormatted(),
            'total_retail_pounds' => $this->getTotalRetailInPounds(),
            'profit_margin' => $this->profit_margin,
            'profit_margin_formatted' => $this->getProfitMarginFormatted(),
            'profit_margin_pounds' => $this->getProfitMarginInPounds(),
            'profit_margin_percentage' => $this->getProfitMarginPercentage(),
            'profit_margin_percentage_formatted' => $this->getProfitMarginPercentageFormatted(),
            'shipping_address' => $this->shipping_address,
            'shipping_address_formatted' => $this->getShippingAddressFormatted(),
            'tracking_number' => $this->tracking_number,
            'carrier' => $this->carrier,
            'sent_to_supplier_at' => $this->sent_to_supplier_at,
            'confirmed_by_supplier_at' => $this->confirmed_by_supplier_at,
            'shipped_by_supplier_at' => $this->shipped_by_supplier_at,
            'delivered_at' => $this->delivered_at,
            'estimated_delivery' => $this->estimated_delivery,
            'estimated_delivery_formatted' => $this->getEstimatedDeliveryFormatted(),
            'days_until_delivery' => $this->getDaysUntilDelivery(),
            'supplier_response' => $this->supplier_response,
            'notes' => $this->notes,
            'supplier_notes' => $this->supplier_notes,
            'retry_count' => $this->retry_count,
            'last_retry_at' => $this->last_retry_at,
            'auto_retry_enabled' => $this->auto_retry_enabled,
            'webhook_data' => $this->when(
                $request->user()->hasPermission('manage_supplier_integrations'),
                $this->webhook_data
            ),
            'processing_time' => $this->getProcessingTime(),
            'processing_time_formatted' => $this->getProcessingTimeFormatted(),
            'is_pending' => $this->isPending(),
            'is_sent_to_supplier' => $this->isSentToSupplier(),
            'is_confirmed' => $this->isConfirmed(),
            'is_shipped' => $this->isShipped(),
            'is_delivered' => $this->isDelivered(),
            'is_cancelled' => $this->isCancelled(),
            'is_rejected' => $this->isRejected(),
            'is_completed' => $this->isCompleted(),
            'can_retry' => $this->canRetry(),
            'is_overdue' => $this->isOverdue(),
            'order' => new OrderResource($this->whenLoaded('order')),
            'supplier' => new SupplierResource($this->whenLoaded('supplier')),
            'dropship_order_items' => DropshipOrderItemResource::collection($this->whenLoaded('dropshipOrderItems')),
            'dropship_order_items_count' => $this->whenCounted('dropshipOrderItems'),
            'timeline' => $this->when(
                $this->relationLoaded('dropshipOrderItems') || $this->sent_to_supplier_at,
                function() {
                    return $this->getTimeline();
                }
            ),
            'health_status' => [
                'overall' => $this->getOverallHealthStatus(),
                'tracking_available' => $this->tracking_number ? 'good' : 'missing',
                'delivery_status' => $this->getDeliveryHealthStatus(),
                'communication_status' => $this->getCommunicationHealthStatus(),
                'retry_status' => $this->getRetryHealthStatus(),
            ],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    protected function getTimeline(): array
    {
        $timeline = [];

        $timeline[] = [
            'event' => 'created',
            'label' => 'Order Created',
            'timestamp' => $this->created_at,
            'status' => 'completed'
        ];

        if ($this->sent_to_supplier_at) {
            $timeline[] = [
                'event' => 'sent_to_supplier',
                'label' => 'Sent to Supplier',
                'timestamp' => $this->sent_to_supplier_at,
                'status' => 'completed'
            ];
        }

        if ($this->confirmed_by_supplier_at) {
            $timeline[] = [
                'event' => 'confirmed',
                'label' => 'Confirmed by Supplier',
                'timestamp' => $this->confirmed_by_supplier_at,
                'status' => 'completed'
            ];
        }

        if ($this->shipped_by_supplier_at) {
            $timeline[] = [
                'event' => 'shipped',
                'label' => 'Shipped by Supplier',
                'timestamp' => $this->shipped_by_supplier_at,
                'status' => 'completed'
            ];
        }

        if ($this->delivered_at) {
            $timeline[] = [
                'event' => 'delivered',
                'label' => 'Delivered',
                'timestamp' => $this->delivered_at,
                'status' => 'completed'
            ];
        } elseif ($this->estimated_delivery) {
            $timeline[] = [
                'event' => 'estimated_delivery',
                'label' => 'Estimated Delivery',
                'timestamp' => $this->estimated_delivery,
                'status' => $this->estimated_delivery->isPast() ? 'overdue' : 'pending'
            ];
        }

        return $timeline;
    }

    protected function getOverallHealthStatus(): string
    {
        if ($this->is_cancelled || $this->is_rejected) {
            return 'failed';
        }

        if ($this->is_delivered) {
            return 'excellent';
        }

        if ($this->isOverdue()) {
            return 'poor';
        }

        if ($this->is_shipped && $this->tracking_number) {
            return 'good';
        }

        if ($this->is_confirmed) {
            return 'fair';
        }

        if ($this->retry_count > 2) {
            return 'poor';
        }

        return 'fair';
    }

    protected function getDeliveryHealthStatus(): string
    {
        if ($this->is_delivered) {
            return 'delivered';
        }

        if ($this->isOverdue()) {
            return 'overdue';
        }

        if ($this->estimated_delivery) {
            $daysUntil = $this->getDaysUntilDelivery();
            if ($daysUntil !== null && $daysUntil <= 1) {
                return 'arriving_soon';
            }
            return 'on_track';
        }

        return 'unknown';
    }

    protected function getCommunicationHealthStatus(): string
    {
        if ($this->is_rejected) {
            return 'rejected';
        }

        if ($this->is_confirmed) {
            return 'good';
        }

        if ($this->sent_to_supplier_at && $this->sent_to_supplier_at->diffInHours(now()) > 24) {
            return 'delayed_response';
        }

        if ($this->sent_to_supplier_at) {
            return 'waiting_response';
        }

        return 'not_sent';
    }

    protected function getRetryHealthStatus(): string
    {
        if ($this->retry_count === 0) {
            return 'no_retries';
        }

        if ($this->retry_count <= 2) {
            return 'some_retries';
        }

        return 'many_retries';
    }
}
