<?php

namespace App\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderRefundResource extends JsonResource
{
    public function toArray(Request $request)
    {
        return [
            'id' => $this->id,
            'amount' => $this->amount,
            'amount_formatted' => $this->formatPrice($this->amount),
            'processed_at' => $this->processed_at,
            'notes' => $this->notes,
            'status' => $this->whenLoaded('status', function() {
                return [
                    'id' => $this->status->id,
                    'name' => $this->status->name
                ];
            }),
            'order_return' => new OrderReturnResource($this->whenLoaded('orderReturn')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    private function formatPrice(int $priceInPennies): string
    {
        return '£' . number_format($priceInPennies / 100, 2);
    }
}
