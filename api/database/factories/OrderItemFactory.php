<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Product;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition(): array
    {
        return [
            'quantity'   => fake()->numberBetween(1, 10),
            'price'      => fake()->numberBetween(500, 10000),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),

            'order_id'           => Order::factory(),
            'product_id'         => Product::factory(),
            'product_variant_id' => ProductVariant::factory(),
        ];
    }
}
