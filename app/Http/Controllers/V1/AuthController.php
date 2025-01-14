<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Auth\V1\UserAuth;
use App\Requests\V1\LoginUserRequest;
use App\Requests\V1\RegisterUserRequest;
use App\Requests\V1\ForgotPasswordRequest;
use App\Requests\V1\PasswordResetRequest;
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

    /**
     * Register a new user and return their API token.
     * 
     * This endpoint is used to register a new user, including their name, email, password, 
     * and password confirmation.
     * 
     * @group Authentication
     * 
     * @header X-Hmac HMAC signature of the request payload done via sha256.
     * @header X-Timestamp Timestamp + Request body.
     * 
     * @bodyParam name string required The user's full name. Example: John Doe
     * @bodyParam email string required The user's email address. Example: john.doe@example.com
     * @bodyParam password string required The user's password. Must be at least 8 characters. Example: password123
     * @bodyParam password_confirmation string required Must match the password field. Example: password123
     * 
     * @response 200 {
     *   "data": [],
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
     * Login a user.
     *
     * Authenticates a user with email and password. Returns an API token with expiry timer.
     *
     * @group Authentication
     * 
     * @header X-Hmac HMAC signature of the request payload done via sha256.
     * @header X-Timestamp Timestamp + Request body.
     *
     * @bodyParam email string required The user's email. Example: john.doe@example.com
     * @bodyParam password string required The user's password. Example: password123
     * @bodyParam remember boolean optional Whether to remember the user. Example: true
     *
     * @response 200 {
     *   "data": {
     *       "token_type": "Bearer,
     *       "token": "{YOUR_AUTH_KEY}",
     *       "expires_in": "{YOUR_EXPIRY_TIMER}"
     *   },
     *   "message": "Authenticated",
     *   "status": 200
     * }
     * @response 401 {
     *   "message": "Invalid credentials",
     *   "status": 401
     * }
     */
    public function login(LoginUserRequest $request)
    {
        $request->validated($request->only(['email', 'password', 'remember']));

        try {
            return $this->userAuth->login($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Logout a user.
     *
     * Logs out the currently authenticated user by invalidating their API token.
     *
     * @group Authentication
     * @authenticated
     * 
     * @header Authorization Bearer token required.
     *
     * @response 200 {
     *   "message": "User logged out successfully",
     *   "status": 200
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
     * Request a password reset link.
     *
     * Sends a password reset email to the specified email address.
     *
     * @group Password Reset
     * 
     * @header X-Hmac HMAC signature of the request payload done via sha256.
     * @header X-Timestamp Timestamp + Request body.
     *
     * @bodyParam email string required The user's email address. Example: john.doe@example.com
     *
     * @response 200 {
     *   "message": "Password reset link sent.",
     *   "status": 200
     * }
     * 
     * @response 400 {
     *   "message": {ERROR_MESSAGE},
     *   "status": 400
     * }
     */
    public function forgotPassword(ForgotPasswordRequest $request)
    {
        $request->validated($request->only(['email']));

        try {
            return $this->userAuth->forgotPassword($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Reset a user's password.
     *
     * Resets the user's password using the provided reset token.
     *
     * @group Password Reset
     * 
     * @header X-Hmac HMAC signature of the request payload done via sha256.
     * @header X-Timestamp Timestamp + Request body.
     *
     * @bodyParam token string required The password reset token. Example: abc123
     * @bodyParam email string required The user's email address. Example: john.doe@example.com
     * @bodyParam password string required The new password. Example: newpassword123
     * @bodyParam password_confirmation string required Must match the password. Example: newpassword123
     *
     * @response 200 {
     *   "message": "Password has been reset.",
     *   "status": 200
     * }
     * @response 400 {
     *   "message": {ERROR_MESSAGE},
     *   "status": 400
     * }
     */
    public function passwordReset(PasswordResetRequest $request)
    {
        $request->validated($request->only(['token', 'email', 'password', 'password_confirmation']));

        try {
            return $this->userAuth->passwordReset($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }
}
