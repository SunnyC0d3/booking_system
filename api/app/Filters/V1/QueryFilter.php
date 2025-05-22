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
            if (method_exists($this, $key)) {
                $this->$key($value);
            }
        }

        return $builder;
    }

    protected function filter(array $filters)
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
