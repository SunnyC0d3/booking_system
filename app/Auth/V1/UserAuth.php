<?php

namespace App\Auth\V1;

use App\Models\User;
use Illuminate\Http\Request;
use App\Permissions\V1\Abilities;
use App\Traits\V1\ApiResponses;
use Illuminate\Support\Facades\Auth;
use App\Policies\V1\UserPolicy;
use Illuminate\Support\Facades\Hash;
use Laravel\Passport\Token;
use Laravel\Passport\Client;
use Laravel\Passport\RefreshToken;
use \GuzzleHttp\Client as GuzzleClient;
use \Exception;

final class UserAuth
{
    use ApiResponses;

    private $authenticatedUser = null;
    private $userPolicy;
    private $accessToken;
    private $refreshToken;

    public function __construct()
    {
        $this->userPolicy = new UserPolicy();
    }

    private function validateClientFromToken($accessToken)
    {
        $token = Token::where('id', $accessToken)->first();

        if (!$token) {
            throw new Exception('Invalid token', 401);
        }

        $client = Client::find($token->client_id);

        if (!$client) {
            throw new Exception('Invalid client', 401);
        }

        return $this->ok(
            'Client validated successfully',
            [
                'client_id' => $client->id,
                'client_secret' => $client->secret,
                'client_name' => $client->name,
            ]
        );
    }

    public function setAuthenticatedUser($authenticatedUser)
    {
        $this->authenticatedUser = $authenticatedUser;
    }

    public function getAuthenticatedUser()
    {
        if ($this->authenticatedUser !== null) {
            return $this->authenticatedUser;
        }

        throw new Exception('Not authorised', 400);
    }

    public function register(Request $request)
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'user'
        ]);

        $this->setAuthenticatedUser($user);

        return $this->ok(
            'User registered successfully',
            []
        );
    }

    public function login(Request $request)
    {
        $client = $this->validateClientFromToken($request->bearerToken());

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw new Exception('Invalid credentials', 400);
        }

        $http = new GuzzleClient();

        $response = $http->post(env('APP_URL') . '/oauth/token', [
            'form_params' => [
                'grant_type' => 'password',
                'client_id' => $client['client_id'],
                'client_secret' => $client['client_secret'],
                'username' => $request->email,
                'password' => $request->password,
                'scope' => Abilities::getAbilities($user),
            ],
        ]);

        $this->setAuthenticatedUser($user);

        return $this->ok(
            'Authenticated',
            json_decode($response->getBody(), true)
        );
    }

    public function logout()
    {
        $user = Auth::user();

        $accessToken = $user->token();

        if ($accessToken) {
            $accessToken->revoke();
            RefreshToken::where('access_token_id', $accessToken->id)->update(['revoked' => true]);
        }

        $this->setAuthenticatedUser(null);

        return $this->ok('User logged out Successfully.');
    }

    public function refreshToken(Request $request)
    {
        $client = $this->validateClientFromToken($request->bearerToken());

        $refreshTokenRecord = RefreshToken::where('id', $request->refresh_token)->first();

        if (!$refreshTokenRecord) {
            throw new Exception('Invalid refresh token', 400);
        }

        $accessTokenRecord = Token::where('id', $refreshTokenRecord->access_token_id)->first();

        if (!$accessTokenRecord) {
            throw new Exception('No access token found for this refresh token', 404);
        }

        $user = User::findOrFail($accessTokenRecord->user_id);

        $http = new GuzzleClient();

        $response = $http->post(env('APP_URL') . '/oauth/token', [
            'form_params' => [
                'grant_type' => 'refresh_token',
                'refresh_token' => $request->refresh_token,
                'client_id' => $client['client_id'],
                'client_secret' => $client['client_secret'],
                'scope' => Abilities::getAbilities($user),
            ],
        ]);

        return $this->ok(
            'New tokens have been generated.',
            json_decode($response->getBody(), true)
        );
    }

    public function hasPermission()
    {
        if ($this->getAuthenticatedUser()) {
            return $this->userPolicy->can($this->getAuthenticatedUser());
        }

        return false;
    }

    public function checkRole(array $roles)
    {
        if ($this->getAuthenticatedUser()) {
            return in_array($this->getAuthenticatedUser()->role, $roles) ?? false;
        }
    }
}
