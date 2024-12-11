<?php

namespace Tests\Unit\App\Http\Controllers\Api\V1;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use App\Models\User;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_registersANewUserAndReturnsApiToken()
    {
        $client = User::factory()->create();
        $token = $client->createToken('Test Token', ['client:only'])->plainTextToken;

        $requestData = [
            'name' => 'John Doe',
            'email' => 'johndoe@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/register', $requestData);

        $response
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'access_token',
                    'refresh_token'
                ],
                'message',
                'status'
            ]);
    }

    public function test_logsInAUserAndReturnsNewToken()
    {
        $client = User::factory()->create();
        $token = $client->createToken('Test Token', ['client:only'])->plainTextToken;

        User::factory()->create([
            'name' => 'John Doe',
            'email' => 'johndoe@example.com',
            'password' => Hash::make('password123'),
        ]);

        $loginData = [
            'email' => 'johndoe@example.com',
            'password' => 'password123',
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/login', $loginData);

        $response
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'access_token',
                    'refresh_token'
                ],
                'message',
                'status'
            ]);

        $this->assertNotNull($response['data']['access_token']);
    }

    public function test_logsOutAUserWhichShouldDeleteAllTheRelatedTokens()
    {
        $client = User::factory()->create();
        $token = $client->createToken('Test Token', ['client:only'])->plainTextToken;

        $user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'johndoe@example.com',
            'password' => Hash::make('password123'),
        ]);

        $loginData = [
            'email' => 'johndoe@example.com',
            'password' => 'password123',
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/login', $loginData);

        $response
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'access_token',
                    'refresh_token'
                ],
                'message',
                'status'
            ]);

        $userToken = $response['data']['access_token'];
        $refreshToken = $response['data']['refresh_token'];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/logout', [
            'access_token' => $userToken,
            'refresh_token' => $refreshToken
        ])
            ->assertStatus(200)
            ->assertJson([
                'message' => 'User logged out Successfully.',
            ]);
    }

    public function test_logInAsAUserAndSeeIfPassingAccessTokenAndRefreshTokenUpdatesAndReturnsNewTokens()
    {
        $client = User::factory()->create();
        $token = $client->createToken('Test Token', ['client:only'])->plainTextToken;

        User::factory()->create([
            'name' => 'John Doe',
            'email' => 'johndoe@example.com',
            'password' => Hash::make('password123'),
        ]);

        $loginData = [
            'email' => 'johndoe@example.com',
            'password' => 'password123',
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/login', $loginData);

        $response
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'access_token',
                    'refresh_token'
                ],
                'message',
                'status'
            ]);

        $userToken = $response['data']['access_token'];
        $refreshToken = $response['data']['refresh_token'];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json'
        ])
            ->postJson('/api/token/refresh', [
                'access_token' => $userToken,
                'refresh_token' => $refreshToken
            ])
            ->assertStatus(200)
            ->assertJson([
                'message' => 'New tokens have been generated.',
            ])
            ->assertJsonStructure([
                'data' => [
                    'access_token',
                    'refresh_token'
                ],
                'message',
                'status'
            ]);
    }

    public function test_logInAsAUserAndSeeIfPassingFakeAccessTokenAndFakeRefreshTokenFails()
    {
        $client = User::factory()->create();
        $token = $client->createToken('Test Token', ['client:only'])->plainTextToken;

        User::factory()->create([
            'name' => 'John Doe',
            'email' => 'johndoe@example.com',
            'password' => Hash::make('password123'),
        ]);

        $loginData = [
            'email' => 'johndoe@example.com',
            'password' => 'password123',
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/login', $loginData);

        $response
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'access_token',
                    'refresh_token'
                ],
                'message',
                'status'
            ]);

        $userToken = $response['data']['access_token'];
        $refreshToken = $response['data']['refresh_token'];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json'
        ])
            ->postJson('/api/token/refresh', [
                'access_token' => 'fake_token',
                'refresh_token' => 'fake_token'
            ])
            ->assertStatus(400);
    }
}
