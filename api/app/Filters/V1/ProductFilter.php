<?php

namespace App\Filters\V1;

use Illuminate\Database\Eloquent\Builder;

class ProductFilter extends QueryFilter
{
    protected array $sortable = [
        'name' => 'name',
        'price' => 'price',
        'quantity' => 'quantity',
        'created_at' => 'created_at',
        'updated_at' => 'updated_at',
    ];

    public function name(string $value)
    {
        $this->safeLikeQuery('name', $value);
        return $this->builder;
    }

    public function search(string $value)
    {
        return $this->builder->where(function (Builder $query) use ($value) {
            $this->builder = $query;
            $this->safeLikeQuery('name', $value);
            $query->orWhere(function (Builder $subQuery) use ($value) {
                $this->builder = $subQuery;
                $this->safeLikeQuery('description', $value);
            });
        });
    }

    public function createdAt(string $value)
    {
        $dates = explode(',', $value);

        foreach ($dates as $date) {
            if (!$this->isValidDate($date)) {
                return $this->builder;
            }
        }

        if (count($dates) > 1) {
            return $this->builder->whereBetween('created_at', $dates);
        }

        return $this->builder->whereDate('created_at', $value);
    }

    public function price(string $value)
    {
        $range = explode(',', $value);

        foreach ($range as $price) {
            if (!is_numeric($price)) {
                return $this->builder;
            }
        }

        if (count($range) === 2) {
            return $this->builder->whereBetween('price', $range);
        }

        return $this->builder->where('price', $value);
    }

    public function category(string $value)
    {
        $categoryIds = explode(',', $value);

        foreach ($categoryIds as $id) {
            if (!is_numeric($id)) {
                return $this->builder;
            }
        }

        return $this->builder->whereHas('category', function (Builder $query) use ($categoryIds) {
            $query->whereIn('id', $categoryIds);
        });
    }

    private function isValidDate(string $date): bool
    {
        $format = 'Y-m-d';
        $dateTime = \DateTime::createFromFormat($format, $date);
        return $dateTime && $dateTime->format($format) === $date;
    }
}
