<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Filters\V1\QueryFilter;
use Illuminate\Database\Eloquent\Builder;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'discount_price',
        'quantity',
        'sku',
        'is_active',
        'is_featured'
    ];

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

    public function scopeFilter(Builder $builder, QueryFilter $filters)
    {
        return $filters->apply($builder);
    }
}
