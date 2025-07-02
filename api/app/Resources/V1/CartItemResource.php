<?php

namespace App\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_variant_id' => $this->product_variant_id,
            'quantity' => $this->quantity,
            'price_snapshot' => $this->price_snapshot,
            'price_formatted' => $this->getPriceFormatted(),
            'line_total' => $this->getLineTotalInPennies(),
            'line_total_formatted' => $this->getLineTotalFormatted(),
            'current_price' => $this->getCurrentProductPrice(),
            'has_price_changed' => $this->hasPriceChanged(),
            'price_change' => $this->when($this->hasPriceChanged(), $this->getPriceChange()),
            'is_available' => $this->isProductAvailable(),
            'available_stock' => $this->getAvailableStock(),
            'product' => $this->whenLoaded('product', function () {
                return [
                    'id' => $this->product->id,
                    'name' => $this->product->name,
                    'description' => $this->product->description,
                    'price' => $this->product->price,
                    'price_formatted' => $this->product->price_formatted,
                    'featured_image' => $this->product->featured_image,
                    'status' => $this->product->productStatus?->name,
                ];
            }),
            'product_variant' => $this->whenLoaded('productVariant', function () {
                return $this->productVariant ? [
                    'id' => $this->productVariant->id,
                    'value' => $this->productVariant->value,
                    'additional_price' => $this->productVariant->additional_price,
                    'additional_price_formatted' => $this->productVariant->additional_price_formatted,
                    'quantity' => $this->productVariant->quantity,
                    'product_attribute' => $this->productVariant->productAttribute ? [
                        'id' => $this->productVariant->productAttribute->id,
                        'name' => $this->productVariant->productAttribute->name,
                    ] : null,
                ] : null;
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
