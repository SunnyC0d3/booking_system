<?php

namespace Tests\Feature\App\Requests\V1;

use App\Requests\V1\StoreProductCategoryRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;
use App\Models\ProductCategory;

class StoreProductCategoryRequestTest extends TestCase
{
    public function test_validation_fails_when_name_is_missing()
    {
        $data = [];
        $rules = (new StoreProductCategoryRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->fails(), 'Validation should fail when name is missing.');
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }

    public function test_validation_fails_when_name_is_not_unique()
    {
        $existingCategory = ProductCategory::factory()->create(['name' => 'Existing Category']);

        $data = ['name' => 'Existing Category'];
        $rules = (new StoreProductCategoryRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->fails(), 'Validation should fail when name is not unique.');
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }

    public function test_validation_fails_when_parent_id_does_not_exist()
    {
        $data = ['name' => 'New Category', 'parent_id' => 9999];
        $rules = (new StoreProductCategoryRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->fails(), 'Validation should fail when parent_id does not exist.');
        $this->assertArrayHasKey('parent_id', $validator->errors()->toArray());
    }

    public function test_validation_passes_with_valid_data()
    {
        $parentCategory = ProductCategory::factory()->create();
        
        $data = ['name' => 'New Unique Category', 'parent_id' => $parentCategory->id];
        $rules = (new StoreProductCategoryRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertFalse($validator->fails(), 'Validation should pass with a unique name and valid parent_id.');
    }
}