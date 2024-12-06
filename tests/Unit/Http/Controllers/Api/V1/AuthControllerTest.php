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

        $mockResponse = [
            'data' => [
                'token' => 'mocked_token',
                'refresh_token' => 'mocked_refresh_token',
            ],
            'message' => 'User registered successfully',
            'status' => 200,
        ];

        $response = $this->postJson('/api/register', $requestData);

        $response
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'refresh_token'
                ],
                'message',
                'status'
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'johndoe@example.com',
        ]);
    }

    /** Login
     * 
     *  1. Pass user information and see if token information gets returned, mock return data
     */

    /**
     * Logout
     * 
     * 1. Login as a user and save returned data, using the tokens and see if calling logout deletes this data successfully
     * 2. Remove access token, and try logout to see if it fails
     */

    /**
     *  Refresh Tokens
     * 
     *  1. Login as a user and save returned data, test to see if by passing token and refresh token you can successfully get new ones
     *  2. Remove access token, and try logout to see if it fails
     *  3. Remove refresh token, and see if it fails
     */
}
