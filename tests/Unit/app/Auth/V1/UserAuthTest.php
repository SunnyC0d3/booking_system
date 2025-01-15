<?php

namespace Tests\Unit\App\Auth\V1;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Http\Request;
use App\Auth\V1\UserAuth;
use Illuminate\Support\Facades\Hash;
use App\Models\PersonalAccessToken;
use Illuminate\Support\Str;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UserAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_register()
    {
        $request = Request::create('/register', 'POST', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $userAuth = new UserAuth();
        $response = $userAuth->register($request);

        $this->assertEquals(200, $response->status());                                                                                        
        $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
    }

    public function test_login()
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
        ]);

        $request = Request::create('/login', 'POST', [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $userAuth = new UserAuth();
        $response = $userAuth->login($request);

        $this->assertEquals(200, $response->status());
        $this->assertNotNull($userAuth->getAuthenticatedUser());
    }

    public function test_logout()
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
        ]);

        $accessToken = Str::random(60);
        $refreshToken = Str::random(60);

        PersonalAccessToken::create([
            'tokenable_id' => $user->id,
            'tokenable_type' => User::class,
            'name' => 'API Token',
            'token' => hash('sha256', $accessToken),
            'abilities' => ['*'],
        ]);

        PersonalAccessToken::create([
            'tokenable_id' => $user->id,
            'tokenable_type' => User::class,
            'name' => 'API Refresh Token',
            'token' => hash('sha256', $refreshToken),
            'abilities' => ['*'],
        ]);

        $userAuth = new UserAuth();
        $userAuth->setAuthenticatedUser($user);

        $request = Request::create('/logout', 'POST', [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
        ]);

        $response = $userAuth->logout($request);

        $this->assertEquals(200, $response->status());
    }

    public function test_refreshToken()
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
        ]);

        $accessToken = Str::random(60);
        $refreshToken = Str::random(60);

        PersonalAccessToken::create([
            'tokenable_id' => $user->id,
            'tokenable_type' => User::class,
            'name' => 'API Token',
            'token' => hash('sha256', $accessToken),
            'abilities' => ['*'],
        ]);

        PersonalAccessToken::create([
            'tokenable_id' => $user->id,
            'tokenable_type' => User::class,
            'name' => 'API Refresh Token',
            'token' => hash('sha256', $refreshToken),
            'abilities' => ['*'],
            'refresh_token_expires_at' => now()->addDay(),
        ]);

        $userAuth = new UserAuth();
        $userAuth->setAuthenticatedUser($user);

        $request = Request::create('/refresh-token', 'POST', [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
        ]);

        $response = $userAuth->refreshToken($request);

        $this->assertEquals(200, $response->status());
        $this->assertNotNull($userAuth->getAuthenticatedUser());
    }

    public function test_canCreate()
    {
        $request = Request::create('/register', 'POST', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $userAuth = new UserAuth();
        $response = $userAuth->register($request);

        $this->assertTrue($userAuth->canCreate());
    }

    public function test_canReplace()
    {
        $request = Request::create('/register', 'POST', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $userAuth = new UserAuth();
        $response = $userAuth->register($request);

        $this->assertTrue($userAuth->canReplace());
    }

    public function test_canUpdate()
    {
        $request = Request::create('/register', 'POST', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $userAuth = new UserAuth();
        $response = $userAuth->register($request);

        $this->assertTrue($userAuth->canUpdate());
    }

    public function test_canDelete()
    {
        $request = Request::create('/register', 'POST', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $userAuth = new UserAuth();
        $response = $userAuth->register($request);

        $this->assertTrue($userAuth->canDelete());
    }

    public function test_only()
    {
        $request = Request::create('/register', 'POST', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $userAuth = new UserAuth();
        $response = $userAuth->register($request);

        $this->assertTrue($userAuth->only());
    }
}