<?php

namespace App\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request)
    {
        return [
            'id' => $this->id,
            'user' => $this->whenLoaded('user'),
            'total_amount' => $this->total_amount,
            'orderItem' => $this->orderItems->map(function ($item) {
                return [
                    'id' => $item->id,
                    'product' => $item->product, // assume eager loaded
                    'product_variant' => $item->productVariant, // assume eager loaded
                    'quantity' => $item->quantity,
                    'order_returns' => $item->orderReturn?->map(function ($return) {
                        return [
                            'id' => $return->id,
                            'reason' => $return->reason,
                            'status' => $return->status->name ?? 'Unknown',
                            'created_at' => $return->created_at,
                            'updated_at' => $return->updated_at,
                        ];
                    }) ?? [],
                ];
            }),
            'status' => $this->status->name ?? null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
