<?php

namespace App\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    protected $searchMetadata = [];

    public function __construct($resource, array $searchMetadata = [])
    {
        parent::__construct($resource);
        $this->searchMetadata = $searchMetadata;
    }

    public function toArray(Request $request): array
    {
        $baseData = [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price,
            'price_formatted' => $this->formatPrice($this->price),
            'quantity' => $this->quantity,
            'is_in_stock' => $this->quantity > 0,
            'is_low_stock' => $this->isLowStock(),
            'stock_status' => $this->getStockStatus(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];

        // Add relationships
        $baseData = array_merge($baseData, $this->getRelationshipData());

        // Add search-specific metadata if available
        if (!empty($this->searchMetadata) || $this->hasSearchScores()) {
            $baseData['search_metadata'] = $this->getSearchMetadata();
        }

        // Add media information
        $baseData = array_merge($baseData, $this->getMediaData());

        return $baseData;
    }

    /**
     * Get relationship data
     */
    protected function getRelationshipData(): array
    {
        $data = [];

        // Product status
        $data['product_status'] = $this->whenLoaded('productStatus', function() {
            return [
                'id' => $this->productStatus->id,
                'name' => $this->productStatus->name
            ];
        });

        // Category with breadcrumb support
        $data['category'] = $this->whenLoaded('category', function() {
            $categoryData = [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'parent_id' => $this->category->parent_id
            ];

            // Add parent category if loaded
            if ($this->category->relationLoaded('parent') && $this->category->parent) {
                $categoryData['parent'] = [
                    'id' => $this->category->parent->id,
                    'name' => $this->category->parent->name,
                    'parent_id' => $this->category->parent->parent_id
                ];
            }

            return $categoryData;
        });

        // Vendor information
        $data['vendor'] = $this->whenLoaded('vendor', function() {
            return [
                'id' => $this->vendor->id,
                'name' => $this->vendor->name,
                'description' => $this->vendor->description,
                'logo' => $this->vendor->getFirstMediaUrl('logo'),
                'products_count' => $this->whenCounted('vendor.products')
            ];
        });

        // Variants with enhanced data
        $data['variants'] = $this->relationLoaded('variants', function() {
            return $this->variants->map(function($variant) {
                return [
                    'id' => $variant->id,
                    'value' => $variant->value,
                    'additional_price' => $variant->additional_price,
                    'additional_price_formatted' => $variant->additional_price ?
                        $this->formatPrice($variant->additional_price) : null,
                    'total_price' => $this->price + ($variant->additional_price ?? 0),
                    'total_price_formatted' => $this->formatPrice($this->price + ($variant->additional_price ?? 0)),
                    'quantity' => $variant->quantity,
                    'is_available' => $variant->quantity > 0,
                    'is_low_stock' => $variant->isLowStock(),
                    'product_attribute' => $variant->whenLoaded('productAttribute', function() use ($variant) {
                        return [
                            'id' => $variant->productAttribute->id,
                            'name' => $variant->productAttribute->name
                        ];
                    }),
                    'created_at' => $variant->created_at,
                    'updated_at' => $variant->updated_at,
                ];
            });
        });

        // Tags
        $data['tags'] = $this->whenLoaded('tags', function() {
            return $this->tags->map(function($tag) {
                return [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'products_count' => $this->whenCounted('tags.products')
                ];
            });
        });

        return $data;
    }

    protected function getSearchMetadata(): array
    {
        $metadata = [];

        // Relevance scores
        if (isset($this->relevance_score)) {
            $metadata['relevance_score'] = round($this->relevance_score, 2);
        }

        if (isset($this->search_score)) {
            $metadata['search_score'] = round($this->search_score, 2);
        }

        if (isset($this->calculated_relevance_score)) {
            $metadata['calculated_relevance_score'] = round($this->calculated_relevance_score, 2);
        }

        if (isset($this->intelligence_score)) {
            $metadata['intelligence_score'] = round($this->intelligence_score, 2);
        }

        if (isset($this->business_boost_score)) {
            $metadata['business_boost_score'] = round($this->business_boost_score, 2);
        }

        if (isset($this->final_score)) {
            $metadata['final_score'] = round($this->final_score, 2);
        }

        if (isset($this->search_explanations)) {
            $metadata['explanations'] = $this->search_explanations;
        }

        if (isset($this->searchMetadata['highlights'])) {
            $metadata['highlights'] = $this->searchMetadata['highlights'];
        }

        if (isset($this->searchMetadata['query_match'])) {
            $metadata['query_match'] = $this->searchMetadata['query_match'];
        }

        if (isset($this->searchMetadata['position'])) {
            $metadata['position'] = $this->searchMetadata['position'];
        }

        return $metadata;
    }

    protected function getMediaData(): array
    {
        $data = [];

        $data['featured_image'] = $this->getFirstMediaUrl('featured_image') ?: null;

        $data['gallery'] = $this->getMedia('gallery')->map(function ($media) {
            return [
                'id' => $media->id,
                'url' => $media->getUrl(),
                'name' => $media->name,
                'file_name' => $media->file_name,
                'mime_type' => $media->mime_type,
                'size' => $media->size,
                'alt_text' => $media->getCustomProperty('alt_text', ''),
            ];
        });

        $data['media_count'] = [
            'featured_image' => $this->getMedia('featured_image')->count(),
            'gallery' => $this->getMedia('gallery')->count(),
            'total' => $this->getMedia()->count(),
        ];

        return $data;
    }

    protected function hasSearchScores(): bool
    {
        return isset($this->relevance_score) ||
            isset($this->search_score) ||
            isset($this->calculated_relevance_score) ||
            isset($this->intelligence_score) ||
            isset($this->final_score);
    }

    protected function getStockStatus(): string
    {
        if ($this->quantity <= 0) {
            return 'out_of_stock';
        } elseif ($this->quantity <= $this->low_stock_threshold) {
            return 'low_stock';
        } else {
            return 'in_stock';
        }
    }

    protected function formatPrice(int $priceInPennies): string
    {
        return '£' . number_format($priceInPennies / 100, 2);
    }

    public static function collectionWithSearchData($resource, array $searchMetadata = [])
    {
        return collect($resource)->map(function ($item, $index) use ($searchMetadata) {
            $itemMetadata = $searchMetadata;
            $itemMetadata['position'] = $index + 1;

            return new static($item, $itemMetadata);
        });
    }
}
