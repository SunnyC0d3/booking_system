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

    public function apply(Builder $builder): Builder
    {
        $this->builder = $builder;

        $filters = $this->request->query('filter', []);

        if ($filters) {
            $this->filter($filters);
        }

        $sortValue = $this->request->query('sort');

        if ($sortValue) {
            $this->sort($sortValue);
        }

        return $this->builder;
    }

    protected function filter(array $filters): void
    {
        foreach ($filters as $key => $value) {
            if (method_exists($this, $key)) {
                $this->$key($value);
            }
        }
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

            $columnName = $this->sortable[$sortAttribute] ?? $sortAttribute;

            if (in_array($columnName, $this->sortable, true) || array_key_exists($sortAttribute, $this->sortable)) {
                $this->builder->orderBy($columnName, $direction);
            }
        }
    }
}
