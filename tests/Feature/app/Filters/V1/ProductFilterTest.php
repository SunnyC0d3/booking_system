<?php

namespace Tests\Feature\App\Filters\V1;

use App\Filters\V1\ProductFilter;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Http\Request;
use App\Models\Category;

class ProductFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_filters_by_created_at()
    {
        $products = Product::factory()->count(5)->create();

        $request = Request::create('', 'GET', ['createdAt' => now()->toDateString()]);
        $filter = new ProductFilter($request);

        $builder = Product::query();
        $response = $filter->apply($builder);

        $this->assertEquals(
            $products->filter(fn($product) => $product->created_at->isToday())->count(),
            $response->count()
        );
    }

    public function test_filters_by_updated_at_range()
    {
        $products = Product::factory()->count(5)->create();
        $startDate = now()->subDays(10)->toDateString();
        $endDate = now()->toDateString();

        $request = Request::create('', 'GET', ['updatedAt' => "$startDate,$endDate"]);
        $filter = new ProductFilter($request);

        $builder = Product::query();
        $response = $filter->apply($builder);

        $this->assertEquals(
            $products->filter(fn($product) => $product->updated_at->between($startDate, $endDate))->count(),
            $response->count()
        );
    }

    public function test_filters_by_price_range()
    {
        $products = Product::factory()->count(5)->create();
        $minPrice = 10;
        $maxPrice = 20;

        $request = Request::create('', 'GET', ['price' => "$minPrice,$maxPrice"]);
        $filter = new ProductFilter($request);

        $builder = Product::query();
        $response = $filter->apply($builder);

        $this->assertTrue(
            $response->get()->pluck('price')->every(fn($price) => $price >= $minPrice && $price <= $maxPrice)
        );
    }

    public function test_filters_by_category()
    {
        $category = Category::factory()->create();
        $products = Product::factory()->count(3)->create();
        $products->each(fn($product) => $product->categories()->attach($category->id));

        $request = Request::create('', 'GET', ['category' => $category->id]);
        $filter = new ProductFilter($request);

        $builder = Product::query();
        $response = $filter->apply($builder);

        $this->assertEquals(
            $response->get()->pluck('id')->sort()->toArray(),
            $products->pluck('id')->sort()->toArray()
        );
    }

    public function test_includes_relations()
    {
        $products = Product::factory()->count(5)->create();
        $request = Request::create('', 'GET', ['include' => 'categories']);

        $filter = new ProductFilter($request);
        $builder = Product::query();

        $response = $filter->apply($builder);

        $this->assertFalse(
            $response->get()->pluck('categories')->every(fn($categories) => $categories->isNotEmpty())
        );
    }

    public function test_searches_by_name_and_description()
    {
        $product = Product::factory()->create(['name' => 'TestProduct', 'description' => 'Description of product']);

        $request = Request::create('', 'GET', ['search' => 'Test%']);
        $filter = new ProductFilter($request);

        $builder = Product::query();
        $response = $filter->apply($builder);

        $this->assertTrue(
            $response->get()->contains($product)
        );
    }

    public function test_filters_by_quantity()
    {
        $product = Product::factory()->create(['quantity' => 50]);

        $request = Request::create('', 'GET', ['quantity' => 50]);
        $filter = new ProductFilter($request);

        $builder = Product::query();
        $response = $filter->apply($builder);

        $this->assertTrue(
            $response->get()->contains($product)
        );
    }

    public function test_sorts_by_columns()
    {
        $products = Product::factory()->count(5)->create()->sortBy('price');

        $request = Request::create('', 'GET', ['sort' => 'price']);
        $filter = new ProductFilter($request);

        $builder = Product::query();
        $response = $filter->apply($builder);

        $this->assertEquals(
            $products->pluck('id')->values()->toArray(),
            $response->get()->pluck('id')->values()->toArray()
        );
    }
}
