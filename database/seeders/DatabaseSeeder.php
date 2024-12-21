<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Attribute;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        $categories = Category::factory(5)->create();

        $categories->each(function (Category $category) {
            $products = Product::factory(10)->create();

            $products->each(function (Product $product) use ($category) {
                $product->categories()->attach($category);

                ProductImage::factory(3)->create([
                    'imageable_id' => $product->id,
                    'imageable_type' => Product::class,
                ]);

                Attribute::factory(3)->create([
                    'attributable_id' => $product->id,
                    'attributable_type' => Product::class,
                ]);
            });
        });
    }
}
