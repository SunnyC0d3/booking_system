<?php

namespace Tests\Feature\App\Requests\V1;

use App\Requests\V1\StoreProductRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class StoreProductRequestTest extends TestCase
{
    public function test_validation_fails_when_name_is_missing()
    {
        $data = [
            'description' => 'A sample product description.',
            'price' => 19.99,
        ];

        $rules = (new StoreProductRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->fails(), 'Validation should fail when the name is missing.');
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }

    public function test_validation_fails_when_price_is_invalid()
    {
        $data = [
            'name' => 'Sample Product',
            'price' => 'invalid-price',
        ];

        $rules = (new StoreProductRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->fails(), 'Validation should fail when the price is invalid.');
        $this->assertArrayHasKey('price', $validator->errors()->toArray());
    }

    public function test_validation_fails_when_attributes_are_invalid()
    {
        $data = [
            'name' => 'Sample Product',
            'price' => 19.99,
            'attributes' => [
                ['key' => '', 'value' => 'Value1'],
                ['key' => 'Key2', 'value' => ''],
            ],
        ];

        $rules = (new StoreProductRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->fails(), 'Validation should fail with invalid attributes.');
        $this->assertArrayHasKey('attributes.0.key', $validator->errors()->toArray());
        $this->assertArrayHasKey('attributes.1.value', $validator->errors()->toArray());
    }

    public function test_validation_fails_when_images_are_invalid()
    {
        $data = [
            'name' => 'Sample Product',
            'price' => 19.99,
            'images' => [
                'not-an-image',
            ],
        ];

        $rules = (new StoreProductRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->fails(), 'Validation should fail when images are invalid.');
        $this->assertArrayHasKey('images.0', $validator->errors()->toArray());
    }

    public function test_validation_passes_with_nullable_fields()
    {
        $data = [
            'name' => 'Sample Product',
            'price' => 19.99,
            'description' => null,
            'category_id' => null,
        ];

        $rules = (new StoreProductRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertFalse($validator->fails(), 'Validation should pass with nullable fields.');
    }
}
