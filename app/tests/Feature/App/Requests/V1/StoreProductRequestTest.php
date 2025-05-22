<?php

namespace Tests\Feature\App\Requests\V1;

use App\Requests\V1\StoreProductRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class StoreProductRequestTest extends TestCase
{
    public function test_validation_fails_when_required_fields_are_missing()
    {
        $data = [];
        $rules = (new StoreProductRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->fails(), 'Validation should fail when required fields are missing.');
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
        $this->assertArrayHasKey('price', $validator->errors()->toArray());
        $this->assertArrayHasKey('product_category_id', $validator->errors()->toArray());
        $this->assertArrayHasKey('product_status_id', $validator->errors()->toArray());
        $this->assertArrayHasKey('quantity', $validator->errors()->toArray());
    }

    public function test_validation_fails_when_price_is_invalid()
    {
        $data = [
            'name' => 'Sample Product',
            'price' => 'invalid',
            'product_category_id' => 1,
            'product_status_id' => 1,
            'quantity' => 10,
        ];
        $rules = (new StoreProductRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->fails(), 'Validation should fail when the price is invalid.');
        $this->assertArrayHasKey('price', $validator->errors()->toArray());
    }

    public function test_validation_fails_when_product_variants_are_invalid()
    {
        $data = [
            'name' => 'Sample Product',
            'price' => 19.99,
            'product_category_id' => 1,
            'product_status_id' => 1,
            'quantity' => 10,
            'product_variants' => [
                ['product_attribute_id' => null, 'value' => 'Large', 'additional_price' => -5, 'quantity' => -1],
            ],
        ];
        $rules = (new StoreProductRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->fails(), 'Validation should fail when product variants are invalid.');
        $this->assertArrayHasKey('product_variants.0.product_attribute_id', $validator->errors()->toArray());
        $this->assertArrayHasKey('product_variants.0.additional_price', $validator->errors()->toArray());
        $this->assertArrayHasKey('product_variants.0.quantity', $validator->errors()->toArray());
    }

    public function test_validation_fails_when_media_is_invalid()
    {
        $data = [
            'name' => 'Sample Product',
            'price' => 19.99,
            'product_category_id' => 1,
            'product_status_id' => 1,
            'quantity' => 10,
            'media' => [
                'featured_image' => 'not-a-file',
                'gallery' => ['invalid-file'],
            ],
        ];
        $rules = (new StoreProductRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->fails(), 'Validation should fail when media is invalid.');
        $this->assertArrayHasKey('media.featured_image', $validator->errors()->toArray());
        $this->assertArrayHasKey('media.gallery.0', $validator->errors()->toArray());
    }

    public function test_validation_passes_with_valid_data()
    {
        $category = \App\Models\ProductCategory::factory()->create();
        $status = \App\Models\ProductStatus::factory()->create();
        $tag1 = \App\Models\ProductTag::factory()->create();
        $tag2 = \App\Models\ProductTag::factory()->create();
        $attribute = \App\Models\ProductAttribute::factory()->create();

        $image = \Illuminate\Http\UploadedFile::fake()->image('product.jpg');

        $data = [
            'name' => 'Sample Product',
            'price' => 19.99,
            'description' => 'A great product.',
            'product_category_id' => $category->id,
            'product_status_id' => $status->id,
            'quantity' => 10,
            'product_tags' => [$tag1->id, $tag2->id],
            'product_variants' => [
                [
                    'product_attribute_id' => $attribute->id,
                    'value' => 'Large',
                    'additional_price' => 5,
                    'quantity' => 5,
                ],
            ],
            'media' => [
                'featured_image' => $image,
                'gallery' => [$image],
            ],
        ];

        $rules = (new StoreProductRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertFalse($validator->fails(), 'Validation should pass with valid data.');
    }
}
