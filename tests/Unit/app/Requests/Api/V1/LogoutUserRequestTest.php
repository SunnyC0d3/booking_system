<?php

namespace Tests\Unit\App\Requests\Api\V1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Requests\Api\V1\LogoutUserRequest;
use Tests\TestCase;
use Illuminate\Support\Facades\Validator;

class LogoutUserRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_validatesRefreshTokenField()
    {
        $validData = [
            'refresh_token' => 'validRefreshToken12345',
        ];

        $request = new LogoutUserRequest();
        $validator = Validator::make($validData, $request->rules());

        $this->assertFalse($validator->fails());

        $invalidData = [
            'refresh_token' => '',
        ];

        $validator = Validator::make($invalidData, $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('refresh_token', $validator->errors()->toArray());

        $invalidData = [
            'refresh_token' => str_repeat('a', 61),
        ];

        $validator = Validator::make($invalidData, $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('refresh_token', $validator->errors()->toArray());
    }

    public function test_allowsValidRefreshTokenToPass()
    {
        $validData = [
            'refresh_token' => 'validRefreshToken12345',
        ];

        $request = new LogoutUserRequest();
        $validator = Validator::make($validData, $request->rules());

        $this->assertFalse($validator->fails());
    }
}
