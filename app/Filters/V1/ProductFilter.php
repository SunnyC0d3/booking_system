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

    public function createdAt($value)
    {
        $dates = explode(',', $value);

        if (count($dates) > 1) {
            return $this->builder->whereBetween('created_at', $dates);
        }

        return $this->builder->whereDate('created_at', $value);
    }

    public function updatedAt($value)
    {
        $dates = explode(',', $value);

        if (count($dates) > 1) {
            return $this->builder->whereBetween('updated_at', $dates);
        }

        return $this->builder->whereDate('updated_at', $value);
    }

    public function price($value)
    {
        $range = explode(',', $value);

        if (count($range) === 2) {
            return $this->builder->whereBetween('price', $range);
        }

        return $this->builder->where('price', $value);
    }

    public function quantity($value)
    {
        return $this->builder->where('quantity', $value);
    }

    public function category($value)
    {
        return $this->builder->whereHas('categories', function (Builder $query) use ($value) {
            $query->whereIn('id', explode(',', $value));
        });
    }

    public function include($value)
    {
        return $this->builder->with($value);
    }

    public function search($value)
    {
        $likeStr = str_replace('*', '%', $value);
        return $this->builder->where('name', 'like', $likeStr)
            ->orWhere('description', 'like', $likeStr);
    }
}
