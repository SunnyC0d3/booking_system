<?php

namespace App\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EnhancedSearchResponse extends JsonResource
{
    protected $searchStats;
    protected $facets;
    protected $queryInfo;

    public function __construct($resource, array $searchStats = [], array $facets = [], array $queryInfo = [])
    {
        parent::__construct($resource);
        $this->searchStats = $searchStats;
        $this->facets = $facets;
        $this->queryInfo = $queryInfo;
    }

    public function toArray(Request $request): array
    {
        return [
            'data' => ProductResource::collectionWithSearchData(
                $this->resource->items(),
                ['query' => $this->queryInfo['original'] ?? '']
            ),
            'meta' => [
                'pagination' => [
                    'current_page' => $this->resource->currentPage(),
                    'per_page' => $this->resource->perPage(),
                    'total' => $this->resource->total(),
                    'last_page' => $this->resource->lastPage(),
                    'from' => $this->resource->firstItem(),
                    'to' => $this->resource->lastItem(),
                    'has_more_pages' => $this->resource->hasMorePages(),
                ],
                'search' => [
                    'query' => $this->queryInfo['original'] ?? '',
                    'processed_query' => $this->queryInfo['processed'] ?? '',
                    'search_time_ms' => $this->searchStats['search_time_ms'] ?? 0,
                    'total_results' => $this->resource->total(),
                    'has_results' => $this->resource->total() > 0,
                    'quality_score' => $this->searchStats['quality_score'] ?? null,
                    'filters_applied' => $this->queryInfo['filters_applied'] ?? [],
                ],
                'facets' => $this->facets,
                'suggestions' => $this->when(
                    empty($this->resource->items()) && !empty($this->queryInfo['suggestions']),
                    $this->queryInfo['suggestions'] ?? []
                ),
            ],
            'links' => [
                'first' => $this->resource->url(1),
                'last' => $this->resource->url($this->resource->lastPage()),
                'prev' => $this->resource->previousPageUrl(),
                'next' => $this->resource->nextPageUrl(),
            ],
        ];
    }
}
