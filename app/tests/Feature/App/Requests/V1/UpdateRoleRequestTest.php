<?php

namespace Tests\Feature\App\Requests\V1;

use App\Requests\V1\UpdateRoleRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;
use App\Models\Role;

class UpdateRoleRequestTest extends TestCase
{
    public function test_validation_fails_when_name_is_missing()
    {
        $data = [];
        $rules = (new UpdateRoleRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->fails(), 'Validation should fail when name is missing.');
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }

    public function test_validation_fails_when_name_is_not_a_string()
    {
        $data = ['name' => 12345];
        $rules = (new UpdateRoleRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->fails(), 'Validation should fail when name is not a string.');
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }

    public function test_validation_fails_when_name_exceeds_max_length()
    {
        $data = ['name' => str_repeat('a', 256)];
        $rules = (new UpdateRoleRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->fails(), 'Validation should fail when name exceeds max length.');
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }

    public function test_validation_fails_when_name_is_not_unique()
    {
        $existingRole = Role::factory()->create(['name' => 'admin']);
        $newRole = Role::factory()->create();

        $request = new UpdateRoleRequest();
        $this->app->instance(UpdateRoleRequest::class, $request);
        $this->app['router']->getRoutes()->refreshNameLookups();
        $this->app['router']->getRoutes()->refreshActionLookups();

        $this->app['request']->setRouteResolver(function () use ($newRole) {
            return (object) ['parameters' => ['role' => $newRole->id]];
        });

        $data = ['name' => 'admin'];
        $rules = $request->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->fails(), 'Validation should fail when name is not unique.');
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }

    public function test_validation_passes_with_valid_data()
    {
        $role = Role::factory()->create(['name' => 'admin']);

        $request = new UpdateRoleRequest();
        $this->app->instance(UpdateRoleRequest::class, $request);
        $this->app['router']->getRoutes()->refreshNameLookups();
        $this->app['router']->getRoutes()->refreshActionLookups();

        $this->app['request']->setRouteResolver(function () use ($role) {
            return (object) ['parameters' => ['role' => $role->id]];
        });

        $data = ['name' => 'super admin'];
        $rules = $request->rules();
        $validator = Validator::make($data, $rules);

        $this->assertFalse($validator->fails(), 'Validation should pass with valid data.');
    }
}
