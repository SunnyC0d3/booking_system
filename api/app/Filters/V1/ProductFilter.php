<?php

namespace App\Filters\V1;

use Illuminate\Database\Eloquent\Builder;

class ProductFilter extends QueryFilter
{
    protected array $sortable = [
        'name',
        'price',
        'quantity',
        'created_at',
        'updated_at',
    ];

    public function name(string $value)
    {
        $likeStr = str_replace('*', '%', $value);
        return $this->builder->where('name', 'like', $likeStr);
    }

    public function createdAt(string $value)
    {
        $dates = explode(',', $value);

        if (count($dates) > 1) {
            return $this->builder->whereBetween('created_at', $dates);
        }

        return $this->builder->whereDate('created_at', $value);
    }

    public function updatedAt(string $value)
    {
        $dates = explode(',', $value);

        if (count($dates) > 1) {
            return $this->builder->whereBetween('updated_at', $dates);
        }

        return $this->builder->whereDate('updated_at', $value);
    }

    public function price(string $value)
    {
        $range = explode(',', $value);

        if (count($range) === 2) {
            return $this->builder->whereBetween('price', $range);
        }

        return $this->builder->where('price', $value);
    }

    public function quantity(int $value)
    {
        return $this->builder->where('quantity', $value);
    }

    public function category(string $value)
    {
        return $this->builder->whereHas('category', function (Builder $query) use ($value) {
            $query->whereIn('id', explode(',', $value));
        });
    }

    public function include(string|array $value)
    {
        return $this->builder->with($value);
    }

    public function search(string $value)
    {
        $likeStr = str_replace('*', '%', $value);
        return $this->builder->where('name', 'like', $likeStr)
            ->orWhere('description', 'like', $likeStr);
    }
}
