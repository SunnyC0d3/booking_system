<?php

namespace Database\Factories;

use App\Models\Vendor;
use App\Models\ProductStatus;
use App\Models\ProductCategory;
use App\Models\Product;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'name'                  => fake()->name(),
            'description'           => fake()->text(),
            'price'                 => fake()->randomFloat(2, 1, 100),
            'quantity'              => fake()->randomNumber(3),
            'product_status_id'     => ProductStatus::factory(),
            'product_category_id'   => ProductCategory::factory(),
            'vendor_id'             => Vendor::factory(),
        ];
    }

    public function configure(): static
    {
        $images = collect(Storage::files('demo-images'));

        return $this->afterCreating(function (Product $product) use ($images) {
            if ($images->isNotEmpty()) {
                $product->addMediaFromDisk($images->random())
                    ->preservingOriginal()
                    ->toMediaCollection('featured_image');
            }
        });
    }
}
