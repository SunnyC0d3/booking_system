<?php

namespace App\Filters\V1;

use App\Constants\ProductStatuses;
use App\Services\V1\Search\QueryProcessor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProductFilter extends QueryFilter
{
    protected QueryProcessor $queryProcessor;

    protected array $sortable = [
        'name' => 'name',
        'price' => 'price',
        'quantity' => 'quantity',
        'created_at' => 'created_at',
        'updated_at' => 'updated_at',
        'relevance' => 'search_score',
    ];

    public function __construct($request)
    {
        parent::__construct($request);
        $this->queryProcessor = new QueryProcessor();
    }

    public function search(string $value): Builder
    {
        if (empty(trim($value))) {
            return $this->builder;
        }

        try {
            $parsedQuery = $this->queryProcessor->parseQuery($value);

            if ($parsedQuery->hasTerms()) {
                $this->builder = $this->applyFullTextSearch($parsedQuery);
            }

            if ($parsedQuery->hasFilters()) {
                $this->builder = $this->applyQueryFilters($parsedQuery->filters);
            }

            return $this->builder;
        } catch (\Exception $e) {
            return $this->fallbackToSimpleSearch($value);
        }
    }

    protected function applyFullTextSearch($parsedQuery): Builder
    {
        $fulltextQuery = $this->buildSafeFulltextQuery($parsedQuery);
        $searchTerms = $parsedQuery->terms;
        $cleanedQuery = $parsedQuery->cleaned;

        if (empty($fulltextQuery) || strlen($cleanedQuery) < 2) {
            return $this->applyComprehensiveSearch($searchTerms, $cleanedQuery);
        }

        try {
            return $this->builder
                ->selectRaw('
                products.*,
                MATCH(products.name, products.description) AGAINST(? IN BOOLEAN MODE) as relevance_score,
                CASE
                    -- Exact product name match (highest priority)
                    WHEN LOWER(products.name) = LOWER(?) THEN 1000

                    -- Product name starts with search
                    WHEN LOWER(products.name) LIKE LOWER(?) THEN 800

                    -- Product name contains search
                    WHEN LOWER(products.name) LIKE LOWER(?) THEN 600

                    -- Category exact match
                    WHEN EXISTS (
                        SELECT 1 FROM product_categories pc
                        WHERE pc.id = products.product_category_id
                        AND LOWER(pc.name) = LOWER(?)
                    ) THEN 500

                    -- Parent category exact match
                    WHEN EXISTS (
                        SELECT 1 FROM product_categories pc
                        JOIN product_categories parent ON pc.parent_id = parent.id
                        WHERE pc.id = products.product_category_id
                        AND LOWER(parent.name) = LOWER(?)
                    ) THEN 450

                    -- Vendor exact match
                    WHEN EXISTS (
                        SELECT 1 FROM vendors v
                        WHERE v.id = products.vendor_id
                        AND LOWER(v.name) = LOWER(?)
                    ) THEN 400

                    -- Tag exact match
                    WHEN EXISTS (
                        SELECT 1 FROM product_tag pt
                        JOIN product_tags ptags ON pt.product_tag_id = ptags.id
                        WHERE pt.product_id = products.id
                        AND LOWER(ptags.name) = LOWER(?)
                    ) THEN 350

                    -- Category partial match
                    WHEN EXISTS (
                        SELECT 1 FROM product_categories pc
                        WHERE pc.id = products.product_category_id
                        AND LOWER(pc.name) LIKE LOWER(?)
                    ) THEN 300

                    -- Parent category partial match
                    WHEN EXISTS (
                        SELECT 1 FROM product_categories pc
                        JOIN product_categories parent ON pc.parent_id = parent.id
                        WHERE pc.id = products.product_category_id
                        AND LOWER(parent.name) LIKE LOWER(?)
                    ) THEN 280

                    -- Vendor partial match
                    WHEN EXISTS (
                        SELECT 1 FROM vendors v
                        WHERE v.id = products.vendor_id
                        AND LOWER(v.name) LIKE LOWER(?)
                    ) THEN 250

                    -- Variant value match
                    WHEN EXISTS (
                        SELECT 1 FROM product_variants pv
                        WHERE pv.product_id = products.id
                        AND LOWER(pv.value) LIKE LOWER(?)
                    ) THEN 200

                    -- Variant attribute name match
                    WHEN EXISTS (
                        SELECT 1 FROM product_variants pv
                        JOIN product_attributes pa ON pv.product_attribute_id = pa.id
                        WHERE pv.product_id = products.id
                        AND LOWER(pa.name) LIKE LOWER(?)
                    ) THEN 180

                    -- Tag partial match
                    WHEN EXISTS (
                        SELECT 1 FROM product_tag pt
                        JOIN product_tags ptags ON pt.product_tag_id = ptags.id
                        WHERE pt.product_id = products.id
                        AND LOWER(ptags.name) LIKE LOWER(?)
                    ) THEN 150

                    -- Product description contains search
                    WHEN LOWER(products.description) LIKE LOWER(?) THEN 100

                    -- Vendor description contains search
                    WHEN EXISTS (
                        SELECT 1 FROM vendors v
                        WHERE v.id = products.vendor_id
                        AND LOWER(v.description) LIKE LOWER(?)
                    ) THEN 50

                    -- Fulltext relevance as fallback
                    ELSE MATCH(products.name, products.description) AGAINST(? IN BOOLEAN MODE) * 10
                END as search_score
            ', [
                    $fulltextQuery,                     // MATCH AGAINST
                    $cleanedQuery,                      // Exact product name
                    $cleanedQuery . '%',               // Product name starts with
                    '%' . $cleanedQuery . '%',         // Product name contains
                    $cleanedQuery,                      // Category exact
                    $cleanedQuery,                      // Parent category exact
                    $cleanedQuery,                      // Vendor exact
                    $cleanedQuery,                      // Tag exact
                    '%' . $cleanedQuery . '%',         // Category partial
                    '%' . $cleanedQuery . '%',         // Parent category partial
                    '%' . $cleanedQuery . '%',         // Vendor partial
                    '%' . $cleanedQuery . '%',         // Variant value
                    '%' . $cleanedQuery . '%',         // Variant attribute
                    '%' . $cleanedQuery . '%',         // Tag partial
                    '%' . $cleanedQuery . '%',         // Product description
                    '%' . $cleanedQuery . '%',         // Vendor description
                    $fulltextQuery                      // MATCH AGAINST for scoring
                ])
                ->where(function($query) use ($fulltextQuery, $searchTerms, $cleanedQuery) {
                    // Main fulltext search
                    $query->whereRaw('MATCH(products.name, products.description) AGAINST(? IN BOOLEAN MODE)', [$fulltextQuery])

                        // OR search in product name/description with individual terms
                        ->orWhere(function($q) use ($searchTerms) {
                            foreach ($searchTerms as $term) {
                                if (strlen($term) >= 1) {
                                    $q->where('products.name', 'LIKE', '%' . $term . '%')
                                        ->orWhere('products.description', 'LIKE', '%' . $term . '%');
                                }
                            }
                        })

                        // OR search in categories
                        ->orWhereHas('category', function($q) use ($cleanedQuery, $searchTerms) {
                            $q->where('name', 'LIKE', '%' . $cleanedQuery . '%');
                            foreach ($searchTerms as $term) {
                                if (strlen($term) >= 2) {
                                    $q->orWhere('name', 'LIKE', '%' . $term . '%');
                                }
                            }
                        })

                        // OR search in parent categories
                        ->orWhereHas('category.parent', function($q) use ($cleanedQuery, $searchTerms) {
                            $q->where('name', 'LIKE', '%' . $cleanedQuery . '%');
                            foreach ($searchTerms as $term) {
                                if (strlen($term) >= 2) {
                                    $q->orWhere('name', 'LIKE', '%' . $term . '%');
                                }
                            }
                        })

                        // OR search in vendors
                        ->orWhereHas('vendor', function($q) use ($cleanedQuery, $searchTerms) {
                            $q->where('name', 'LIKE', '%' . $cleanedQuery . '%')
                                ->orWhere('description', 'LIKE', '%' . $cleanedQuery . '%');
                            foreach ($searchTerms as $term) {
                                if (strlen($term) >= 2) {
                                    $q->orWhere('name', 'LIKE', '%' . $term . '%')
                                        ->orWhere('description', 'LIKE', '%' . $term . '%');
                                }
                            }
                        })

                        // OR search in tags
                        ->orWhereHas('tags', function($q) use ($cleanedQuery, $searchTerms) {
                            $q->where('name', 'LIKE', '%' . $cleanedQuery . '%');
                            foreach ($searchTerms as $term) {
                                if (strlen($term) >= 2) {
                                    $q->orWhere('name', 'LIKE', '%' . $term . '%');
                                }
                            }
                        })

                        // OR search in product variants
                        ->orWhereHas('variants', function($q) use ($cleanedQuery, $searchTerms) {
                            $q->where('value', 'LIKE', '%' . $cleanedQuery . '%');
                            foreach ($searchTerms as $term) {
                                if (strlen($term) >= 1) {
                                    $q->orWhere('value', 'LIKE', '%' . $term . '%');
                                }
                            }
                        })

                        ->orWhereHas('variants.productAttribute', function($q) use ($cleanedQuery, $searchTerms) {
                            $q->where('name', 'LIKE', '%' . $cleanedQuery . '%');
                            foreach ($searchTerms as $term) {
                                if (strlen($term) >= 2) {
                                    $q->orWhere('name', 'LIKE', '%' . $term . '%');
                                }
                            }
                        });
                });

        } catch (\Exception $e) {
            Log::error('Enhanced fulltext search failed', [
                'error' => $e->getMessage(),
                'query' => $cleanedQuery
            ]);
            return $this->applyComprehensiveSearch($searchTerms, $cleanedQuery);
        }
    }

    protected function applyComprehensiveSearch(array $searchTerms, string $cleanedQuery): Builder
    {
        return $this->builder
            ->selectRaw('
            products.*,
            0 as relevance_score,
            CASE
                WHEN LOWER(products.name) = LOWER(?) THEN 1000
                WHEN LOWER(products.name) LIKE LOWER(?) THEN 800
                WHEN LOWER(products.name) LIKE LOWER(?) THEN 600
                WHEN EXISTS (
                    SELECT 1 FROM product_categories pc
                    WHERE pc.id = products.product_category_id
                    AND LOWER(pc.name) = LOWER(?)
                ) THEN 500
                WHEN EXISTS (
                    SELECT 1 FROM vendors v
                    WHERE v.id = products.vendor_id
                    AND LOWER(v.name) = LOWER(?)
                ) THEN 400
                WHEN EXISTS (
                    SELECT 1 FROM product_categories pc
                    WHERE pc.id = products.product_category_id
                    AND LOWER(pc.name) LIKE LOWER(?)
                ) THEN 300
                WHEN EXISTS (
                    SELECT 1 FROM vendors v
                    WHERE v.id = products.vendor_id
                    AND LOWER(v.name) LIKE LOWER(?)
                ) THEN 250
                WHEN LOWER(products.description) LIKE LOWER(?) THEN 100
                ELSE 10
            END as search_score
        ', [
                $cleanedQuery,                      // Exact product name
                $cleanedQuery . '%',               // Product name starts with
                '%' . $cleanedQuery . '%',         // Product name contains
                $cleanedQuery,                      // Category exact
                $cleanedQuery,                      // Vendor exact
                '%' . $cleanedQuery . '%',         // Category partial
                '%' . $cleanedQuery . '%',         // Vendor partial
                '%' . $cleanedQuery . '%',         // Product description
            ])
            ->where(function($query) use ($searchTerms, $cleanedQuery) {
                $query->where(function($q) use ($searchTerms) {
                    foreach ($searchTerms as $term) {
                        if (strlen($term) >= 1) {
                            $q->where('products.name', 'LIKE', '%' . $term . '%')
                                ->orWhere('products.description', 'LIKE', '%' . $term . '%');
                        }
                    }
                })
                    ->orWhereHas('category', function($q) use ($cleanedQuery, $searchTerms) {
                        $q->where('name', 'LIKE', '%' . $cleanedQuery . '%');
                        foreach ($searchTerms as $term) {
                            if (strlen($term) >= 2) {
                                $q->orWhere('name', 'LIKE', '%' . $term . '%');
                            }
                        }
                    })
                    ->orWhereHas('category.parent', function($q) use ($cleanedQuery, $searchTerms) {
                        $q->where('name', 'LIKE', '%' . $cleanedQuery . '%');
                        foreach ($searchTerms as $term) {
                            if (strlen($term) >= 2) {
                                $q->orWhere('name', 'LIKE', '%' . $term . '%');
                            }
                        }
                    })
                    ->orWhereHas('vendor', function($q) use ($cleanedQuery, $searchTerms) {
                        $q->where('name', 'LIKE', '%' . $cleanedQuery . '%')
                            ->orWhere('description', 'LIKE', '%' . $cleanedQuery . '%');
                        foreach ($searchTerms as $term) {
                            if (strlen($term) >= 2) {
                                $q->orWhere('name', 'LIKE', '%' . $term . '%')
                                    ->orWhere('description', 'LIKE', '%' . $term . '%');
                            }
                        }
                    })
                    ->orWhereHas('tags', function($q) use ($cleanedQuery, $searchTerms) {
                        $q->where('name', 'LIKE', '%' . $cleanedQuery . '%');
                        foreach ($searchTerms as $term) {
                            if (strlen($term) >= 2) {
                                $q->orWhere('name', 'LIKE', '%' . $term . '%');
                            }
                        }
                    })
                    ->orWhereHas('variants', function($q) use ($cleanedQuery, $searchTerms) {
                        $q->where('value', 'LIKE', '%' . $cleanedQuery . '%');
                        foreach ($searchTerms as $term) {
                            if (strlen($term) >= 1) {
                                $q->orWhere('value', 'LIKE', '%' . $term . '%');
                            }
                        }
                    })
                    ->orWhereHas('variants.productAttribute', function($q) use ($cleanedQuery, $searchTerms) {
                        $q->where('name', 'LIKE', '%' . $cleanedQuery . '%');
                        foreach ($searchTerms as $term) {
                            if (strlen($term) >= 2) {
                                $q->orWhere('name', 'LIKE', '%' . $term . '%');
                            }
                        }
                    });
            });
    }

    protected function buildSafeFulltextQuery($parsedQuery): string
    {
        $fulltextParts = [];

        foreach ($parsedQuery->phrases as $phrase) {
            if (!empty(trim($phrase))) {
                $fulltextParts[] = '"' . addslashes(trim($phrase)) . '"';
            }
        }

        $processedTerms = [];
        foreach ($parsedQuery->terms as $term) {
            $cleanTerm = $this->cleanTermForFulltext($term);
            if ($cleanTerm && !in_array($cleanTerm, $processedTerms) && strlen($cleanTerm) >= 2) {
                $processedTerms[] = $cleanTerm;
                $fulltextParts[] = '+' . $cleanTerm . '*';
            }
        }

        return implode(' ', $fulltextParts);
    }

    protected function cleanTermForFulltext(string $term): string
    {
        $term = preg_replace('/[^\w\s]/', '', $term);
        $term = trim($term);

        $term = str_replace(['+', '-', '~', '<', '>', '(', ')', '"', '*'], '', $term);

        return $term;
    }

    protected function fallbackToLikeSearch($parsedQuery): Builder
    {
        return $this->builder->where(function (Builder $query) use ($parsedQuery) {
            foreach ($parsedQuery->terms as $term) {
                if (strlen($term) >= 2) {
                    $cleanTerm = trim($term);
                    $query->orWhere('products.name', 'LIKE', '%' . $cleanTerm . '%')
                        ->orWhere('products.description', 'LIKE', '%' . $cleanTerm . '%');
                }
            }

            foreach ($parsedQuery->phrases as $phrase) {
                if (!empty(trim($phrase))) {
                    $cleanPhrase = trim($phrase);
                    $query->orWhere('products.name', 'LIKE', '%' . $cleanPhrase . '%')
                        ->orWhere('products.description', 'LIKE', '%' . $cleanPhrase . '%');
                }
            }
        });
    }

    protected function fallbackToSimpleSearch(string $value): Builder
    {
        $cleanValue = trim($value);

        return $this->builder->where(function($query) use ($cleanValue) {
            $query->where('products.name', 'LIKE', '%' . $cleanValue . '%')
                ->orWhere('products.description', 'LIKE', '%' . $cleanValue . '%');
        });
    }

    protected function applyQueryFilters(array $filters): Builder
    {
        if (isset($filters['price_min']) || isset($filters['price_max'])) {
            $this->builder = $this->builder->where(function($query) use ($filters) {
                if (isset($filters['price_min'])) {
                    $query->where('products.price', '>=', $filters['price_min']);
                }
                if (isset($filters['price_max'])) {
                    $query->where('products.price', '<=', $filters['price_max']);
                }
            });
        }

        if (isset($filters['colors'])) {
            $this->builder = $this->applyAttributeFilter('color', $filters['colors']);
        }

        if (isset($filters['sizes'])) {
            $this->builder = $this->applyAttributeFilter('size', $filters['sizes']);
        }

        if (isset($filters['brands'])) {
            $this->builder = $this->builder->whereHas('vendor', function($query) use ($filters) {
                $query->whereIn(DB::raw('LOWER(vendors.name)'), array_map('strtolower', $filters['brands']));
            });
        }

        return $this->builder;
    }

    protected function applyAttributeFilter(string $attributeName, array $values): Builder
    {
        return $this->builder->whereHas('variants', function($query) use ($attributeName, $values) {
            $query->whereHas('productAttribute', function($attrQuery) use ($attributeName) {
                $attrQuery->where(DB::raw('LOWER(product_attributes.name)'), strtolower($attributeName));
            })->whereIn(DB::raw('LOWER(product_variants.value)'), array_map('strtolower', $values));
        });
    }

    public function availability(string $value): Builder
    {
        switch (strtolower($value)) {
            case 'in_stock':
                return $this->builder->where('products.quantity', '>', 0)
                    ->whereHas('productStatus', function($query) {
                        $query->where('name', ProductStatuses::ACTIVE);
                    });

            case 'out_of_stock':
                return $this->builder->where('products.quantity', '<=', 0);

            case 'low_stock':
                return $this->builder->whereRaw('products.quantity > 0 AND products.quantity <= products.low_stock_threshold');

            case 'available':
                return $this->builder->where('products.quantity', '>', 0)
                    ->whereHas('productStatus', function($query) {
                        $query->whereIn('name', [ProductStatuses::ACTIVE]);
                    });

            default:
                return $this->builder;
        }
    }

    public function vendors(string $value): Builder
    {
        $vendorIds = explode(',', $value);

        foreach ($vendorIds as $id) {
            if (!is_numeric($id)) {
                return $this->builder;
            }
        }

        return $this->builder->whereIn('products.vendor_id', $vendorIds);
    }

    public function tags(string $value, string $logic = 'OR'): Builder
    {
        $tagIds = explode(',', $value);

        foreach ($tagIds as $id) {
            if (!is_numeric($id)) {
                return $this->builder;
            }
        }

        if (strtoupper($logic) === 'AND') {
            foreach ($tagIds as $tagId) {
                $this->builder = $this->builder->whereHas('tags', function($query) use ($tagId) {
                    $query->where('product_tags.id', $tagId);
                });
            }
            return $this->builder;
        } else {
            return $this->builder->whereHas('tags', function($query) use ($tagIds) {
                $query->whereIn('product_tags.id', $tagIds);
            });
        }
    }

    public function priceRanges(string $value): Builder
    {
        $ranges = explode(',', $value);

        return $this->builder->where(function($query) use ($ranges) {
            foreach ($ranges as $range) {
                if (strpos($range, '-') !== false) {
                    [$min, $max] = explode('-', $range, 2);
                    if (is_numeric($min) && is_numeric($max)) {
                        $query->orWhereBetween('products.price', [
                            floatval($min) * 100,
                            floatval($max) * 100
                        ]);
                    }
                } elseif (is_numeric($range)) {
                    $query->orWhere('products.price', floatval($range) * 100);
                }
            }
        });
    }

    public function price(string $value): Builder
    {
        $range = explode(',', $value);

        foreach ($range as $price) {
            if (!is_numeric($price)) {
                return $this->builder;
            }
        }

        if (count($range) === 2) {
            $minPrice = floatval($range[0]) * 100;
            $maxPrice = floatval($range[1]) * 100;

            if ($minPrice === $maxPrice) {
                return $this->builder->where('products.price', '=', $minPrice);
            }

            return $this->builder->whereBetween('products.price', [$minPrice, $maxPrice]);
        }

        return $this->builder->where('products.price', '<=', floatval($value) * 100);
    }

    public function category(string $value): Builder
    {
        $categoryIds = explode(',', $value);

        foreach ($categoryIds as $id) {
            if (!is_numeric($id)) {
                return $this->builder;
            }
        }

        return $this->builder->where(function($query) use ($categoryIds) {
            $query->whereIn('products.product_category_id', $categoryIds)
                ->orWhereHas('category', function($categoryQuery) use ($categoryIds) {
                    $categoryQuery->whereIn('product_categories.parent_id', $categoryIds);
                });
        });
    }

    public function attributes(string $value): Builder
    {
        $attributeFilters = explode(',', $value);

        foreach ($attributeFilters as $filter) {
            if (strpos($filter, ':') === false) continue;

            [$attributeName, $attributeValue] = explode(':', $filter, 2);

            $this->builder = $this->builder->whereHas('variants', function($query) use ($attributeName, $attributeValue) {
                $query->whereHas('productAttribute', function($attrQuery) use ($attributeName) {
                    $attrQuery->where(DB::raw('LOWER(product_attributes.name)'), strtolower(trim($attributeName)));
                })->where(DB::raw('LOWER(product_variants.value)'), strtolower(trim($attributeValue)));
            });
        }

        return $this->builder;
    }

    public function name(string $value): Builder
    {
        $this->safeLikeQuery('name', $value);
        return $this->builder;
    }

    protected function sort(string $value): void
    {
        $sortAttributes = explode(',', $value);

        foreach ($sortAttributes as $sortAttribute) {
            $direction = 'asc';

            if (str_starts_with($sortAttribute, '-')) {
                $direction = 'desc';
                $sortAttribute = substr($sortAttribute, 1);
            }

            if (in_array($sortAttribute, array_keys($this->sortable), true)) {
                $columnName = $this->sortable[$sortAttribute];

                if ($sortAttribute === 'relevance') {
                    // Sort by search relevance score
                    $this->builder->orderByRaw("COALESCE(search_score, relevance_score, 0) {$direction}");
                } else {
                    $this->builder->orderBy($columnName, $direction);
                }
            }
        }
    }

    public function status(string $value): Builder
    {
        return $this->builder->whereHas('productStatus', function($query) use ($value) {
            $query->where('name', $value);
        });
    }

    public function vendor(string $value): Builder
    {
        return $this->builder->whereHas('vendor', function($query) use ($value) {
            $this->builder = $query;
            $this->safeLikeQuery('name', $value);

        });
    }

    public function createdAt(string $value): Builder
    {
        $dates = explode(',', $value);

        foreach ($dates as $date) {
            if (!$this->isValidDate($date)) {
                return $this->builder;
            }
        }

        if (count($dates) > 1) {
            return $this->builder->whereBetween('products.created_at', [
                $dates[0] . ' 00:00:00',
                $dates[1] . ' 23:59:59'
            ]);
        }

        return $this->builder->whereDate('products.created_at', $value);
    }

    public function updatedAt(string $value): Builder
    {
        $dates = explode(',', $value);

        foreach ($dates as $date) {
            if (!$this->isValidDate($date)) {
                return $this->builder;
            }
        }

        if (count($dates) > 1) {
            return $this->builder->whereBetween('products.updated_at', [
                $dates[0] . ' 00:00:00',
                $dates[1] . ' 23:59:59'
            ]);
        }

        return $this->builder->whereDate('products.updated_at', $value);
    }

    public function quantity(string $value): Builder
    {
        if (strpos($value, ',') !== false) {
            $range = explode(',', $value);
            if (count($range) === 2 && is_numeric($range[0]) && is_numeric($range[1])) {
                return $this->builder->whereBetween('products.quantity', [
                    intval($range[0]),
                    intval($range[1])
                ]);
            }
        } elseif (is_numeric($value)) {
            return $this->builder->where('products.quantity', '>=', intval($value));
        }

        return $this->builder;
    }

    public function include(string|array $value): Builder
    {
        $relationships = is_array($value) ? $value : explode(',', $value);

        $optimizedRelationships = [];

        foreach ($relationships as $relationship) {
            switch ($relationship) {
                case 'vendor':
                    $optimizedRelationships[] = 'vendor:id,name,description';
                    break;
                case 'category':
                    $optimizedRelationships[] = 'category:id,name,parent_id';
                    break;
                case 'tags':
                    $optimizedRelationships[] = 'tags:id,name';
                    break;
                case 'variants':
                    $optimizedRelationships[] = 'variants.productAttribute:id,name';
                    $optimizedRelationships[] = 'variants:id,product_id,product_attribute_id,value,additional_price,quantity';
                    break;
                case 'productStatus':
                    $optimizedRelationships[] = 'productStatus:id,name';
                    break;
                case 'media':
                    $optimizedRelationships[] = 'media';
                    break;
                default:
                    $optimizedRelationships[] = $relationship;
            }
        }

        return $this->builder->with($optimizedRelationships);
    }

    protected function isAllowedFilter(string $method): bool
    {
        $allowedMethods = [
            'name',
            'email',
            'search',
            'createdAt',
            'updatedAt',
            'price',
            'priceRanges',
            'quantity',
            'category',
            'availability',
            'vendors',
            'vendor',
            'tags',
            'attributes',
            'status',
            'include',
            'user',
            'sort',
            'filter'
        ];

        return in_array($method, $allowedMethods);
    }

    protected function safeLikeQuery(string $column, string $value): void
    {
        $escapedValue = str_replace(['%', '_', '\\'], ['\\%', '\\_', '\\\\'], $value);
        $likeValue = str_replace('*', '%', $escapedValue);

        if (strpos($likeValue, '%') === false) {
            $likeValue = '%' . $likeValue . '%';
        }

        $this->builder->where($column, 'like', $likeValue);
    }

    private function isValidDate(string $date): bool
    {
        $format = 'Y-m-d';
        $dateTime = \DateTime::createFromFormat($format, $date);
        return $dateTime && $dateTime->format($format) === $date;
    }
}
