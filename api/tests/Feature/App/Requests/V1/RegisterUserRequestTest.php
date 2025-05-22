<?php

namespace Tests\Feature\App\Requests\V1;

use App\Requests\V1\RegisterUserRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class RegisterUserRequestTest extends TestCase
{
    public function test_validation_passes_with_valid_data()
    {
        $data = [
            'name' => 'John Doe',
            'email' => 'johndoe@example.com',
            'password' => 'securepassword',
            'password_confirmation' => 'securepassword',
        ];

        $rules = (new RegisterUserRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertFalse($validator->fails(), 'Validation should pass with valid data.');
    }

    public function test_validation_fails_when_name_is_missing()
    {
        $data = [
            'email' => 'johndoe@example.com',
            'password' => 'securepassword',
            'password_confirmation' => 'securepassword',
        ];

        $rules = (new RegisterUserRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->fails(), 'Validation should fail when the name is missing.');
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }

    public function test_validation_fails_when_email_is_invalid()
    {
        $data = [
            'name' => 'John Doe',
            'email' => 'invalid-email',
            'password' => 'securepassword',
            'password_confirmation' => 'securepassword',
        ];

        $rules = (new RegisterUserRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->fails(), 'Validation should fail with an invalid email.');
        $this->assertArrayHasKey('email', $validator->errors()->toArray());
    }

    public function test_validation_fails_when_password_confirmation_does_not_match()
    {
        $data = [
            'name' => 'John Doe',
            'email' => 'johndoe@example.com',
            'password' => 'securepassword',
            'password_confirmation' => 'differentpassword',
        ];

        $rules = (new RegisterUserRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->fails(), 'Validation should fail when password confirmation does not match.');
        $this->assertArrayHasKey('password', $validator->errors()->toArray());
    }

    public function test_validation_fails_when_password_is_too_short()
    {
        $data = [
            'name' => 'John Doe',
            'email' => 'johndoe@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
        ];

        $rules = (new RegisterUserRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->fails(), 'Validation should fail when password is too short.');
        $this->assertArrayHasKey('password', $validator->errors()->toArray());
    }

    public function test_validation_fails_when_email_is_empty()
    {
        $data = [
            'name' => 'John Doe',
            'email' => '',
            'password' => 'securepassword',
            'password_confirmation' => 'securepassword',
        ];

        $rules = (new RegisterUserRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->fails(), 'Validation should fail when email is empty.');
        $this->assertArrayHasKey('email', $validator->errors()->toArray());
    }
}
