<?php

namespace Database\Factories;

use App\Constants\ProductStatuses;
use App\Models\ProductStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductStatusFactory extends Factory
{
    protected $model = ProductStatus::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->randomElement([
                ProductStatuses::ACTIVE,
                ProductStatuses::INACTIVE,
                ProductStatuses::OUT_OF_STOCK,
                ProductStatuses::DISCONTINUED,
                ProductStatuses::COMING_SOON
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
