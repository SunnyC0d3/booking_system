<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'slug' => fake()->slug(),
            'description' => fake()->paragraph(),
            'price' => fake()->randomFloat(2, 10, 1000),
            'discount_price' => fake()->randomFloat(2, 5, 500),
            'quantity' => fake()->numberBetween(0, 100),
            'sku' => fake()->unique()->lexify('SKU????'),
            'is_active' => fake()->boolean(80),
            'is_featured' => fake()->boolean(20),
        ];
    }
}