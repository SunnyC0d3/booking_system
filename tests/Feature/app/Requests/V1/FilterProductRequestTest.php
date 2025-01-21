<?php

namespace Tests\Feature\App\Requests\V1;

use Tests\TestCase;
use Illuminate\Support\Facades\Validator;
use App\Requests\V1\FilterProductRequest;

class FilterProductRequestTest extends TestCase
{
    public function test_validation_passes_with_valid_data()
    {
        $data = [
            'filter' => [
                'name' => 'Product Name',
                'price' => '10.99,15.00,20.50',
                'category' => '1,2,3',
                'quantity' => 100,
                'created_at' => '2025-01-01,2025-02-01',
                'updated_at' => '2025-01-01',
                'search' => 'Search Query',
                'include' => 'details,category',
            ],
            'page' => 1,
            'per_page' => 25,
            'sort' => 'name,price',
        ];

        $validator = Validator::make($data, (new FilterProductRequest())->rules());

        $this->assertFalse($validator->fails(), 'Valid data should pass validation.');
    }

    public function test_validation_fails_with_invalid_data()
    {
        $data = [
            'filter' => [
                'name' => str_repeat('A', 256),
                'price' => 'invalid,entry',
                'category' => 'invalid_category',
                'quantity' => -5,
                'created_at' => 'not-a-date',
                'updated_at' => '2025-99-99',
                'search' => str_repeat('A', 256),
                'include' => 'details,invalid$',
            ],
            'page' => 0,
            'per_page' => 200,
            'sort' => 'invalid_sort',
        ];

        $validator = Validator::make($data, (new FilterProductRequest())->rules());

        $this->assertTrue($validator->fails(), 'Invalid data should fail validation.');
    }
}
