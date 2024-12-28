<?php

namespace App\Auth\V1;

use App\Models\User;
use Illuminate\Http\Request;
use App\Traits\V1\ApiResponses;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Passport\TokenRepository;
use Laravel\Passport\RefreshTokenRepository;
use \GuzzleHttp\Client;
use \Exception;

final class UserAuth
{
    use ApiResponses;

    private $clientConfig = [
        'grant_type' => '',
        'client_id' => '',
        'client_secret' => '',
        'scope' => '',
        'refresh_token' => '',
        'username' => '',
        'password' => ''
    ];

    private function generateToken(array $config)
    {
        $matchedKeys = array_intersect_key($config, $this->clientConfig);

        if (empty($matchedKeys)) {
            throw new Exception('No valid keys found in the configuration', 400);
        }

        $this->clientConfig = array_merge($this->clientConfig, $matchedKeys);

        $http = new Client();

        $response = $http->post(env('APP_URL') . env('OAUTH_PATH'), [
            'form_params' => $this->clientConfig
        ]);

        return $this->ok(
            'Generated Token Successfully',
            json_decode((string) $response->getBody(), true)
        );
    }

    public function clientToken(Request $request)
    {
        $config = [
            'grant_type' => $request->grant_type,
            'client_id' => $request->client_id,
            'client_secret' => $request->client_secret,
            'scope' => $request->scope
        ];

        return $this->generateToken($config);
    }

    public function register(Request $request)
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'user'
        ]);

        return $this->ok(
            'User registered successfully',
            []
        );
    }

    public function login(Request $request)
    {
        $config = [
            'grant_type' => $request->grant_type,
            'username' => $request->username,
            'password' => $request->password,
            'client_id' => $request->client_id,
            'client_secret' => $request->client_secret,
            'scope' => $request->scope
        ];

        return $this->generateToken($config);
    }

    public function refreshToken(Request $request)
    {
        $config = [
            'grant_type' => $request->grant_type,
            'refresh_token' => $request->refresh_token,
            'client_id' => $request->client_id,
            'client_secret' => $request->client_secret,
            'scope' => $request->scope
        ];

        return $this->generateToken($config);
    }

    public function logout()
    {
        $user = Auth::user();

        if ($user && $user->token()) {
            $tokenId = $user->token()->id;

            $tokenRepository = app(TokenRepository::class);
            $tokenRepository->revokeAccessToken($tokenId);

            $refreshTokenRepository = app(RefreshTokenRepository::class);
            $refreshTokenRepository->revokeRefreshTokensByAccessTokenId($tokenId);
        }

        return $this->ok('User logged out Successfully.');
    }

    public function hasPermission(string $scope)
    {
        $user = Auth::user();

        if ($user) {
            return $user->tokenCan($scope);
        }

        return false;
    }

    public function checkRole(array $roles)
    {
        $user = Auth::user();

        if ($user) {
            return in_array($user->role, $roles) ?? false;
        }
    }
}
