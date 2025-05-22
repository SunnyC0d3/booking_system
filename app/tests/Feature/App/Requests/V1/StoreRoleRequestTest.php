<?php

namespace Tests\Feature\App\Requests\V1;

use App\Requests\V1\StoreRoleRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;
use App\Models\Role;

class StoreRoleRequestTest extends TestCase
{
    public function test_validation_fails_when_name_is_missing()
    {
        $data = [];
        $rules = (new StoreRoleRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->fails(), 'Validation should fail when name is missing.');
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }

    public function test_validation_fails_when_name_is_not_a_string()
    {
        $data = ['name' => 12345];
        $rules = (new StoreRoleRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->fails(), 'Validation should fail when name is not a string.');
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }

    public function test_validation_fails_when_name_exceeds_max_length()
    {
        $data = ['name' => str_repeat('a', 256)];
        $rules = (new StoreRoleRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->fails(), 'Validation should fail when name exceeds max length.');
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }

    public function test_validation_fails_when_name_is_not_unique()
    {
        Role::factory()->create(['name' => 'admin']);

        $data = ['name' => 'admin'];
        $rules = (new StoreRoleRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->fails(), 'Validation should fail when name is not unique.');
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }

    public function test_validation_passes_with_valid_data()
    {
        $data = ['name' => 'user'];
        $rules = (new StoreRoleRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertFalse($validator->fails(), 'Validation should pass with valid data.');
    }
}
