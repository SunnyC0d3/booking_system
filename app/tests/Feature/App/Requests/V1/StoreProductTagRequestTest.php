<?php

namespace Tests\Feature\App\Requests\V1;

use App\Requests\V1\StoreProductTagRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;
use App\Models\ProductTag;

class StoreProductTagRequestTest extends TestCase
{
    public function test_validation_fails_when_name_is_missing()
    {
        $data = [];
        $rules = (new StoreProductTagRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->fails(), 'Validation should fail when name is missing.');
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }

    public function test_validation_fails_when_name_is_not_unique()
    {
        $existingTag = ProductTag::factory()->create(['name' => 'Existing Tag']);

        $data = ['name' => 'Existing Tag'];
        $rules = (new StoreProductTagRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->fails(), 'Validation should fail when name is not unique.');
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }

    public function test_validation_passes_with_valid_data()
    {
        $data = ['name' => 'New Unique Tag'];
        $rules = (new StoreProductTagRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertFalse($validator->fails(), 'Validation should pass with a unique name.');
    }
}
