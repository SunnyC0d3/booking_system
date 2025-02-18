<?php

namespace Tests\Feature\App\Requests\V1;

use App\Requests\V1\UpdateProductAttributeRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;
use App\Models\ProductAttribute;

class UpdateProductAttributeRequestTest extends TestCase
{
    public function test_validation_fails_when_name_is_missing()
    {
        $data = [];
        $rules = (new UpdateProductAttributeRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->fails(), 'Validation should fail when name is missing.');
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }

    public function test_validation_fails_when_name_is_not_a_string()
    {
        $data = ['name' => 12345];
        $rules = (new UpdateProductAttributeRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->fails(), 'Validation should fail when name is not a string.');
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }

    public function test_validation_fails_when_name_exceeds_max_length()
    {
        $data = ['name' => str_repeat('a', 256)];
        $rules = (new UpdateProductAttributeRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->fails(), 'Validation should fail when name exceeds max length.');
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }

    public function test_validation_fails_when_name_is_not_unique()
    {
        $existingAttribute = ProductAttribute::factory()->create(['name' => 'Size']);
        $newAttribute = ProductAttribute::factory()->create();

        $request = new UpdateProductAttributeRequest();
        $this->app->instance(UpdateProductAttributeRequest::class, $request);
        $this->app['router']->getRoutes()->refreshNameLookups();
        $this->app['router']->getRoutes()->refreshActionLookups();

        $this->app['request']->setRouteResolver(function () use ($newAttribute) {
            return (object) ['parameters' => ['productAttribute' => $newAttribute->id]];
        });

        $data = ['name' => 'Size'];
        $rules = $request->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->fails(), 'Validation should fail when name is not unique.');
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }

    public function test_validation_passes_with_valid_data()
    {
        $attribute = ProductAttribute::factory()->create(['name' => 'Color']);

        $request = new UpdateProductAttributeRequest();
        $this->app->instance(UpdateProductAttributeRequest::class, $request);
        $this->app['router']->getRoutes()->refreshNameLookups();
        $this->app['router']->getRoutes()->refreshActionLookups();

        $this->app['request']->setRouteResolver(function () use ($attribute) {
            return (object) ['parameters' => ['productAttribute' => $attribute->id]];
        });

        $data = ['name' => 'New Color'];
        $rules = $request->rules();
        $validator = Validator::make($data, $rules);

        $this->assertFalse($validator->fails(), 'Validation should pass with valid data.');
    }
}
