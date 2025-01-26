<?php

namespace App\Resources\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'price' => $this->price,
            'quantity' => $this->quantity,
            'description' => $this->description,
            'categories' => $this->whenLoaded('category'),
            'images' => $this->getMedia('featured_image')->map(function ($media) {
                return [
                    'url' => $media->getUrl(),
                    'name' => $media->name,
                    'type' => $media->mime_type,
                ];
            }),
            'attributes' => $this->whenLoaded('productVariants'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
