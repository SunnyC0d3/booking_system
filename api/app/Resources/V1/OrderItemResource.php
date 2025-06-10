<?php

namespace App\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    public function toArray(Request $request)
    {
        return [
            'id' => $this->id,
            'product' => new ProductResource($this->whenLoaded('product')),
            'product_variant' => $this->whenLoaded('productVariant'),
            'quantity' => $this->quantity,
            'order_returns' => new OrderReturnResource($this->whenLoaded('orderReturn')),
            'order' => new OrderResource($this->whenLoaded('order')),
        ];
    }
}
