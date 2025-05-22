<?php

namespace Tests\Feature\App\Requests\V1;

use App\Requests\V1\UpdatePermissionRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;
use App\Models\Permission;

class UpdatePermissionRequestTest extends TestCase
{
    public function test_validation_fails_when_name_is_missing()
    {
        $data = [];
        $rules = (new UpdatePermissionRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->fails(), 'Validation should fail when name is missing.');
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }

    public function test_validation_fails_when_name_is_not_a_string()
    {
        $data = ['name' => 12345];
        $rules = (new UpdatePermissionRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->fails(), 'Validation should fail when name is not a string.');
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }

    public function test_validation_fails_when_name_exceeds_max_length()
    {
        $data = ['name' => str_repeat('a', 256)];
        $rules = (new UpdatePermissionRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->fails(), 'Validation should fail when name exceeds max length.');
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }

    public function test_validation_fails_when_name_is_not_unique()
    {
        $existingPermission = Permission::factory()->create(['name' => 'create-vendor']);
        $newPermission = Permission::factory()->create();

        $request = new UpdatePermissionRequest();
        $this->app->instance(UpdatePermissionRequest::class, $request);
        $this->app['router']->getRoutes()->refreshNameLookups();
        $this->app['router']->getRoutes()->refreshActionLookups();

        $this->app['request']->setRouteResolver(function () use ($newPermission) {
            return (object) ['parameters' => ['permission' => $newPermission->id]];
        });

        $data = ['name' => 'create-vendor'];
        $rules = $request->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->fails(), 'Validation should fail when name is not unique.');
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }

    public function test_validation_passes_with_valid_data()
    {
        $permission = Permission::factory()->create(['name' => 'edit-user']);

        $request = new UpdatePermissionRequest();
        $this->app->instance(UpdatePermissionRequest::class, $request);
        $this->app['router']->getRoutes()->refreshNameLookups();
        $this->app['router']->getRoutes()->refreshActionLookups();

        $this->app['request']->setRouteResolver(function () use ($permission) {
            return (object) ['parameters' => ['permission' => $permission->id]];
        });

        $data = ['name' => 'update-user'];
        $rules = $request->rules();
        $validator = Validator::make($data, $rules);

        $this->assertFalse($validator->fails(), 'Validation should pass with valid data.');
    }
}
