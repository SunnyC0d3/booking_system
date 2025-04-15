<?php

namespace Tests\Feature\App\Requests\V1;

use App\Models\Role;
use App\Models\User;
use App\Requests\V1\StoreUserRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class StoreUserRequestTest extends TestCase
{
    public function test_validation_fails_when_required_fields_are_missing()
    {
        $data = [];
        $rules = (new StoreUserRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
        $this->assertArrayHasKey('email', $validator->errors()->toArray());
        $this->assertArrayHasKey('password', $validator->errors()->toArray());
        $this->assertArrayHasKey('role_id', $validator->errors()->toArray());
        $this->assertArrayHasKey('address.address_line1', $validator->errors()->toArray());
        $this->assertArrayHasKey('address.city', $validator->errors()->toArray());
        $this->assertArrayHasKey('address.country', $validator->errors()->toArray());
        $this->assertArrayHasKey('address.postal_code', $validator->errors()->toArray());
    }

    public function test_validation_fails_when_email_is_not_unique()
    {
        $user = User::factory()->create(['email' => 'duplicate@example.com']);
        $role = Role::factory()->create();

        $data = [
            'name' => 'John Doe',
            'email' => 'duplicate@example.com',
            'password' => 'password123',
            'role_id' => $role->id,
            'address' => [
                'address_line1' => '123 Street',
                'city' => 'London',
                'country' => 'UK',
                'postal_code' => 'ABC123'
            ]
        ];

        $rules = (new StoreUserRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('email', $validator->errors()->toArray());
    }

    public function test_validation_passes_with_valid_data()
    {
        $role = Role::factory()->create();

        $data = [
            'name' => 'Jane Doe',
            'email' => 'jane.doe@example.com',
            'password' => 'securePass123',
            'role_id' => $role->id,
            'address' => [
                'address_line1' => '456 Road',
                'city' => 'Manchester',
                'country' => 'UK',
                'postal_code' => 'XYZ789',
                'address_line2' => 'Apt 4B',
                'state' => 'England',
            ]
        ];

        $rules = (new StoreUserRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertFalse($validator->fails());
    }
}
