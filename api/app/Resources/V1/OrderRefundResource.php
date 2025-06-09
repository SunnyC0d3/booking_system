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
            'processed_at' => $this->processed_at,
            'notes' => $this->notes,
            'status' => $this->status->name
        ];
    }
}
