<?php

namespace Tests\Feature\App\Requests\V1;

use Tests\TestCase;
use Illuminate\Support\Facades\Validator;
use App\Requests\V1\FilterUserRequest;

class FilterUserRequestTest extends TestCase
{
    public function test_validation_passes_with_valid_data()
    {
        $data = [
            'filter' => [
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'role' => '1,2,3',
                'created_at' => '2025-01-01,2025-02-01',
                'updated_at' => '2025-03-01',
                'search' => 'developer',
                'include' => 'address,role',
            ],
            'page' => 1,
            'per_page' => 25,
            'sort' => 'name,-email',
        ];

        $validator = Validator::make($data, (new FilterUserRequest())->rules());

        $this->assertFalse($validator->fails(), 'Valid data should pass validation.');
    }

    public function test_validation_fails_with_invalid_data()
    {
        $data = [
            'filter' => [
                'name' => str_repeat('A', 256),
                'email' => 'invalid-email',
                'role' => 'abc,def',
                'created_at' => 'not-a-date',
                'updated_at' => '2025-99-99',
                'search' => str_repeat('A', 256),
                'include' => 'details,invalid@field',
            ],
            'page' => 0,
            'per_page' => 500,
            'sort' => 'invalid sort value',
        ];

        $validator = Validator::make($data, (new FilterUserRequest())->rules());

        $this->assertTrue($validator->fails(), 'Invalid data should fail validation.');
    }
}
