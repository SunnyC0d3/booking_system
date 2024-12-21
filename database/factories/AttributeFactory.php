<?php

namespace Database\Factories;

use App\Models\Attribute;
use Illuminate\Database\Eloquent\Factories\Factory;

class AttributeFactory extends Factory
{
    protected $model = Attribute::class;

    public function definition(): array
    {
        return [
            'key' => fake()->randomElement(['Color', 'Size', 'Material']),
            'value' => fake()->word(),
            'attributable_id' => null,
            'attributable_type' => null,
        ];
    }
}