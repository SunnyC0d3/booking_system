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
        // Create categories
        $categories = Category::factory(5)->create();

        // Create products and associate them with categories
        $categories->each(function (Category $category) {
            $products = Product::factory(10)->create();

            // Add images and attributes for each product
            $products->each(function (Product $product) use ($category) {
                // Assign the category to the product
                $product->categories()->attach($category);

                // Add polymorphic product images
                ProductImage::factory(3)->create([
                    'imageable_id' => $product->id,
                    'imageable_type' => Product::class,
                ]);

                // Add polymorphic attributes
                Attribute::factory(3)->create([
                    'attributable_id' => $product->id,
                    'attributable_type' => Product::class,
                ]);
            });
        });
    }
}
