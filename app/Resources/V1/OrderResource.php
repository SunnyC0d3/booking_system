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
            'orderItem' => $this->whenLoaded('orderItems'),
            'status' => $this->whenLoaded('status'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
