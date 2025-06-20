<?php

namespace App\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price,
            'price_formatted' => $this->formatPrice($this->price),
            'quantity' => $this->quantity,
            'product_status' => $this->whenLoaded('productStatus', function() {
                return [
                    'id' => $this->productStatus->id,
                    'name' => $this->productStatus->name
                ];
            }),
            'category' => $this->whenLoaded('category', function() {
                return [
                    'id' => $this->category->id,
                    'name' => $this->category->name,
                    'parent_id' => $this->category->parent_id
                ];
            }),
            'vendor' => new VendorResource($this->whenLoaded('vendor')),
            'variants' => ProductVariantResource::collection($this->whenLoaded('variants')),
            'tags' => ProductTagResource::collection($this->whenLoaded('tags')),
            'featured_image' => $this->getFirstMediaUrl('featured_image'),
            'gallery' => $this->getMedia('gallery')->map(function ($media) {
                return [
                    'id' => $media->id,
                    'url' => $media->getUrl(),
                    'name' => $media->name,
                    'file_name' => $media->file_name,
                    'mime_type' => $media->mime_type,
                    'size' => $media->size,
                ];
            }),
            'media_count' => [
                'featured_image' => $this->getMedia('featured_image')->count(),
                'gallery' => $this->getMedia('gallery')->count(),
            ],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }

    private function formatPrice(int $priceInPennies): string
    {
        return '£' . number_format($priceInPennies / 100, 2);
    }
}
