<?php

namespace App\Http\Controllers\V1\Auth;

use App\Http\Controllers\Controller;
use App\Services\V1\Auth\UserAuth;
use App\Requests\V1\LoginUserRequest;
use App\Requests\V1\RegisterUserRequest;
use App\Requests\V1\ForgotPasswordRequest;
use App\Requests\V1\PasswordResetRequest;
use App\Requests\V1\ChangePasswordRequest;
use App\Traits\V1\ApiResponses;
use Illuminate\Http\Request;
use \Exception;

class AuthController extends Controller
{
    use ApiResponses;

    protected UserAuth $userAuth;

    public function __construct(UserAuth $userAuth)
    {
        $this->userAuth = $userAuth;
    }

    /**
     * Register a new user
     *
     * Creates a new user account with email verification. The user will receive an email to verify their account.
     * Passwords must meet security requirements including minimum length, character diversity, and cannot be common passwords.
     *
     * @group Authentication
     * @unauthenticated
     *
     * @bodyParam name string required The user's full name. Example: John Doe
     * @bodyParam email string required The user's email address. Must be unique. Example: john@example.com
     * @bodyParam password string required The user's password. Must be at least 12 characters with uppercase, lowercase, numbers, and symbols. Example: MySecurePass123!
     * @bodyParam password_confirmation string required Password confirmation. Must match the password field. Example: MySecurePass123!
     *
     * @response 200 {
     *   "data": [],
     *   "message": "User registered successfully.",
     *   "status": 200
     * }
     *
     * @response 422 {
     *   "message": "Password does not meet security requirements.",
     *   "errors": [
     *     "Password must contain at least 1 uppercase letter(s).",
     *     "Password cannot contain common words or patterns that are not secure."
     *   ],
     *   "strength": {
     *     "score": 45,
     *     "strength": "medium",
     *     "feedback": ["Add special characters", "Use more unique characters"]
     *   }
     * }
     *
     * @response 423 {
     *   "message": "Registration temporarily blocked. Please try again later.",
     *   "time_remaining_minutes": 15
     * }
     */
    public function register(RegisterUserRequest $request)
    {
        try {
            return $this->userAuth->register($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * User login
     *
     * Authenticates a user and returns an access token. Implements account lockout protection and tracks failed attempts.
     *
     * @group Authentication
     * @unauthenticated
     *
     * @bodyParam email string required The user's email address. Example: john@example.com
     * @bodyParam password string required The user's password. Example: MySecurePass123!
     * @bodyParam remember boolean optional Whether to remember the user for extended period. Example: true
     *
     * @response 200 {
     *   "data": {
     *     "token_type": "Bearer",
     *     "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...",
     *     "expires_at": 1640995200,
     *     "user": {
     *       "id": 1,
     *       "email": "john@example.com",
     *       "role": "user"
     *     }
     *   },
     *   "message": "User logged in successfully.",
     *   "status": 200
     * }
     *
     * @response 401 {
     *   "message": "Invalid credentials"
     * }
     *
     * @response 423 {
     *   "message": "Account is temporarily locked due to multiple failed login attempts.",
     *   "locked_until": "2024-01-15T15:30:00.000000Z",
     *   "time_remaining_seconds": 1800,
     *   "time_remaining_minutes": 30,
     *   "lockout_count": 2
     * }
     *
     * @response 426 {
     *   "message": "Your password has expired and must be changed before logging in.",
     *   "password_expired": true,
     *   "change_password_required": true
     * }
     */
    public function login(LoginUserRequest $request)
    {
        try {
            return $this->userAuth->login($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * User logout
     *
     * Logs out the authenticated user and revokes their access token.
     *
     * @group Authentication
     * @authenticated
     *
     * @response 200 {
     *   "data": [],
     *   "message": "User logged out successfully",
     *   "status": 200
     * }
     */
    public function logout(Request $request)
    {
        try {
            return $this->userAuth->logout($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Change password
     *
     * Changes the authenticated user's password. Requires current password verification and validates new password strength.
     *
     * @group Authentication
     * @authenticated
     *
     * @bodyParam current_password string required The user's current password. Example: MyOldPass123!
     * @bodyParam new_password string required The new password. Must meet security requirements. Example: MyNewSecurePass456!
     * @bodyParam new_password_confirmation string required Confirmation of the new password. Example: MyNewSecurePass456!
     *
     * @response 200 {
     *   "data": {
     *     "password_strength": {
     *       "score": 85,
     *       "strength": "very strong",
     *       "feedback": []
     *     },
     *     "security_score": {
     *       "score": 95,
     *       "level": "excellent",
     *       "issues": []
     *     }
     *   },
     *   "message": "Password changed successfully",
     *   "status": 200
     * }
     *
     * @response 401 {
     *   "message": "Current password is incorrect"
     * }
     *
     * @response 422 {
     *   "message": "New password does not meet security requirements.",
     *   "errors": [
     *     "Password cannot be the same as your last 5 passwords."
     *   ],
     *   "strength": {
     *     "score": 45,
     *     "strength": "medium",
     *     "feedback": ["Add special characters"]
     *   }
     * }
     */
    public function changePassword(ChangePasswordRequest $request)
    {
        try {
            return $this->userAuth->changePassword($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Forgot password
     *
     * Sends a password reset link to the user's email address.
     *
     * @group Authentication
     * @unauthenticated
     *
     * @bodyParam email string required The user's email address. Example: john@example.com
     *
     * @response 200 {
     *   "data": [],
     *   "message": "We have emailed your password reset link!",
     *   "status": 200
     * }
     *
     * @response 423 {
     *   "message": "Password reset temporarily blocked. Please try again later."
     * }
     */
    public function forgotPassword(ForgotPasswordRequest $request)
    {
        try {
            return $this->userAuth->forgotPassword($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Reset password
     *
     * Resets the user's password using a valid reset token from the forgot password email.
     *
     * @group Authentication
     * @unauthenticated
     *
     * @bodyParam token string required The password reset token from the email. Example: abc123def456
     * @bodyParam email string required The user's email address. Example: john@example.com
     * @bodyParam password string required The new password. Must meet security requirements. Example: MyNewSecurePass456!
     * @bodyParam password_confirmation string required Confirmation of the new password. Example: MyNewSecurePass456!
     *
     * @response 200 {
     *   "data": [],
     *   "message": "Your password has been reset!",
     *   "status": 200
     * }
     *
     * @response 422 {
     *   "message": "New password does not meet security requirements.",
     *   "errors": [
     *     "Password must contain at least 1 special character(s)."
     *   ],
     *   "strength": {
     *     "score": 60,
     *     "strength": "strong",
     *     "feedback": ["Add special characters"]
     *   }
     * }
     */
    public function passwordReset(PasswordResetRequest $request)
    {
        try {
            return $this->userAuth->passwordReset($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Get security information
     *
     * Retrieves comprehensive security information for the authenticated user including account lock status, security score, and password expiry details.
     *
     * @group Authentication
     * @authenticated
     *
     * @response 200 {
     *   "data": {
     *     "account_lock": {
     *       "is_locked": false,
     *       "failed_attempts": 0,
     *       "max_attempts": 5,
     *       "lockout_count": 0,
     *       "locked_until": null,
     *       "time_until_unlock": null,
     *       "last_attempt_at": null,
     *       "last_successful_login": "2024-01-15T10:30:00.000000Z",
     *       "should_reset": false
     *     },
     *     "security_score": {
     *       "score": 85,
     *       "level": "good",
     *       "issues": ["Medium strength password"]
     *     },
     *     "password_expiry": {
     *       "requires_change": false,
     *       "days_until_expiry": 45,
     *       "last_changed": "2024-01-01T12:00:00.000000Z"
     *     },
     *     "login_history": {
     *       "last_login": "2024-01-15T10:30:00.000000Z",
     *       "last_login_ip": "192.168.1.100"
     *     }
     *   },
     *   "message": "Security information retrieved",
     *   "status": 200
     * }
     */
    public function getSecurityInfo(Request $request)
    {
        try {
            return $this->userAuth->getSecurityInfo($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }
}
