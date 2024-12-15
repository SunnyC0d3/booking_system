<?php

namespace App\Auth\V1;

use Illuminate\Support\Str;
use App\Models\User;
use App\Models\PersonalAccessToken;
use App\Requests\V1\LoginUserRequest;
use App\Requests\V1\LogoutUserRequest;
use App\Requests\V1\RegisterUserRequest;
use App\Requests\V1\RefreshTokenRequest;
use App\Permissions\V1\Abilities;
use App\Traits\V1\ApiResponses;
use Illuminate\Support\Facades\Hash;

final class UserAuth
{
    use ApiResponses;

    private $authenticatedUser = null;

    private $registerUserRequest;
    private $loginUserRequest;
    private $logoutUserRequest;
    private $refreshTokenRequest;

    public function __construct(
        RegisterUserRequest $registerUserRequest,
        LoginUserRequest $loginUserRequest,
        LogoutUserRequest $logoutUserRequest,
        RefreshTokenRequest $refreshTokenRequest,
    ) {
        $this->registerUserRequest = $registerUserRequest;
        $this->loginUserRequest = $loginUserRequest;
        $this->logoutUserRequest = $logoutUserRequest;
        $this->refreshTokenRequest = $refreshTokenRequest;
    }

    private function createOrUpdateTokens($user, $type = 'create')
    {
        $token = $user->createToken('API token for ' . $user->email, Abilities::getAbilities($user), now()->addDay())->plainTextToken;
        $refreshToken = Str::random(60);

        if ($type === 'create') {
            $user->tokens()->create([
                'name' => 'API Refresh Token',
                'token' => hash('sha256', $refreshToken),
                'refresh_token_expires_at' => now()->addDays(30),
            ]);
        }

        if ($type === 'update') {
            $user->tokens()->update([
                'name' => 'API Refresh Token',
                'token' => hash('sha256', $refreshToken),
                'refresh_token_expires_at' => now()->addDays(30),
            ]);
        }

        return [$token, $refreshToken];
    }

    private function getTokenFromRequest($request, $tokenType = 'access')
    {
        $token = '';

        if ($tokenType === 'access') {
            $token = explode('|', $request->access_token, 2)[1] ?? $request->access_token;
        }

        if ($tokenType === 'refresh') {
            $token = $request->refresh_token;
        }

        return hash('sha256', $token);
    }

    private function getUserBasedOffAccessTokenAndRefreshToken($request)
    {
        $request->validated($request->only(['access_token', 'refresh_token']));

        if (!$request->filled('access_token') || !$request->filled('refresh_token')) {
            return $this->error('No token exists.', 400);
        }

        $user = PersonalAccessToken::where('token', $this->getTokenFromRequest($request))->first();

        if (!$user) {
            return $this->error('Invalid request', 400);
        }

        $user = $user->tokenable;

        return $user;
    }

    public function setAuthenticatedUser($authenticatedUser)
    {
        $this->authenticatedUser = $authenticatedUser;
    }

    public function getAuthenticatedUser()
    {
        return $this->authenticatedUser;
    }

    public function register()
    {
        $this->registerUserRequest->validated($this->registerUserRequest->only(['name', 'email', 'password', 'password_confirmation']));

        $user = User::create([
            'name' => $this->registerUserRequest->name,
            'email' => $this->registerUserRequest->email,
            'password' => Hash::make($this->registerUserRequest->password),
            'role' => 'user',
        ]);

        $createdTokens = $this->createOrUpdateTokens($user);

        $this->setAuthenticatedUser($user);

        return $this->ok(
            'User registered successfully',
            [
                'access_token' => $createdTokens[0],
                'refresh_token' => $createdTokens[1],
            ],
            201
        );
    }

    public function login()
    {
        $this->loginUserRequest->validated($this->loginUserRequest->only(['email', 'password']));

        $user = User::where('email', $this->loginUserRequest->email)->first();

        if (!$user || !Hash::check($this->loginUserRequest->password, $user->password)) {
            return $this->error('Invalid credentials', 401);
        }

        $user = User::firstWhere('email', $this->loginUserRequest->email);

        $createdTokens = $this->createOrUpdateTokens($user);

        $this->setAuthenticatedUser($user);

        return $this->ok(
            'Authenticated',
            [
                'access_token' => $createdTokens[0],
                'refresh_token' => $createdTokens[1],
            ],
            200
        );
    }

    public function logout()
    {
        $user = $this->getUserBasedOffAccessTokenAndRefreshToken($this->logoutUserRequest);

        foreach ($user->tokens as $token) {
            if ($this->getTokenFromRequest($this->logoutUserRequest) === $token->token) {
                $token->delete();
            }

            if ($this->getTokenFromRequest($this->logoutUserRequest, 'refresh') === $token->token) {
                $token->delete();
            }
        }

        $this->setAuthenticatedUser(null);

        return $this->ok('User logged out Successfully.');
    }

    public function refreshToken()
    {
        $user = $this->getUserBasedOffAccessTokenAndRefreshToken($this->refreshTokenRequest);

        $refreshToken = $user->tokens->where('token', $this->getTokenFromRequest($this->refreshTokenRequest, 'refresh'))->first();

        if (!$refreshToken) {
            return $this->error('Invalid refresh token', 400);
        }

        if ($refreshToken->refresh_token_expires_at < now()) {
            return $this->error('Refresh token expired', 400);
        }

        $user->tokens->where('token', hash('sha256', $this->getTokenFromRequest($this->refreshTokenRequest)))->first()->delete();

        $createdTokens = $this->createOrUpdateTokens($user);

        $this->setAuthenticatedUser($user);

        return $this->ok(
            'New tokens have been generated.',
            [
                'access_token' => $createdTokens[0],
                'refresh_token' => $createdTokens[1],
            ],
            201
        );
    }
}
