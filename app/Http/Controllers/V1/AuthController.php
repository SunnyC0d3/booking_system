<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Auth\V1\UserAuth;

class AuthController extends Controller
{
    protected $userAuth;

    public function __construct(UserAuth $userAuth)
    {
        $this->userAuth = $userAuth;
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
     * @response 201 {
     *   "data": {
     *       "token": "{YOUR_AUTH_KEY}",
     *       "refresh_token": "{YOUR_REFRESH_KEY}"
     *   },
     *   "message": "User registered successfully",
     *   "status": 201
     * }
     */
    public function register()
    {
        return $this->userAuth->register();
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
    public function login()
    {
        return $this->userAuth->login();
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
        return $this->userAuth->logout();
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
     * @response 201 {
     * "data": {
     *       "token": "{YOUR_NEW_AUTH_KEY}",
     *       "refresh_token": "{YOUR_NEW_REFRESH_KEY}"
     * },
     *      "message": "New tokens have been generated.",
     *      "status": 201
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
    public function refreshToken()
    {
        return $this->userAuth->refreshToken();
    }
}
