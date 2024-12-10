<?php

namespace Tests\Unit\App\Requests\Api\V1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Requests\Api\V1\LoginUserRequest;
use Tests\TestCase;
use Illuminate\Support\Facades\Validator;

class LoginUserRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_validatesEmailAndPasswordFields()
    {
        $validData = [
            'email' => 'test@example.com',
            'password' => 'password123',
        ];

        $request = new LoginUserRequest();
        $validator = Validator::make($validData, $request->rules());

        $this->assertFalse($validator->fails());

        $invalidData = [
            'email' => '',
            'password' => 'password123',
        ];

        $validator = Validator::make($invalidData, $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('email', $validator->errors()->toArray());

        $invalidData = [
            'email' => 'test@example.com',
            'password' => 'short',
        ];

        $validator = Validator::make($invalidData, $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('password', $validator->errors()->toArray());
    }

    public function test_allowsValidEmailAndPasswordToPass()
    {
        $validData = [
            'email' => 'test@example.com',
            'password' => 'password123',
        ];

        $request = new LoginUserRequest();
        $validator = Validator::make($validData, $request->rules());

        $this->assertFalse($validator->fails());
    }
}
