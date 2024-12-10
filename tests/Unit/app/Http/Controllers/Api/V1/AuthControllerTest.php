<?php

namespace Tests\Unit\App\Http\Controllers\Api\V1;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_registersANewUserAndReturnsApiToken()
    {
        $requestData = [
            'name' => 'John Doe',
            'email' => 'johndoe@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
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

    public function test_logsInAUserAndReturnsNewToken()
    {
        \App\Models\User::factory()->create([
            'email' => 'johndoe@example.com',
            'password' => Hash::make('password123'),
        ]);

        $loginData = [
            'email' => 'johndoe@example.com',
            'password' => 'password123',
        ];

        $response = $this->postJson('/api/login', $loginData);

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

        $this->assertNotNull($response['data']['token']);
    }

    public function test_logsOutAUserWhichShouldDeleteAllTheRelatedTokens()
    {
        $user = \App\Models\User::factory()->create([
            'email' => 'johndoe@example.com',
            'password' => Hash::make('password123'),
        ]);

        $loginData = [
            'email' => 'johndoe@example.com',
            'password' => 'password123',
        ];

        $response = $this->postJson('/api/login', $loginData);

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

        $user = Sanctum::actingAs($user, ['user:only']);

        $token = $response['data']['token'];
        $refreshToken = $response['data']['refresh_token'];

        $response = $this->postJson('/api/logout', [
            'refresh_token' => $refreshToken
        ])
            ->assertStatus(200)
            ->assertJson([
                'message' => 'User logged out Successfully.',
            ]);
    }

    public function test_logInAsAUserAndSeeIfPassingTokenAndRefreshTokenUpdatesAndReturnsNewTokens()
    {
        \App\Models\User::factory()->create([
            'email' => 'johndoe@example.com',
            'password' => Hash::make('password123'),
        ]);

        $loginData = [
            'email' => 'johndoe@example.com',
            'password' => 'password123',
        ];

        $response = $this->postJson('/api/login', $loginData);

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

        $token = $response['data']['token'];
        $refreshToken = $response['data']['refresh_token'];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json'
        ])
            ->postJson('/api/token/refresh', [
                'refresh_token' => $refreshToken
            ])
            ->assertStatus(200)
            ->assertJson([
                'message' => 'New tokens have been generated.',
            ])
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'refresh_token'
                ],
                'message',
                'status'
            ]);
    }

    public function test_logInAsAUserAndSeeIfPassingTokenAndFakeRefreshTokenFails()
    {
        \App\Models\User::factory()->create([
            'email' => 'johndoe@example.com',
            'password' => Hash::make('password123'),
        ]);

        $loginData = [
            'email' => 'johndoe@example.com',
            'password' => 'password123',
        ];

        $response = $this->postJson('/api/login', $loginData);

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

        $token = $response['data']['token'];
        $refreshToken = $response['data']['refresh_token'];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json'
        ])
            ->postJson('/api/token/refresh', [
                'refresh_token' => 'fake_token'
            ])
            ->assertStatus(400);
    }
}
