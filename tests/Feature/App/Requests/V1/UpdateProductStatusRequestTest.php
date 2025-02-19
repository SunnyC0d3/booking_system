<?php

namespace Tests\Feature\App\Requests\V1;

use App\Requests\V1\UpdateProductStatusRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;
use App\Models\ProductStatus;

class UpdateProductStatusRequestTest extends TestCase
{
    public function test_update_validation_fails_when_name_is_missing()
    {
        $productStatus = ProductStatus::factory()->create();

        $data = [];
        $rules = (new UpdateProductStatusRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->fails(), 'Validation should fail when name is missing.');
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }

    public function test_validation_fails_when_name_is_not_a_string()
    {
        $data = ['name' => 12345];
        $rules = (new UpdateProductStatusRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->fails(), 'Validation should fail when name is not a string.');
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }

    public function test_validation_passes_with_valid_data()
    {
        $productStatus = ProductStatus::factory()->create(['name' => 'Active']);

        $request = new UpdateProductStatusRequest();
        $this->app->instance(UpdateProductStatusRequest::class, $request);
        $this->app['router']->getRoutes()->refreshNameLookups();
        $this->app['router']->getRoutes()->refreshActionLookups();

        $this->app['request']->setRouteResolver(function () use ($productStatus) {
            return (object) ['parameters' => ['productStatus' => $productStatus->id]];
        });

        $data = ['name' => 'New Active'];
        $rules = $request->rules();
        $validator = Validator::make($data, $rules);

        $this->assertFalse($validator->fails(), 'Validation should pass with valid data.');
    }
}
