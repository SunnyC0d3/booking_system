<?php

namespace App\Filters\V1;

use Illuminate\Database\Eloquent\Builder;

class VendorFilter extends QueryFilter
{
    protected array $sortable = [
        'name' => 'name',
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
        });
    }

    public function user(string|array $value)
    {
        $ids = is_array($value) ? $value : explode(',', $value);

        foreach ($ids as $id) {
            if (!is_numeric($id)) {
                return $this->builder;
            }
        }

        return $this->builder->whereIn('user_id', $ids);
    }

    public function include(string|array $value)
    {
        return $this->builder->with($value);
    }

}
