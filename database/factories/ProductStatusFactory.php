<?php

namespace Database\Factories;

use App\Models\ProductStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductStatusFactory extends Factory
{
    protected $model = ProductStatus::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->randomElement([
                'Active', 'Inactive', 'Out Of Stock', 'Discontinued', 'Coming Soon'
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
