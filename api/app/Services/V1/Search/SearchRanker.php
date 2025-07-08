<?php

namespace App\Services\V1\Search;

use App\Constants\ProductStatuses;
use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class SearchRanker
{
    protected array $boostFactors = [
        'exact_name_match' => 100,
        'name_starts_with' => 75,
        'name_contains' => 50,
        'description_contains' => 25,
        'in_stock' => 20,
        'high_quantity' => 15,
        'recent_product' => 10,
        'has_variants' => 5,
        'has_media' => 5,
    ];

    protected array $penaltyFactors = [
        'out_of_stock' => -50,
        'low_stock' => -10,
        'inactive_status' => -100,
        'old_product' => -5,
    ];

    public function calculateRelevance(Product $product, string $query): float
    {
        $score = 0;
        $queryLower = strtolower($query);
        $nameLower = strtolower($product->name);
        $descriptionLower = strtolower($product->description ?? '');

        if ($nameLower === $queryLower) {
            $score += $this->boostFactors['exact_name_match'];
        } elseif (str_starts_with($nameLower, $queryLower)) {
            $score += $this->boostFactors['name_starts_with'];
        } elseif (str_contains($nameLower, $queryLower)) {
            $score += $this->boostFactors['name_contains'];
        }

        if (str_contains($descriptionLower, $queryLower)) {
            $score += $this->boostFactors['description_contains'];
        }

        $queryWords = explode(' ', $queryLower);
        $nameWords = explode(' ', $nameLower);

        $matchingWords = array_intersect($queryWords, $nameWords);
        $score += count($matchingWords) * 10;

        foreach ($queryWords as $queryWord) {
            foreach ($nameWords as $nameWord) {
                if (strlen($queryWord) >= 3 && str_contains($nameWord, $queryWord)) {
                    $score += 5;
                }
            }
        }

        return $score;
    }

    public function applyBoostFactors(Collection $products, string $query = ''): Collection
    {
        return $products->map(function (Product $product) use ($query) {
            $boostScore = 0;

            if (!empty($query)) {
                $boostScore += $this->calculateRelevance($product, $query);
            }

            if ($product->quantity > 0) {
                $boostScore += $this->boostFactors['in_stock'];

                if ($product->quantity > 50) {
                    $boostScore += $this->boostFactors['high_quantity'];
                } elseif ($product->quantity <= $product->low_stock_threshold) {
                    $boostScore += $this->penaltyFactors['low_stock'];
                }
            } else {
                $boostScore += $this->penaltyFactors['out_of_stock'];
            }

            if ($product->productStatus && $product->productStatus->name !== ProductStatuses::ACTIVE) {
                $boostScore += $this->penaltyFactors['inactive_status'];
            }

            $daysSinceCreation = $product->created_at->diffInDays(now());
            if ($daysSinceCreation <= 30) {
                $boostScore += $this->boostFactors['recent_product'];
            } elseif ($daysSinceCreation > 365) {
                $boostScore += $this->penaltyFactors['old_product'];
            }

            if ($product->relationLoaded('variants') && $product->variants->count() > 0) {
                $boostScore += $this->boostFactors['has_variants'];
            }

            if ($product->getMedia('featured_image')->count() > 0) {
                $boostScore += $this->boostFactors['has_media'];
            }

            $product->calculated_relevance_score = $boostScore;

            return $product;
        });
    }

    public function sortByMultipleCriteria(Collection $products, array $criteria): Collection
    {
        return $products->sortBy(function (Product $product) use ($criteria) {
            $sortValue = '';

            foreach ($criteria as $criterion) {
                switch ($criterion['field']) {
                    case 'relevance':
                        $value = $product->calculated_relevance_score ?? 0;
                        $sortValue .= sprintf('%010d', $criterion['direction'] === 'desc' ? (999999 - $value) : $value);
                        break;

                    case 'price':
                        $value = $product->price ?? 0;
                        $sortValue .= sprintf('%010d', $criterion['direction'] === 'desc' ? (999999 - $value) : $value);
                        break;

                    case 'name':
                        $sortValue .= $criterion['direction'] === 'desc'
                            ? str_pad(substr(strrev($product->name), 0, 20), 20, 'z')
                            : str_pad(substr($product->name, 0, 20), 20, ' ');
                        break;

                    case 'created_at':
                        $timestamp = $product->created_at->timestamp;
                        $sortValue .= sprintf('%010d', $criterion['direction'] === 'desc' ? (9999999999 - $timestamp) : $timestamp);
                        break;

                    case 'quantity':
                        $value = $product->quantity ?? 0;
                        $sortValue .= sprintf('%010d', $criterion['direction'] === 'desc' ? (999999 - $value) : $value);
                        break;
                }
            }

            return $sortValue;
        });
    }

    public function applyIntelligentRanking(Collection $products, array $options = []): Collection
    {
        $userId = $options['user_id'] ?? null;
        $sessionData = $options['session_data'] ?? [];

        return $products->map(function (Product $product) use ($userId, $sessionData) {
            $intelligenceScore = 0;

            if ($userId) {
                $intelligenceScore += $this->calculateUserPreferenceScore($product, $userId);
            }

            if (!empty($sessionData['viewed_categories'])) {
                if (in_array($product->product_category_id, $sessionData['viewed_categories'])) {
                    $intelligenceScore += 15;
                }
            }

            if (!empty($sessionData['viewed_brands'])) {
                if (in_array($product->vendor_id, $sessionData['viewed_brands'])) {
                    $intelligenceScore += 10;
                }
            }

            $popularityScore = $this->getProductPopularityScore($product->id);
            $intelligenceScore += $popularityScore;

            $trendingScore = $this->getTrendingScore($product->id);
            $intelligenceScore += $trendingScore;

            $seasonalScore = $this->getSeasonalScore($product);
            $intelligenceScore += $seasonalScore;

            $product->intelligence_score = $intelligenceScore;
            $product->final_score = ($product->calculated_relevance_score ?? 0) + $intelligenceScore;

            return $product;
        });
    }

    protected function calculateUserPreferenceScore(Product $product, int $userId): float
    {
        $cacheKey = "user_preferences_{$userId}";

        $preferences = Cache::remember($cacheKey, 3600, function () use ($userId) {
            return [
                'preferred_categories' => [],
                'preferred_brands' => [],
                'price_range' => null,
            ];
        });

        $score = 0;

        if (in_array($product->product_category_id, $preferences['preferred_categories'])) {
            $score += 25;
        }

        if (in_array($product->vendor_id, $preferences['preferred_brands'])) {
            $score += 20;
        }

        if ($preferences['price_range']) {
            $minPrice = $preferences['price_range']['min'] ?? 0;
            $maxPrice = $preferences['price_range']['max'] ?? PHP_INT_MAX;

            if ($product->price >= $minPrice && $product->price <= $maxPrice) {
                $score += 15;
            }
        }

        return $score;
    }

    protected function getProductPopularityScore(int $productId): float
    {
        $cacheKey = "product_popularity_{$productId}";

        return Cache::remember($cacheKey, 1800, function () use ($productId) {
            return rand(0, 20);
        });
    }

    protected function getTrendingScore(int $productId): float
    {
        $cacheKey = "trending_score_{$productId}";

        return Cache::remember($cacheKey, 900, function () use ($productId) {
            return rand(0, 15);
        });
    }

    protected function getSeasonalScore(Product $product): float
    {
        $currentMonth = now()->month;
        $score = 0;

        $productText = strtolower($product->name . ' ' . $product->description);

        $seasonalTerms = [
            'winter' => [12, 1, 2],
            'christmas' => [11, 12],
            'holiday' => [11, 12],
            'summer' => [6, 7, 8],
            'spring' => [3, 4, 5],
            'fall' => [9, 10, 11],
            'autumn' => [9, 10, 11],
            'beach' => [5, 6, 7, 8],
            'swimwear' => [4, 5, 6, 7, 8],
            'coat' => [10, 11, 12, 1, 2],
            'jacket' => [9, 10, 11, 12, 1, 2, 3],
            'scarf' => [10, 11, 12, 1, 2],
            'boots' => [9, 10, 11, 12, 1, 2],
            'sandals' => [4, 5, 6, 7, 8, 9],
        ];

        foreach ($seasonalTerms as $term => $months) {
            if (str_contains($productText, $term) && in_array($currentMonth, $months)) {
                $score += 10;
            }
        }

        return $score;
    }

    public function diversifyResults(Collection $products, array $options = []): Collection
    {
        $maxPerCategory = $options['max_per_category'] ?? 5;
        $maxPerBrand = $options['max_per_brand'] ?? 3;

        $categoryCount = [];
        $brandCount = [];
        $diversifiedProducts = collect();

        foreach ($products as $product) {
            $categoryId = $product->product_category_id;
            $brandId = $product->vendor_id;

            $categoryCount[$categoryId] = ($categoryCount[$categoryId] ?? 0) + 1;
            $brandCount[$brandId] = ($brandCount[$brandId] ?? 0) + 1;

            if ($categoryCount[$categoryId] <= $maxPerCategory &&
                $brandCount[$brandId] <= $maxPerBrand) {
                $diversifiedProducts->push($product);
            }
        }

        return $diversifiedProducts;
    }

    public function generateExplanations(Collection $products, string $query): Collection
    {
        return $products->map(function (Product $product) use ($query) {
            $explanations = [];

            if ($product->calculated_relevance_score ?? 0 > 0) {
                $explanations[] = "Relevance score: {$product->calculated_relevance_score}";
            }

            $queryLower = strtolower($query);
            $nameLower = strtolower($product->name);

            if (str_contains($nameLower, $queryLower)) {
                $explanations[] = "Product name matches your search";
            }

            if ($product->description && str_contains(strtolower($product->description), $queryLower)) {
                $explanations[] = "Product description matches your search";
            }

            // Boost explanations
            if ($product->quantity > 0) {
                $explanations[] = "Product is in stock";
            } else {
                $explanations[] = "Product is out of stock";
            }

            if ($product->created_at->diffInDays(now()) <= 30) {
                $explanations[] = "Recently added product";
            }

            if ($product->intelligence_score ?? 0 > 0) {
                $explanations[] = "Recommended based on preferences";
            }

            $product->search_explanations = $explanations;

            return $product;
        });
    }

    public function applyBusinessBoosts(Collection $products): Collection
    {
        return $products->map(function (Product $product) {
            $businessScore = 0;

            if ($product->search_keywords &&
                is_array($product->search_keywords) &&
                in_array('featured', $product->search_keywords)) {
                $businessScore += 30;
            }

            if ($product->search_keywords &&
                is_array($product->search_keywords) &&
                (in_array('sale', $product->search_keywords) || in_array('clearance', $product->search_keywords))) {
                $businessScore += 20;
            }

            if ($product->created_at->diffInDays(now()) <= 7) {
                $businessScore += 25;
            }

            $product->business_boost_score = $businessScore;
            $product->final_score = ($product->final_score ?? 0) + $businessScore;

            return $product;
        });
    }

    public function rankSearchResults(Collection $products, string $query, array $options = []): Collection
    {
        $products = $this->applyBoostFactors($products, $query);
        $products = $this->applyIntelligentRanking($products, $options);
        $products = $this->applyBusinessBoosts($products);
        $products = $products->sortByDesc('final_score');

        if ($options['diversify'] ?? false) {
            $products = $this->diversifyResults($products, $options);
        }

        if ($options['explain'] ?? false) {
            $products = $this->generateExplanations($products, $query);
        }

        return $products->values();
    }

    public function getBoostFactors(): array
    {
        return $this->boostFactors;
    }

    public function getPenaltyFactors(): array
    {
        return $this->penaltyFactors;
    }

    public function updateBoostFactors(array $newFactors): void
    {
        $this->boostFactors = array_merge($this->boostFactors, $newFactors);
    }

    public function calculateSearchQuality(Collection $products, string $query): array
    {
        $totalProducts = $products->count();
        $relevantProducts = $products->filter(function ($product) {
            return ($product->calculated_relevance_score ?? 0) > 0;
        })->count();

        $inStockProducts = $products->filter(function ($product) {
            return $product->quantity > 0;
        })->count();

        $qualityScore = 0;
        if ($totalProducts > 0) {
            $qualityScore = ($relevantProducts / $totalProducts) * 100;
        }

        return [
            'total_results' => $totalProducts,
            'relevant_results' => $relevantProducts,
            'in_stock_results' => $inStockProducts,
            'quality_score' => round($qualityScore, 2),
            'avg_relevance_score' => $products->avg('calculated_relevance_score') ?? 0,
            'query_length' => strlen($query),
            'has_results' => $totalProducts > 0,
        ];
    }
}
