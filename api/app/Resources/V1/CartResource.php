<?php

namespace App\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'session_id' => $this->when(!$this->user_id, $this->session_id),
            'total_amount' => $this->getTotalAmountInPennies(),
            'total_amount_formatted' => $this->getTotalAmountFormatted(),
            'total_items_count' => $this->getTotalItemsCount(),
            'expires_at' => $this->expires_at,
            'is_expired' => $this->isExpired(),
            'is_empty' => $this->isEmpty(),
            'items' => CartItemResource::collection($this->whenLoaded('cartItems')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
