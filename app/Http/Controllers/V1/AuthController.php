<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Auth\V1\UserAuth;
use App\Requests\V1\ClientTokenRequest;
use App\Requests\V1\LoginUserRequest;
use App\Requests\V1\RegisterUserRequest;
use App\Requests\V1\RefreshTokenRequest;
use App\Traits\V1\ApiResponses;
use \Exception;

class AuthController extends Controller
{
    use ApiResponses;

    protected $userAuth;

    public function __construct(UserAuth $userAuth)
    {
        $this->userAuth = $userAuth;
    }

    public function clientToken(ClientTokenRequest $request)
    {
        $request->validated($request->only(['client_id', 'client_secret', 'scope', 'grant_type']));

        try {
            return $this->userAuth->clientToken($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Register a new user and return their API token.
     * 
     * This endpoint is used to register a new user, including their name, email, password, 
     * and password confirmation. A new API token and refresh token will be issued.
     * 
     * Works with **client-side** authentication only. Requires **"client:only"** ability.
     * 
     * @authentication
     * @group Endpoints
     * @subgroup Authentication
     * 
     * @bodyParam name string required The user's full name. Example: John Doe
     * @bodyParam email string required The user's email address. Example: john.doe@example.com
     * @bodyParam password string required The user's password. Must be at least 8 characters. Example: password123
     * @bodyParam password_confirmation string required Must match the password field. Example: password123
     * 
     * @response 200 {
     *   "data": {
     *       "token": "{YOUR_AUTH_KEY}",
     *       "refresh_token": "{YOUR_REFRESH_KEY}"
     *   },
     *   "message": "User registered successfully",
     *   "status": 200
     * }
     */
    public function register(RegisterUserRequest $request)
    {
        $request->validated($request->only(['name', 'email', 'password', 'password_confirmation']));

        try {
            return $this->userAuth->register($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Authenticate the user and return their API token.
     * 
     * This endpoint is used to authenticate a user with their email and password. If the
     * credentials are valid, an API token and refresh token will be issued.
     * 
     * Works with **client-side** authentication only. Requires **"client:only"** ability.
     * 
     * @authentication
     * @group Endpoints
     * @subgroup Authentication
     * @response 200 {
     * "data": {
     *       "token": "{YOUR_AUTH_KEY}",
     *       "refresh_token": "{YOUR_REFRESH_KEY}"
     * },
     *      "message": "Authenticated",
     *      "status": 200
     * }
     * @response 401 {
     *      "message": "Invalid credentials",
     *      "status": 401
     * }
     */
    public function login(LoginUserRequest $request)
    {
        $request->validated($request->only(['client_id', 'client_secret', 'scope', 'grant_type', 'email', 'password']));

        try {
            return $this->userAuth->login($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Logout the user and destroy their API token.
     * 
     * This endpoint allows the user to log out by deleting their current access token
     * and refresh token from the database.
     * 
     * Works with **client-side** authentication only. Requires **"client:only"** ability.
     * 
     * @authentication
     * @group Endpoints
     * @subgroup Authentication
     * @response 200 {}
     * @response 400 {
     *      "message": "No token exists.",
     *      "status": 400
     * }
     */
    public function logout()
    {
        try {
            return $this->userAuth->logout();
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Refresh the user's API token and generate a new refresh token.
     * 
     * This endpoint is used to refresh the user's API access token by validating their
     * refresh token and issuing new tokens if valid.
     * 
     * Works with **client-side** authentication only. Requires **"client:only"** ability.
     * 
     * @authentication
     * @group Endpoints
     * @subgroup Authentication
     * @response 200 {
     * "data": {
     *       "token": "{YOUR_NEW_AUTH_KEY}",
     *       "refresh_token": "{YOUR_NEW_REFRESH_KEY}"
     * },
     *      "message": "New tokens have been generated.",
     *      "status": 200
     * }
     * @response 400 {
     *      "message": "Refresh token expired",
     *      "status": 400
     * }
     * @response 400 {
     *      "message": "Invalid refresh token",
     *      "status": 400
     * }
     */
    public function refreshToken(RefreshTokenRequest $request)
    {
        $request->validated($request->only(['client_id', 'client_secret', 'scope', 'grant_type', 'refresh_token']));

        try {
            return $this->userAuth->refreshToken($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }
}
