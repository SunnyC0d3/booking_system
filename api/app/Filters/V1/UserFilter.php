<?php

namespace App\Filters\V1;

use Illuminate\Database\Eloquent\Builder;

class UserFilter extends QueryFilter
{
    protected array $sortable = [
        'name' => 'name',
        'email' => 'email',
        'created_at' => 'created_at',
        'updated_at' => 'updated_at',
    ];

    public function name(string $value)
    {
        $this->safeLikeQuery('name', $value);
        return $this->builder;
    }

    public function email(string $value)
    {
        $this->safeLikeQuery('email', $value);
        return $this->builder;
    }

    public function search(string $value)
    {
        return $this->builder->where(function (Builder $query) use ($value) {
            $this->builder = $query;
            $this->safeLikeQuery('name', $value);
            $query->orWhere(function (Builder $subQuery) use ($value) {
                $this->builder = $subQuery;
                $this->safeLikeQuery('email', $value);
            });
        });
    }

    public function role(string|array $value)
    {
        $ids = is_array($value) ? $value : explode(',', $value);

        // 🛡️ Validate all IDs are numeric
        foreach ($ids as $id) {
            if (!is_numeric($id)) {
                return $this->builder;
            }
        }

        return $this->builder->whereIn('role_id', $ids);
    }

    public function include(string|array $value)
    {
        return $this->builder->with($value);
    }
}
