<?php

namespace Tests\Feature\App\Requests\V1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Requests\V1\RegisterUserRequest;
use Tests\TestCase;
use Illuminate\Support\Facades\Validator;
use App\Models\User;

class RegisterUserRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_validatesNameField()
    {
        $validData = [
            'name' => 'Valid Name',
            'email' => 'valid@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $request = new RegisterUserRequest();
        $validator = Validator::make($validData, $request->rules());

        $this->assertFalse($validator->fails());

        $invalidData = [
            'name' => '',
            'email' => 'valid@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $validator = Validator::make($invalidData, $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }

    public function test_validatesEmailField()
    {
        $validData = [
            'name' => 'Valid Name',
            'email' => 'valid@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $request = new RegisterUserRequest();
        $validator = Validator::make($validData, $request->rules());

        $this->assertFalse($validator->fails());

        $invalidData = [
            'name' => 'Valid Name',
            'email' => '',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $validator = Validator::make($invalidData, $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('email', $validator->errors()->toArray());

        $invalidData['email'] = 'invalid-email';
        $validator = Validator::make($invalidData, $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('email', $validator->errors()->toArray());

        User::create([
            'name' => 'Existing User',
            'email' => 'valid@example.com',
            'password' => bcrypt('password123'),
        ]);

        $invalidData['email'] = 'valid@example.com';
        $validator = Validator::make($invalidData, $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('email', $validator->errors()->toArray());
    }

    public function test_validatesPasswordField()
    {
        $validData = [
            'name' => 'Valid Name',
            'email' => 'valid@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $request = new RegisterUserRequest();
        $validator = Validator::make($validData, $request->rules());

        $this->assertFalse($validator->fails());

        $invalidData = [
            'name' => 'Valid Name',
            'email' => 'valid@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
        ];

        $validator = Validator::make($invalidData, $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('password', $validator->errors()->toArray());

        $invalidData['password'] = 'password123';
        $invalidData['password_confirmation'] = 'differentpassword';
        $validator = Validator::make($invalidData, $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('password', $validator->errors()->toArray());
    }
}