<?php

namespace App\Filters\V1;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

abstract class QueryFilter
{
    protected Builder $builder;
    protected Request $request;
    protected array $sortable = [];

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function apply(Builder $builder)
    {
        $this->builder = $builder;

        foreach ($this->request->all() as $key => $value) {
            if (method_exists($this, $key) && $this->isAllowedFilter($key)) {
                $this->$key($value);
            }
        }

        return $builder;
    }

    protected function isAllowedFilter(string $method): bool
    {
        $allowedMethods = [
            'name',
            'email',
            'createdAt',
            'updatedAt',
            'price',
            'quantity',
            'category',
            'include',
            'search',
            'role',
            'user',
            'sort'
        ];

        return in_array($method, $allowedMethods);
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

            if (in_array($sortAttribute, $this->sortable, true)) {
                $columnName = $this->sortable[$sortAttribute] ?? $sortAttribute;
                $this->builder->orderBy(DB::raw($columnName), $direction);
            }
        }
    }

    protected function safeLikeQuery(string $column, string $value): void
    {
        $escapedValue = str_replace(['%', '_'], ['\%', '\_'], $value);
        $likeValue = str_replace('*', '%', $escapedValue);

        $this->builder->where($column, 'like', $likeValue);
    }
}
