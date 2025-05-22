<?php

namespace Tests\Feature\App\Requests\V1;

use App\Requests\V1\LoginUserRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class LoginUserRequestTest extends TestCase
{
    public function test_validation_fails_when_email_is_missing()
    {
        $data = [
            'password' => 'securepassword',
            'remember' => true,
        ];

        $rules = (new LoginUserRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->fails(), 'Validation should fail when email is missing.');
        $this->assertArrayHasKey('email', $validator->errors()->toArray());
    }

    public function test_validation_fails_with_invalid_email()
    {
        $data = [
            'email' => 'invalid-email',
            'password' => 'securepassword',
            'remember' => true,
        ];

        $rules = (new LoginUserRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->fails(), 'Validation should fail for invalid email format.');
        $this->assertArrayHasKey('email', $validator->errors()->toArray());
    }

    public function test_validation_fails_when_password_is_too_short()
    {
        $data = [
            'email' => 'user@example.com',
            'password' => 'short',
            'remember' => true,
        ];

        $rules = (new LoginUserRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->fails(), 'Validation should fail when password is too short.');
        $this->assertArrayHasKey('password', $validator->errors()->toArray());
    }

    public function test_validation_fails_when_remember_is_not_boolean()
    {
        $data = [
            'email' => 'user@example.com',
            'password' => 'securepassword',
            'remember' => 'invalid-value',
        ];

        $rules = (new LoginUserRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->fails(), 'Validation should fail when remember is not a boolean.');
        $this->assertArrayHasKey('remember', $validator->errors()->toArray());
    }

    public function test_validation_fails_when_email_does_not_exist_in_database()
    {
        $data = [
            'email' => 'nonexisting@example.com',
            'password' => 'securepassword',
            'remember' => false,
        ];

        $rules = (new LoginUserRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->fails(), 'Validation should fail when the email does not exist in the database.');
        $this->assertArrayHasKey('email', $validator->errors()->toArray());
    }
}
