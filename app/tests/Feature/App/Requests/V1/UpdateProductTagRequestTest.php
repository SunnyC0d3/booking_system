<?php

namespace Tests\Feature\App\Requests\V1;

use App\Requests\V1\UpdateProductTagRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;
use App\Models\ProductTag;

class UpdateProductTagRequestTest extends TestCase
{
    public function test_update_validation_fails_when_name_is_missing()
    {
        $productTag = ProductTag::factory()->create();

        $data = [];
        $rules = (new UpdateProductTagRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->fails(), 'Validation should fail when name is missing.');
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }

    public function test_validation_fails_when_name_is_not_a_string()
    {
        $data = ['name' => 12345];
        $rules = (new UpdateProductTagRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->fails(), 'Validation should fail when name is not a string.');
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }

    public function test_validation_passes_with_valid_data()
    {
        $productTag = ProductTag::factory()->create(['name' => 'Color']);

        $request = new UpdateProductTagRequest();
        $this->app->instance(UpdateProductTagRequest::class, $request);
        $this->app['router']->getRoutes()->refreshNameLookups();
        $this->app['router']->getRoutes()->refreshActionLookups();

        $this->app['request']->setRouteResolver(function () use ($productTag) {
            return (object) ['parameters' => ['productTag' => $productTag->id]];
        });

        $data = ['name' => 'New Color'];
        $rules = $request->rules();
        $validator = Validator::make($data, $rules);

        $this->assertFalse($validator->fails(), 'Validation should pass with valid data.');
    }
}
