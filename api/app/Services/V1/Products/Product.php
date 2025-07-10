<?php

namespace App\Services\V1\Products;

use App\Constants\ProductStatuses;
use App\Models\Product as ProdDB;
use App\Services\V1\Media\SecureMedia;
use App\Services\V1\Search\QueryProcessor;
use App\Services\V1\Search\SearchRanker;
use App\Filters\V1\ProductFilter;
use App\Resources\V1\ProductResource;
use App\Resources\V1\EnhancedSearchResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Traits\V1\ApiResponses;
use App\Models\Vendor;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class Product
{
    use ApiResponses;

    protected SecureMedia $mediaService;
    protected QueryProcessor $queryProcessor;
    protected SearchRanker $searchRanker;

    public function __construct(
        SecureMedia $mediaService,
        QueryProcessor $queryProcessor,
        SearchRanker $searchRanker
    ) {
        $this->mediaService = $mediaService;
        $this->queryProcessor = $queryProcessor;
        $this->searchRanker = $searchRanker;
    }

    public function search(Request $request, ProductFilter $filter): EnhancedSearchResponse
    {
        $startTime = microtime(true);

        $searchQuery = $request->input('filter.search', '');
        $perPage = min($request->input('per_page', 15), 100);
        $page = $request->input('page', 1);

        $parsedQuery = null;
        if (!empty($searchQuery)) {
            $parsedQuery = $this->queryProcessor->parseQuery($searchQuery);
        }

        $query = ProdDB::with([
            'vendor:id,name,description',
            'variants.productAttribute:id,name',
            'variants:id,product_id,product_attribute_id,value,additional_price,quantity,low_stock_threshold',
            'category:id,name,parent_id',
            'tags:id,name',
            'productStatus:id,name',
            'media'
        ]);

        $query = $filter->apply($query);

        if (!$this->isAdminRequest($request)) {
            $query = $query->whereHas('productStatus', function($q) {
                $q->where('name', ProductStatuses::ACTIVE);
            });
        }

        $results = $query->paginate($perPage, ['*'], 'page', $page);

        $rankedProducts = $results->getCollection();
        $searchStats = [];

        if (!empty($searchQuery) && $rankedProducts->isNotEmpty()) {
            $rankingOptions = [
                'user_id' => $request->user()?->id,
                'session_data' => $this->getSessionData($request),
                'diversify' => $request->boolean('diversify', false),
                'explain' => $request->boolean('explain', false),
            ];

            $rankedProducts = $this->searchRanker->rankSearchResults(
                $rankedProducts,
                $searchQuery,
                $rankingOptions
            );

            $searchStats = $this->searchRanker->calculateSearchQuality($rankedProducts, $searchQuery);
        }

        $facets = $this->generateFacets($query, $request);

        $searchTimeMs = round((microtime(true) - $startTime) * 1000, 2);
        $searchStats['search_time_ms'] = $searchTimeMs;

        $queryInfo = [
            'original' => $searchQuery,
            'processed' => $parsedQuery?->cleaned ?? '',
            'filters_applied' => $this->getAppliedFilters($request),
            'suggestions' => $this->getSuggestions($searchQuery, $results->total()),
        ];

        $results->setCollection($rankedProducts);

        return new EnhancedSearchResponse($results, $searchStats, $facets, $queryInfo);
    }

    public function all(Request $request, ProductFilter $filter)
    {
        $request->validated();

        $query = ProdDB::with([
            'vendor:id,name,description',
            'variants.productAttribute:id,name',
            'variants:id,product_id,product_attribute_id,value,additional_price,quantity',
            'category:id,name,parent_id',
            'tags:id,name',
            'productStatus:id,name',
            'media'
        ])->filter($filter);

        $perPage = min($request->input('per_page', 15), 100);
        $products = $query->paginate($perPage);

        return ProductResource::collection($products)->additional([
            'message' => 'Products retrieved successfully.',
            'status' => 200,
            'meta' => [
                'search_time_ms' => 0,
                'total_results' => $products->total(),
            ]
        ]);
    }

    public function find(Request $request, ProdDB $product)
    {
        $product->load([
            'vendor:id,name,description',
            'variants.productAttribute:id,name',
            'variants:id,product_id,product_attribute_id,value,additional_price,quantity,low_stock_threshold',
            'category:id,name,parent_id',
            'tags:id,name',
            'productStatus:id,name',
            'media'
        ]);

        return $this->ok(
            'Product retrieved successfully.',
            new ProductResource($product)
        );
    }

    public function create(Request $request)
    {
        $user = $request->user();

        if (!$user->hasPermission('create_products')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        $data = $request->validated();
        $vendor = Vendor::where('user_id', $user->id)->first();

        if (!$vendor) {
            return $this->error('Vendor not found.', 404);
        }

        $product = DB::transaction(function () use ($data, $vendor, $request) {
            // Convert price to pennies
            $priceInPennies = isset($data['price']) ? intval($data['price'] * 100) : 0;

            $product = ProdDB::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'price' => $priceInPennies,
                'quantity' => $data['quantity'],
                'product_category_id' => $data['product_category_id'],
                'vendor_id' => $vendor->id,
                'product_status_id' => $data['product_status_id'],
                'low_stock_threshold' => $data['low_stock_threshold'] ?? 10,
                'search_keywords' => $this->generateSearchKeywords($data),
                'last_indexed_at' => now(),
            ]);

            if (!empty($data['product_tags'])) {
                $product->tags()->sync($data['product_tags']);
            }

            if (!empty($data['product_variants'])) {
                foreach ($data['product_variants'] as $variant) {
                    $additionalPriceInPennies = isset($variant['additional_price']) ?
                        intval($variant['additional_price'] * 100) : 0;

                    $product->variants()->create([
                        'product_id' => $product->id,
                        'product_attribute_id' => $variant['product_attribute_id'],
                        'value' => $variant['value'],
                        'additional_price' => $additionalPriceInPennies,
                        'quantity' => $variant['quantity'],
                        'low_stock_threshold' => $variant['low_stock_threshold'] ?? 5,
                    ]);
                }
            }

            if (!empty($data['media'])) {
                $this->handleSecureMediaUpload($product, $request, true);
            }
        });

        $product->load([
            'vendor', 'variants.productAttribute', 'category', 'tags', 'productStatus', 'media'
        ]);

        return $this->ok(
            'Product updated successfully.',
            new ProductResource($product)
        );
    }

    protected function generateSearchKeywords(array $data): array
    {
        $keywords = [];

        if (isset($data['name'])) {
            $keywords = array_merge($keywords, explode(' ', strtolower($data['name'])));
        }

        if (isset($data['description'])) {
            $words = explode(' ', strtolower(strip_tags($data['description'])));
            $keywords = array_merge($keywords, array_filter($words, function($word) {
                return strlen($word) > 2;
            }));
        }

        if (isset($data['product_variants'])) {
            foreach ($data['product_variants'] as $variant) {
                if (isset($variant['value'])) {
                    $keywords[] = strtolower($variant['value']);
                }
            }
        }

        $stopWords = ['the', 'and', 'for', 'with', 'from', 'this', 'that', 'are', 'was', 'will', 'been'];
        $keywords = array_diff(array_unique($keywords), $stopWords);

        return array_values($keywords);
    }

    protected function generateFacets($baseQuery, Request $request): array
    {
        $cacheKey = 'search_facets_' . md5($request->getQueryString() ?? '');

        return Cache::remember($cacheKey, 300, function () use ($baseQuery, $request) {
            $facets = [];

            try {
                $facetQuery = clone $baseQuery;

                $priceStats = $facetQuery->selectRaw('
                    MIN(price) as min_price,
                    MAX(price) as max_price,
                    AVG(price) as avg_price
                ')->first();

                if ($priceStats && $priceStats->min_price && $priceStats->max_price) {
                    $facets['price_ranges'] = $this->generatePriceRanges(
                        $priceStats->min_price / 100,
                        $priceStats->max_price / 100
                    );
                }

                $facets['categories'] = $this->getCategoryFacets($baseQuery);
                $facets['brands'] = $this->getBrandFacets($baseQuery);
                $facets['availability'] = $this->getAvailabilityFacets($baseQuery);
                $facets['attributes'] = $this->getAttributeFacets($baseQuery);

            } catch (\Exception $e) {
                Log::warning('Failed to generate facets: ' . $e->getMessage());
                $facets = [];
            }

            return $facets;
        });
    }

    protected function generatePriceRanges(float $minPrice, float $maxPrice): array
    {
        $ranges = [];
        $step = ($maxPrice - $minPrice) / 6;

        for ($i = 0; $i < 6; $i++) {
            $rangeMin = $minPrice + ($step * $i);
            $rangeMax = $i === 5 ? $maxPrice : $minPrice + ($step * ($i + 1));

            $ranges[] = [
                'label' => '£' . number_format($rangeMin, 0) . ' - £' . number_format($rangeMax, 0),
                'min' => round($rangeMin, 2),
                'max' => round($rangeMax, 2),
                'value' => round($rangeMin, 2) . ',' . round($rangeMax, 2),
            ];
        }

        return $ranges;
    }

    protected function getCategoryFacets($baseQuery): array
    {
        $categoryFacets = $baseQuery
            ->join('product_categories', 'products.product_category_id', '=', 'product_categories.id')
            ->groupBy('product_categories.id', 'product_categories.name')
            ->selectRaw('product_categories.id, product_categories.name, COUNT(*) as count')
            ->orderBy('count', 'desc')
            ->limit(10)
            ->get()
            ->toArray();

        return array_map(function ($facet) {
            return [
                'id' => $facet['id'],
                'name' => $facet['name'],
                'count' => $facet['count'],
                'value' => (string) $facet['id'],
            ];
        }, $categoryFacets);
    }

    protected function getBrandFacets($baseQuery): array
    {
        $brandFacets = $baseQuery
            ->join('vendors', 'products.vendor_id', '=', 'vendors.id')
            ->groupBy('vendors.id', 'vendors.name')
            ->selectRaw('vendors.id, vendors.name, COUNT(*) as count')
            ->orderBy('count', 'desc')
            ->limit(10)
            ->get()
            ->toArray();

        return array_map(function ($facet) {
            return [
                'id' => $facet['id'],
                'name' => $facet['name'],
                'count' => $facet['count'],
                'value' => (string) $facet['id'],
            ];
        }, $brandFacets);
    }

    protected function getAvailabilityFacets($baseQuery): array
    {
        $availabilityStats = $baseQuery
            ->selectRaw('
                SUM(CASE WHEN quantity > 0 THEN 1 ELSE 0 END) as in_stock_count,
                SUM(CASE WHEN quantity <= 0 THEN 1 ELSE 0 END) as out_of_stock_count,
                SUM(CASE WHEN quantity > 0 AND quantity <= low_stock_threshold THEN 1 ELSE 0 END) as low_stock_count
            ')
            ->first();

        $facets = [];

        if ($availabilityStats->in_stock_count > 0) {
            $facets[] = [
                'label' => 'In Stock',
                'value' => 'in_stock',
                'count' => $availabilityStats->in_stock_count,
            ];
        }

        if ($availabilityStats->low_stock_count > 0) {
            $facets[] = [
                'label' => 'Low Stock',
                'value' => 'low_stock',
                'count' => $availabilityStats->low_stock_count,
            ];
        }

        if ($availabilityStats->out_of_stock_count > 0) {
            $facets[] = [
                'label' => 'Out of Stock',
                'value' => 'out_of_stock',
                'count' => $availabilityStats->out_of_stock_count,
            ];
        }

        return $facets;
    }

    protected function getAttributeFacets($baseQuery): array
    {
        $attributeFacets = $baseQuery
            ->join('product_variants', 'products.id', '=', 'product_variants.product_id')
            ->join('product_attributes', 'product_variants.product_attribute_id', '=', 'product_attributes.id')
            ->groupBy('product_attributes.name', 'product_variants.value')
            ->selectRaw('
                product_attributes.name as attribute_name,
                product_variants.value,
                COUNT(DISTINCT products.id) as count
            ')
            ->having('count', '>', 0)
            ->orderBy('attribute_name')
            ->orderBy('count', 'desc')
            ->get();

        $grouped = [];
        foreach ($attributeFacets as $facet) {
            $grouped[$facet->attribute_name][] = [
                'value' => $facet->value,
                'count' => $facet->count,
                'filter_value' => strtolower($facet->attribute_name) . ':' . strtolower($facet->value),
            ];
        }

        return $grouped;
    }

    protected function getAppliedFilters(Request $request): array
    {
        $applied = [];
        $filterParams = $request->input('filter', []);

        foreach ($filterParams as $key => $value) {
            if (!empty($value) && $key !== 'search') {
                $applied[$key] = $value;
            }
        }

        return $applied;
    }

    protected function getSuggestions(string $query, int $resultCount): array
    {
        if ($resultCount > 0 || empty($query)) {
            return [];
        }

        $suggestions = [];

        $commonTypos = [
            'headfones' => 'headphones',
            'computor' => 'computer',
            'phon' => 'phone',
            'labtop' => 'laptop',
        ];

        $queryLower = strtolower($query);
        foreach ($commonTypos as $typo => $correction) {
            if (str_contains($queryLower, $typo)) {
                $suggestions[] = str_replace($typo, $correction, $queryLower);
            }
        }

        $popularTerms = Cache::get('popular_search_terms', [
            'headphones', 'laptop', 'phone', 'shoes', 'clothing'
        ]);

        foreach ($popularTerms as $term) {
            if (levenshtein(strtolower($query), $term) <= 2) {
                $suggestions[] = $term;
            }
        }

        return array_unique(array_slice($suggestions, 0, 5));
    }

    protected function getSessionData(Request $request): array
    {
        return [
            'viewed_categories' => session('viewed_categories', []),
            'viewed_brands' => session('viewed_brands', []),
            'search_history' => session('search_history', []),
        ];
    }

    protected function isAdminRequest(Request $request): bool
    {
        return $request->user() && $request->user()->hasRole(['admin', 'super admin']);
    }

    protected function handleSecureMediaUpload(ProdDB $product, Request $request, bool $isUpdate = false): void
    {
        try {
            if ($request->hasFile('media.featured_image')) {
                if ($isUpdate) {
                    $product->clearMediaCollection('featured_image');
                }

                $this->mediaService->addSecureMedia(
                    $product,
                    $request->file('media.featured_image'),
                    'featured_image'
                );
            }

            if ($request->hasFile('media.gallery')) {
                if ($isUpdate) {
                    $product->clearMediaCollection('gallery');
                }

                $galleryFiles = $request->file('media.gallery');
                if (!is_array($galleryFiles)) {
                    $galleryFiles = [$galleryFiles];
                }

                foreach ($galleryFiles as $galleryFile) {
                    if ($galleryFile && $galleryFile->isValid()) {
                        $this->mediaService->addSecureMedia(
                            $product,
                            $galleryFile,
                            'gallery'
                        );
                    }
                }
            }

        } catch (\Exception $e) {
            Log::error('Product media upload failed', [
                'product_id' => $product->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw new \Exception('Failed to process media files: ' . $e->getMessage());
        }
    }

    public function softDelete(Request $request, ProdDB $product)
    {
        $user = $request->user();

        if (!$user->hasPermission('delete_products')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        $product->update(['last_indexed_at' => now()]);
        $product->delete();

        return $this->ok('Product deleted successfully.');
    }

    public function restore(Request $request, int $id)
    {
        $user = $request->user();

        if (!$user->hasPermission('restore_products')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        $product = ProdDB::withTrashed()->findOrFail($id);

        if (!$product->trashed()) {
            return $this->error('Product is not deleted.', 400);
        }

        $product->restore();
        $product->update(['last_indexed_at' => now()]);

        $product->load([
            'vendor', 'variants.productAttribute', 'category', 'tags', 'productStatus', 'media'
        ]);

        return $this->ok(
            'Product restored successfully.',
            new ProductResource($product)
        );
    }

    /**
     * Force delete with cleanup
     */
    public function delete(Request $request, int $id)
    {
        $user = $request->user();

        if (!$user->hasPermission('force_delete_products')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        $product = ProdDB::withTrashed()->findOrFail($id);

        if (!$product->trashed()) {
            return $this->error('Product must be soft deleted before force deleting.', 400);
        }

        DB::transaction(function () use ($product) {
            $product->clearMediaCollection('featured_image');
            $product->clearMediaCollection('gallery');
            $product->variants()->forceDelete();
            $product->tags()->detach();
            $product->forceDelete();
        });

        return $this->ok('Product deleted successfully.');
    }

    public function update(Request $request, ProdDB $product)
    {
        $user = $request->user();

        if (!$user->hasPermission('edit_products')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        $data = $request->validated();

        $product = DB::transaction(function () use ($data, $product, $request) {
            $updateData = [];

            if (isset($data['name'])) {
                $updateData['name'] = $data['name'];
            }

            if (isset($data['description'])) {
                $updateData['description'] = $data['description'];
            }

            if (isset($data['price'])) {
                $updateData['price'] = intval($data['price'] * 100);
            }

            if (isset($data['quantity'])) {
                $updateData['quantity'] = $data['quantity'];
            }

            if (isset($data['product_category_id'])) {
                $updateData['product_category_id'] = $data['product_category_id'];
            }

            if (isset($data['product_status_id'])) {
                $updateData['product_status_id'] = $data['product_status_id'];
            }

            if (isset($data['low_stock_threshold'])) {
                $updateData['low_stock_threshold'] = $data['low_stock_threshold'];
            }

            $updateData['search_keywords'] = $this->generateSearchKeywords(array_merge($product->toArray(), $data));
            $updateData['last_indexed_at'] = now();

            $product->update($updateData);

            if (isset($data['product_tags'])) {
                $product->tags()->sync($data['product_tags']);
            }

            if (isset($data['product_variants'])) {
                $product->variants()->delete();

                foreach ($data['product_variants'] as $variant) {
                    $additionalPriceInPennies = isset($variant['additional_price']) ?
                        intval($variant['additional_price'] * 100) : 0;

                    $product->variants()->create([
                        'product_id' => $product->id,
                        'product_attribute_id' => $variant['product_attribute_id'],
                        'value' => $variant['value'],
                        'additional_price' => $additionalPriceInPennies,
                        'quantity' => $variant['quantity'],
                        'low_stock_threshold' => $variant['low_stock_threshold'] ?? 5,
                    ]);
                }
            }

            if (!empty($data['media'])) {
                $this->handleSecureMediaUpload($product, $request, true);
            }

            return $product;
        });

        $product->load([
            'vendor',
            'variants.productAttribute',
            'category',
            'tags',
            'productStatus',
            'media'
        ]);

        return $this->ok(
            'Product updated successfully.',
            new ProductResource($product)
        );
    }
}
