<?php

namespace Database\Factories;

use App\Models\ProductImage;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductImageFactory extends Factory
{
    protected $model = ProductImage::class;

    public function definition(): array
    {
        return [
            'path' => fake()->imageUrl(),
            'imageable_id' => null,
            'imageable_type' => null,
        ];
    }
}