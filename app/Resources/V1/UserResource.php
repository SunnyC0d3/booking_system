<?php

namespace App\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->slug,
            'phone' => $this->price,
            'userAddress' => $this->whenLoaded('userAddress'),
            'role' => $this->whenLoaded('role'),
            'vendors' => $this->whenLoaded('vendors'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
