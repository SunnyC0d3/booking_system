<?php

namespace Tests\Feature\App\Requests\V1;

use App\Requests\V1\UpdateProductCategoryRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;
use App\Models\ProductCategory;

class UpdateProductCategoryRequestTest extends TestCase
{
    public function test_update_validation_fails_when_name_is_missing()
    {
        $productCategory = ProductCategory::factory()->create();

        $data = [];
        $rules = (new UpdateProductCategoryRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->fails(), 'Validation should fail when name is missing.');
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }

    public function test_validation_fails_when_name_is_not_a_string()
    {
        $data = ['name' => 123, 'parent_id' => 9999];
        $rules = (new UpdateProductCategoryRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->fails(), 'Validation should fail when name is not a string.');
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }

    public function test_validation_passes_with_valid_data()
    {
        $parentCategory = ProductCategory::factory()->create();
        $productCategory = ProductCategory::factory()->create([
            'name' => 'Category',
            'parent_id' => $parentCategory->id
        ]);
    
        $request = new UpdateProductCategoryRequest();
        $this->app->instance(UpdateProductCategoryRequest::class, $request);
        $this->app['router']->getRoutes()->refreshNameLookups();
        $this->app['router']->getRoutes()->refreshActionLookups();
    
        $this->app['request']->setRouteResolver(function () use ($productCategory) {
            return (object) ['parameters' => ['productCategory' => $productCategory->id]];
        });
    
        $data = ['name' => 'New Category'];
        $rules = $request->rules();
        $validator = Validator::make($data, $rules);
    
        $this->assertFalse($validator->fails(), 'Validation should pass with valid data.');
    }
}
