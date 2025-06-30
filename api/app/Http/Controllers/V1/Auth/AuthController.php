<?php

namespace App\Http\Controllers\V1\Auth;

use App\Http\Controllers\Controller;
use App\Services\V1\Auth\UserAuth;
use App\Services\V1\Auth\AccountLock;
use App\Services\V1\Auth\PasswordValidation;
use App\Requests\V1\LoginUserRequest;
use App\Requests\V1\RegisterUserRequest;
use App\Requests\V1\ForgotPasswordRequest;
use App\Requests\V1\PasswordResetRequest;
use App\Requests\V1\ChangePasswordRequest;
use App\Services\V1\Logger\SecurityLog;
use App\Traits\V1\ApiResponses;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use \Exception;

class AuthController extends Controller
{
    use ApiResponses;

    protected UserAuth $userAuth;
    protected SecurityLog $securityLogger;
    protected AccountLock $accountLock;
    protected PasswordValidation $passwordValidator;

    public function __construct(
        UserAuth $userAuth,
        SecurityLog $securityLogger,
        AccountLock $accountLock,
        PasswordValidation $passwordValidator
    ) {
        $this->userAuth = $userAuth;
        $this->securityLogger = $securityLogger;
        $this->accountLock = $accountLock;
        $this->passwordValidator = $passwordValidator;
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
        $request->validated($request->only(['name', 'email', 'password', 'password_confirmation']));

        try {
            $user = User::where('email', $request->email)->first();

            if ($user && $this->accountLock->isAccountLocked($user)) {
                $timeUntilUnlock = $this->accountLock->getTimeUntilUnlock($user);

                $this->securityLogger->logAuthEvent('registration_blocked_locked_account', $request, [
                    'email' => $request->email,
                    'time_until_unlock' => $timeUntilUnlock
                ]);

                return $this->error([
                    'message' => 'Registration temporarily blocked. Please try again later.',
                    'time_remaining_minutes' => ceil($timeUntilUnlock / 60)
                ], 423);
            }

            $passwordValidation = $this->passwordValidator->validate($request->password);
            if (!$passwordValidation) {
                $this->securityLogger->logAuthEvent('registration_failed_weak_password', $request, [
                    'email' => $request->email,
                    'password_errors' => $this->passwordValidator->getErrors()
                ]);

                return $this->error([
                    'message' => 'Password does not meet security requirements.',
                    'errors' => $this->passwordValidator->getErrors(),
                    'strength' => $this->passwordValidator->calculatePasswordStrength($request->password)
                ], 422);
            }

            $result = $this->userAuth->register($request);

            $this->securityLogger->logAuthEvent('registration_success', $request, [
                'user_email' => $request->email
            ]);

            return $result;
        } catch (Exception $e) {
            $this->securityLogger->logAuthEvent('registration_failed', $request, [
                'error' => $e->getMessage(),
                'email' => $request->email
            ]);

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
        $request->validated($request->only(['email', 'password', 'remember']));

        try {
            $user = User::where('email', $request->email)->first();

            if (!$user) {
                $this->securityLogger->logAuthEvent('login_failed_user_not_found', $request, [
                    'email' => $request->email
                ]);

                return $this->error('Invalid credentials', 401);
            }

            if ($this->accountLock->isAccountLocked($user)) {
                $lockInfo = $this->accountLock->getAccountLockInfo($user);

                $this->securityLogger->logAuthEvent('login_blocked_account_locked', $request, [
                    'user_email' => $request->email,
                    'lockout_count' => $lockInfo['lockout_count'],
                    'time_until_unlock' => $lockInfo['time_until_unlock']
                ]);

                return $this->error([
                    'message' => 'Account is temporarily locked due to multiple failed login attempts.',
                    'locked_until' => $lockInfo['locked_until'],
                    'time_remaining_seconds' => $lockInfo['time_until_unlock'],
                    'time_remaining_minutes' => ceil($lockInfo['time_until_unlock'] / 60),
                    'lockout_count' => $lockInfo['lockout_count'],
                ], 423);
            }

            if (!Hash::check($request->password, $user->password)) {
                $this->accountLock->recordFailedAttempt($user, [
                    'reason' => 'invalid_password',
                    'email_attempted' => $request->email
                ]);

                $lockInfo = $this->accountLock->getAccountLockInfo($user);
                $remainingAttempts = max(0, 5 - $lockInfo['failed_attempts']);

                $this->securityLogger->logAuthEvent('login_failed_invalid_password', $request, [
                    'user_email' => $request->email,
                    'failed_attempts' => $lockInfo['failed_attempts'],
                    'remaining_attempts' => $remainingAttempts
                ]);

                $errorData = ['message' => 'Invalid credentials'];

                if ($remainingAttempts <= 2 && $remainingAttempts > 0) {
                    $errorData['warning'] = "You have {$remainingAttempts} login attempt(s) remaining before your account is locked.";
                } elseif ($remainingAttempts === 0) {
                    $errorData['message'] = 'Account has been temporarily locked due to multiple failed login attempts.';
                    $errorData['locked_until'] = $lockInfo['locked_until'];
                }

                return $this->error($errorData, 401);
            }

//            if ($user->requiresPasswordChange()) {
//                $this->securityLogger->logAuthEvent('login_requires_password_change', $request, [
//                    'user_email' => $request->email,
//                    'password_expired' => true
//                ]);
//
//                return $this->error([
//                    'message' => 'Your password has expired and must be changed before logging in.',
//                    'password_expired' => true,
//                    'change_password_required' => true
//                ], 426);
//            }

            $result = $this->userAuth->login($request);

            $user->recordSuccessfulLogin();

            $securityScore = $user->getSecurityScore();
            if ($securityScore['score'] < 60) {
                $responseData = $result->getData(true);
                $responseData['security_warning'] = [
                    'message' => 'Your account security score is low. Please review your security settings.',
                    'score' => $securityScore['score'],
                    'issues' => $securityScore['issues']
                ];
                $result->setData($responseData);
            }

            $this->securityLogger->logAuthEvent('login_success', $request, [
                'user_email' => $request->email,
                'security_score' => $securityScore['score']
            ]);

            return $result;
        } catch (Exception $e) {
            if (isset($user)) {
                $this->accountLock->recordFailedAttempt($user, [
                    'reason' => 'system_error',
                    'error_message' => $e->getMessage()
                ]);
            }

            $this->securityLogger->logAuthEvent('login_failed_system_error', $request, [
                'error' => $e->getMessage(),
                'email' => $request->email
            ]);

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
    public function logout()
    {
        try {
            $user = auth()->user();

            if ($user) {
                $this->securityLogger->logAuthEvent('logout_success', request(), [
                    'user_email' => $user->email
                ]);
            }

            return $this->userAuth->logout();
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
            $user = auth()->user();

            if (!Hash::check($request->current_password, $user->password)) {
                $this->accountLock->recordFailedAttempt($user, [
                    'reason' => 'invalid_current_password_change_attempt'
                ]);

                $this->securityLogger->logAuthEvent('password_change_failed_invalid_current', $request, [
                    'user_email' => $user->email
                ]);

                return $this->error('Current password is incorrect', 401);
            }

            $passwordValidation = $this->passwordValidator->validate($request->new_password, $user);
            if (!$passwordValidation) {
                $this->securityLogger->logAuthEvent('password_change_failed_weak_password', $request, [
                    'user_email' => $user->email,
                    'password_errors' => $this->passwordValidator->getErrors()
                ]);

                return $this->error([
                    'message' => 'New password does not meet security requirements.',
                    'errors' => $this->passwordValidator->getErrors(),
                    'strength' => $this->passwordValidator->calculatePasswordStrength($request->new_password)
                ], 422);
            }

            $this->passwordValidator->savePasswordToHistory($user, $user->password);

            $user->update([
                'password' => Hash::make($request->new_password),
                'password_changed_at' => now(),
            ]);

            $this->securityLogger->logAuthEvent('password_change_success', $request, [
                'user_email' => $user->email
            ]);

            return $this->ok('Password changed successfully', [
                'password_strength' => $this->passwordValidator->calculatePasswordStrength($request->new_password),
                'security_score' => $user->fresh()->getSecurityScore()
            ]);

        } catch (Exception $e) {
            $this->securityLogger->logAuthEvent('password_change_failed_system_error', $request, [
                'error' => $e->getMessage(),
                'user_email' => auth()->user()->email ?? 'unknown'
            ]);

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
        $request->validated($request->only(['email']));

        try {
            $user = User::where('email', $request->email)->first();

            if ($user && $this->accountLock->isAccountLocked($user)) {
                $this->securityLogger->logAuthEvent('password_reset_blocked_locked_account', $request, [
                    'email' => $request->email
                ]);

                return $this->error('Password reset temporarily blocked. Please try again later.', 423);
            }

            $result = $this->userAuth->forgotPassword($request);

            $this->securityLogger->logAuthEvent('password_reset_requested', $request, [
                'email' => $request->email
            ]);

            return $result;
        } catch (Exception $e) {
            $this->securityLogger->logAuthEvent('password_reset_failed', $request, [
                'error' => $e->getMessage(),
                'email' => $request->email
            ]);

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
        $request->validated($request->only(['token', 'email', 'password', 'password_confirmation']));

        try {
            $user = User::where('email', $request->email)->first();

            if ($user) {
                $passwordValidation = $this->passwordValidator->validate($request->password, $user);
                if (!$passwordValidation) {
                    $this->securityLogger->logAuthEvent('password_reset_failed_weak_password', $request, [
                        'email' => $request->email,
                        'password_errors' => $this->passwordValidator->getErrors()
                    ]);

                    return $this->error([
                        'message' => 'New password does not meet security requirements.',
                        'errors' => $this->passwordValidator->getErrors(),
                        'strength' => $this->passwordValidator->calculatePasswordStrength($request->password)
                    ], 422);
                }
            }

            $result = $this->userAuth->passwordReset($request);

            if ($user) {
                $this->passwordValidator->savePasswordToHistory($user, $user->password);
                $user->update(['password_changed_at' => now()]);

                $this->accountLock->unlockAccount($user);
            }

            $this->securityLogger->logAuthEvent('password_reset_success', $request, [
                'email' => $request->email
            ]);

            return $result;
        } catch (Exception $e) {
            $this->securityLogger->logAuthEvent('password_reset_failed', $request, [
                'error' => $e->getMessage(),
                'email' => $request->email
            ]);

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
            $user = auth()->user();
            $lockInfo = $this->accountLock->getAccountLockInfo($user);
            $securityScore = $user->getSecurityScore();

            return $this->ok('Security information retrieved', [
                'account_lock' => $lockInfo,
                'security_score' => $securityScore,
                'password_expiry' => [
                    'requires_change' => $user->requiresPasswordChange(),
                    'days_until_expiry' => $user->getDaysUntilPasswordExpiry(),
                    'last_changed' => $user->password_changed_at,
                ],
                'login_history' => [
                    'last_login' => $user->last_login_at,
                    'last_login_ip' => $user->last_login_ip,
                ],
            ]);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }
}
