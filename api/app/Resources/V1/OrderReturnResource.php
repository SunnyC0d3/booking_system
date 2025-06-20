<?php

namespace App\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderReturnResource extends JsonResource
{
    public function toArray(Request $request)
    {
        return [
            'id' => $this->id,
            'reason' => $this->reason,
            'status' => $this->whenLoaded('status', function() {
                return [
                    'id' => $this->status->id,
                    'name' => $this->status->name
                ];
            }),
            'order_item' => new OrderItemResource($this->whenLoaded('orderItem')),
            'order_refunds' => OrderRefundResource::collection($this->whenLoaded('orderRefunds')),
            'has_refunds' => $this->hasRefunds(),
            'total_refunded_amount' => $this->getTotalRefundedAmount(),
            'total_refunded_amount_formatted' => $this->formatPrice($this->getTotalRefundedAmount()),
            'is_approved' => $this->isApproved(),
            'is_completed' => $this->isCompleted(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    private function formatPrice(int $priceInPennies): string
    {
        return '£' . number_format($priceInPennies / 100, 2);
    }
}
