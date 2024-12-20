<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Http\Filters\V1\QueryFilter;
use Illuminate\Database\Eloquent\Builder;

class Product extends Model
{
    public function categories()
    {
        return $this->morphToMany(Category::class, 'categorizable');
    }

    public function images()
    {
        return $this->morphMany(ProductImage::class, 'imageable');
    }

    public function attributes()
    {
        return $this->morphMany(Attribute::class, 'attributable');
    }

    public function scopeFilter(Builder $builder, QueryFilter $filters) {
        return $filters->apply($builder);
    }
}
