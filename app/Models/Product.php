<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
}
