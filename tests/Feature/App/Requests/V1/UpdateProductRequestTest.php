<?php

namespace Tests\Feature\App\Requests\V1;

use App\Requests\V1\UpdateProductRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class UpdateProductRequestTest extends TestCase
{
    public function test_validation_passes_with_no_fields()
    {
        $data = [];

        $rules = (new UpdateProductRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertFalse($validator->fails(), 'Validation should pass when no fields are provided.');
    }

    public function test_validation_fails_with_invalid_price()
    {
        $data = [
            'price' => 'invalid-price',
        ];

        $rules = (new UpdateProductRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->fails(), 'Validation should fail with an invalid price.');
        $this->assertArrayHasKey('price', $validator->errors()->toArray());
    }

    public function test_validation_fails_with_invalid_name()
    {
        $data = [
            'name' => null,
        ];

        $rules = (new UpdateProductRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->fails(), 'Validation should fail with an invalid name.');
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }

    public function test_validation_fails_with_invalid_attributes()
    {
        $data = [
            'attributes' => [
                ['key' => '', 'value' => 'Value1'],
                ['key' => 'Key2', 'value' => ''],
            ],
        ];

        $rules = (new UpdateProductRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->fails(), 'Validation should fail with invalid attributes.');
        $this->assertArrayHasKey('attributes.0.key', $validator->errors()->toArray());
        $this->assertArrayHasKey('attributes.1.value', $validator->errors()->toArray());
    }

    public function test_validation_fails_with_invalid_images()
    {
        $data = [
            'images' => [
                'not-an-image',
            ],
        ];

        $rules = (new UpdateProductRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->fails(), 'Validation should fail with invalid images.');
        $this->assertArrayHasKey('images.0', $validator->errors()->toArray());
    }

    public function test_validation_passes_with_nullable_fields_set_to_null()
    {
        $data = [
            'description' => null,
            'category_id' => null,
        ];

        $rules = (new UpdateProductRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertFalse($validator->fails(), 'Validation should pass when nullable fields are null.');
    }
}
