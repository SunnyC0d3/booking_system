<?php

namespace Tests\Feature\App\Auth\V1;

use App\Auth\V1\UserAuth;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Mockery;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Http\Middleware\V1\VerifyHmac;
use Illuminate\Support\Facades\DB;

class UserAuthTest extends TestCase
{
    use RefreshDatabase;

    private $userAuth;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(VerifyHmac::class);

        DB::table('oauth_clients')->insert([
            'id' => env('PASSPORT_PERSONAL_ACCESS_CLIENT_ID'),
            'secret' => env('PASSPORT_PERSONAL_ACCESS_CLIENT_SECRET'),
            'name' => 'User Access Token',
            'redirect' => 'http://localhost',
            'personal_access_client' => true,
            'password_client' => false,
            'revoked' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('roles')->insert([
            'name' => 'User'
        ]);

        $this->userAuth = new UserAuth();
    }

    public function test_register_successfully()
    {
        $request = Request::create('/api/register', 'POST', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response = $this->userAuth->register($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('User registered successfully.', $response->original['message']);
    }

    public function test_login_with_valid_credentials()
    {
        $request = Request::create('/api/register', 'POST', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $this->userAuth->register($request);

        $request = Request::create('/api/login', 'POST', [
            'email' => 'test@example.com',
            'password' => 'password123',
            'remember' => true,
        ]);

        $response = $this->userAuth->login($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('User logged in successfully.', $response->original['message']);
        $this->assertArrayHasKey('access_token', $response->original['data']);
    }

    public function test_login_with_invalid_credentials()
    {
        $request = Request::create('/login', 'POST', [
            'email' => 'invalid@example.com',
            'password' => 'wrongpassword',
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid credentials.');

        $this->userAuth->login($request);
    }

    public function test_logout_successfully()
    {
        $user = User::factory()->user()->create();
        $user->createToken('User Access Token')->accessToken;

        $response = $this->userAuth->logout();

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('User logged out successfully', $response->original['message']);
    }

    public function test_forgot_password_success()
    {
        $request = Request::create('/api/register', 'POST', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $this->userAuth->register($request);

        $request = Request::create('/forgot-password', 'POST', [
            'email' => 'test@example.com',
        ]);

        $response = $this->userAuth->forgotPassword($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('We have emailed your password reset link.', $response->original['message']);
    }

    public function test_password_reset_success()
    {
        $request = Request::create('/api/register', 'POST', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $this->userAuth->register($request);
        
        $request = Request::create('/reset-password', 'POST', [
            'email' => 'test@example.com',
            'token' => 'dummy-token',
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ]);

        $user = Mockery::mock(User::class);
        $user->shouldReceive('forceFill')->andReturnSelf();
        $user->shouldReceive('setRememberToken')->andReturnSelf();
        $user->shouldReceive('save')->once();

        Password::shouldReceive('reset')->andReturnUsing(function ($credentials, $callback) use ($user) {
            $callback($user, $credentials['password']);
            return Password::PASSWORD_RESET;
        });

        $response = $this->userAuth->passwordReset($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Your password has been reset.', $response->original['message']);
    }
}