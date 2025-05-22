<?php

namespace Tests\Feature\App\Requests\V1;

use App\Requests\V1\AssignPermissionRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;
use App\Models\Permission;

class AssignPermissionRequestTest extends TestCase
{
    public function test_validation_fails_when_permissions_is_missing()
    {
        $data = [];
        $rules = (new AssignPermissionRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->fails(), 'Validation should fail when permissions is missing.');
        $this->assertArrayHasKey('permissions', $validator->errors()->toArray());
    }

    public function test_validation_fails_when_permissions_is_not_array()
    {
        $data = ['permissions' => 'not-an-array'];
        $rules = (new AssignPermissionRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->fails(), 'Validation should fail when permissions is not an array.');
        $this->assertArrayHasKey('permissions', $validator->errors()->toArray());
    }

    public function test_validation_fails_when_permission_value_does_not_exist()
    {
        $data = ['permissions' => ['invalid_permission']];
        $rules = (new AssignPermissionRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->fails(), 'Validation should fail when permission name does not exist.');
        $this->assertArrayHasKey('permissions.0', $validator->errors()->toArray());
    }

    public function test_validation_passes_with_valid_permissions()
    {
        $permission1 = Permission::factory()->create(['name' => 'view_users']);
        $permission2 = Permission::factory()->create(['name' => 'edit_users']);

        $data = ['permissions' => [$permission1->name, $permission2->name]];
        $rules = (new AssignPermissionRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertFalse($validator->fails(), 'Validation should pass with valid permission names.');
    }
}
