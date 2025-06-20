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

            if ($user->requiresPasswordChange()) {
                $this->securityLogger->logAuthEvent('login_requires_password_change', $request, [
                    'user_email' => $request->email,
                    'password_expired' => true
                ]);

                return $this->error([
                    'message' => 'Your password has expired and must be changed before logging in.',
                    'password_expired' => true,
                    'change_password_required' => true
                ], 426);
            }

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
