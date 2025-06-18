<?php

namespace Database\Factories;

use App\Models\OrderStatus;
use App\Models\User;
use App\Models\Order;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'status_id'    => OrderStatus::factory(),
            'total_amount' => fake()->numberBetween(1000, 50000),
            'created_at'   => Carbon::now(),
            'updated_at'   => Carbon::now(),

            'user_id' => User::factory(),
        ];
    }
}
