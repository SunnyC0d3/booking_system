<?php

namespace Tests\Feature\App\Requests\V1;

use App\Requests\V1\PasswordResetRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class PasswordResetRequestTest extends TestCase
{
    public function test_validation_fails_when_token_is_missing()
    {
        $data = [
            'email' => 'user@example.com',
            'password' => 'securepassword',
            'password_confirmation' => 'securepassword',
        ];

        $rules = (new PasswordResetRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->fails(), 'Validation should fail when token is missing.');
        $this->assertArrayHasKey('token', $validator->errors()->toArray());
    }

    public function test_validation_fails_when_email_is_invalid()
    {
        $data = [
            'token' => 'valid-token',
            'email' => 'invalid-email',
            'password' => 'securepassword',
            'password_confirmation' => 'securepassword',
        ];

        $rules = (new PasswordResetRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->fails(), 'Validation should fail with invalid email format.');
        $this->assertArrayHasKey('email', $validator->errors()->toArray());
    }

    public function test_validation_fails_when_email_does_not_exist()
    {
        $data = [
            'token' => 'valid-token',
            'email' => 'nonexistent@example.com',
            'password' => 'securepassword',
            'password_confirmation' => 'securepassword',
        ];

        $rules = (new PasswordResetRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->fails(), 'Validation should fail when email does not exist.');
        $this->assertArrayHasKey('email', $validator->errors()->toArray());
    }

    public function test_validation_fails_when_password_confirmation_does_not_match()
    {
        $data = [
            'token' => 'valid-token',
            'email' => 'user@example.com',
            'password' => 'securepassword',
            'password_confirmation' => 'differentpassword',
        ];

        $rules = (new PasswordResetRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->fails(), 'Validation should fail when password confirmation does not match.');
        $this->assertArrayHasKey('password', $validator->errors()->toArray());
    }

    public function test_validation_fails_when_password_is_too_short()
    {
        $data = [
            'token' => 'valid-token',
            'email' => 'user@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
        ];

        $rules = (new PasswordResetRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->fails(), 'Validation should fail when password is too short.');
        $this->assertArrayHasKey('password', $validator->errors()->toArray());
    }

    public function test_validation_fails_when_token_is_empty()
    {
        $data = [
            'token' => '',
            'email' => 'user@example.com',
            'password' => 'securepassword',
            'password_confirmation' => 'securepassword',
        ];

        $rules = (new PasswordResetRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->fails(), 'Validation should fail when token is empty.');
        $this->assertArrayHasKey('token', $validator->errors()->toArray());
    }
}
