<?php

namespace App\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductVariantResource extends JsonResource
{
    public function toArray(Request $request)
    {
        return [
            'id' => $this->id,
            'value' => $this->value,
            'additional_price' => $this->additional_price,
            'additional_price_formatted' => $this->additional_price ? $this->formatPrice($this->additional_price) : null,
            'quantity' => $this->quantity,
            'product' => new ProductResource($this->whenLoaded('product')),
            'product_attribute' => $this->whenLoaded('productAttribute', function() {
                return [
                    'id' => $this->productAttribute->id,
                    'name' => $this->productAttribute->name
                ];
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    private function formatPrice(int $priceInPennies): string
    {
        return '£' . number_format($priceInPennies / 100, 2);
    }
}
