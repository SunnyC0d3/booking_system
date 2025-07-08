<?php

namespace App\Filters\V1;

use App\Services\V1\Search\QueryProcessor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

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

        $parsedQuery = $this->queryProcessor->parseQuery($value);

        if ($parsedQuery->hasTerms()) {
            $this->builder = $this->applyFullTextSearch($parsedQuery);
        }

        if ($parsedQuery->hasFilters()) {
            $this->builder = $this->applyQueryFilters($parsedQuery->filters);
        }

        return $this->builder;
    }

    protected function applyFullTextSearch($parsedQuery): Builder
    {
        $fulltextQuery = $parsedQuery->fulltext_query;

        if (empty($fulltextQuery)) {
            return $this->fallbackToLikeSearch($parsedQuery);
        }

        return $this->builder
            ->selectRaw('
                products.*,
                MATCH(products.name, products.description) AGAINST(? IN BOOLEAN MODE) as relevance_score,
                CASE
                    WHEN products.name LIKE ? THEN 100
                    WHEN products.name LIKE ? THEN 75
                    WHEN products.description LIKE ? THEN 50
                    ELSE MATCH(products.name, products.description) AGAINST(? IN BOOLEAN MODE) * 10
                END as search_score
            ', [
                $fulltextQuery,
                '%' . $parsedQuery->cleaned . '%',
                '%' . implode('%', $parsedQuery->terms) . '%',
                '%' . $parsedQuery->cleaned . '%',
                $fulltextQuery
            ])
            ->whereRaw('MATCH(products.name, products.description) AGAINST(? IN BOOLEAN MODE)', [$fulltextQuery])
            ->orWhere(function($query) use ($parsedQuery) {
                foreach ($parsedQuery->terms as $term) {
                    if (strlen($term) >= 2) {
                        $query->orWhere('products.name', 'LIKE', '%' . $term . '%')
                            ->orWhere('products.description', 'LIKE', '%' . $term . '%');
                    }
                }
            });
    }

    protected function fallbackToLikeSearch($parsedQuery): Builder
    {
        return $this->builder->where(function (Builder $query) use ($parsedQuery) {
            foreach ($parsedQuery->terms as $term) {
                if (strlen($term) >= 2) {
                    $query->orWhere('products.name', 'LIKE', '%' . $term . '%')
                        ->orWhere('products.description', 'LIKE', '%' . $term . '%');
                }
            }

            foreach ($parsedQuery->phrases as $phrase) {
                $query->orWhere('products.name', 'LIKE', '%' . $phrase . '%')
                    ->orWhere('products.description', 'LIKE', '%' . $phrase . '%');
            }
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
                        $query->where('name', 'Active');
                    });

            case 'out_of_stock':
                return $this->builder->where('products.quantity', '<=', 0);

            case 'low_stock':
                return $this->builder->whereRaw('products.quantity > 0 AND products.quantity <= products.low_stock_threshold');

            case 'available':
                return $this->builder->where('products.quantity', '>', 0)
                    ->whereHas('productStatus', function($query) {
                        $query->whereIn('name', ['Active']);
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
            return $this->builder->whereBetween('products.price', [
                floatval($range[0]) * 100,
                floatval($range[1]) * 100
            ]);
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
            'role',
            'user',
            'sort'
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
