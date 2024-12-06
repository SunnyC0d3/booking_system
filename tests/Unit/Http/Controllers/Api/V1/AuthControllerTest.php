<?php

namespace Tests\Unit\Http\Controllers\Api\V1;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_registers_a_new_user_and_returns_api_token()
    {
        $requestData = [
            'name' => 'John Doe',
            'email' => 'johndoe@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        //register new user, by passing correct data
        //validates, assert if validated correctly
        //check if json data matches mocked data
        //check if user is created in db
    }
}
