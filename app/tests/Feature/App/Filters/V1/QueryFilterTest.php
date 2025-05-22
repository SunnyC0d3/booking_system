<?php

namespace Tests\Feature\App\Filters\V1;

use App\Filters\V1\QueryFilter;
use App\Models\Product;
use Illuminate\Http\Request;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class QueryFilterTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();
    }

    public function test_applies_filters_using_apply()
    {
        $specificProduct = Product::factory()->create(['name' => 'Product A']);

        $request = Request::create('', 'GET', ['name' => 'Product A']);

        $queryFilter = new class($request) extends QueryFilter {
            public function name($value)
            {
                $this->builder->where('name', $value);
            }
        };

        $builder = Product::query();
        $response = $queryFilter->apply($builder);

        $this->assertCount(1, $response->get());
        $this->assertEquals('Product A', $response->first()->name);
    }

    public function test_applies_filters_using_filter_method()
    {
        $specificProduct = Product::factory()->create(['name' => 'Product A', 'price' => 40.00]);

        $request = Request::create('', 'GET');

        $queryFilter = new class($request) extends QueryFilter {
            public function name($value)
            {
                $this->builder->where('name', $value);
            }

            public function price($value)
            {
                $this->builder->where('price', $value);
            }
        };

        $builder = Product::query();
        $response = $queryFilter->apply($builder);

        $response->filter($queryFilter);

        $this->assertCount(1, $response->get());
        $this->assertEquals('Product A', $response->first()->name);
        $this->assertEquals(40.00, $response->first()->price);
    }

    public function test_applies_sorting_correctly_using_sort_method()
    {
        $specificProducts = Product::factory()->createMany([
            ['name' => 'Product A', 'price' => 50.00],
            ['name' => 'Product B', 'price' => 30.00],
            ['name' => 'Product C', 'price' => 40.00],
        ]);

        $request = Request::create('', 'GET', ['sort' => '-price']);

        $queryFilter = new class($request) extends QueryFilter {
            protected array $sortable = ['price'];
        };

        $builder = Product::query();
        $response = $queryFilter->apply($builder);

        $sortedPrices = $response->pluck('price')->toArray();

        $this->assertEquals([50.00, 40.00, 30.00], $sortedPrices);
    }
}
