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
                    'product' => $item->product,
                    'product_variant' => $item->productVariant,
                    'quantity' => $item->quantity,
                    'order_returns' => $item->orderReturn ? [
                        'id' => $item->orderReturn->id,
                        'reason' => $item->orderReturn->reason,
                        'status' => $item->orderReturn->status->name ?? 'Unknown',
                        'created_at' => $item->orderReturn->created_at,
                        'updated_at' => $item->orderReturn->updated_at,
                    ] : null,
                ];
            }),
            'status' => $this->status->name ?? null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
