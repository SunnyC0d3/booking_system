<?php

namespace App\Services\V1\Auth;

use App\Models\Role;
use App\Models\User;
use App\Resources\V1\UserResource;
use App\Services\V1\Logger\SecurityLog;
use Illuminate\Http\Request;
use App\Traits\V1\ApiResponses;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Passport\Token;
use Laravel\Passport\TokenRepository;
use Illuminate\Support\Facades\Password;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Auth\PasswordBroker;
use \Exception;

final class UserAuth
{
    use ApiResponses;

    protected SecurityLog $securityLogger;
    protected AccountLock $accountLock;
    protected PasswordValidation $passwordValidator;

    public function __construct(
        SecurityLog $securityLogger,
        AccountLock $accountLock,
        PasswordValidation $passwordValidator
    ) {
        $this->securityLogger = $securityLogger;
        $this->accountLock = $accountLock;
        $this->passwordValidator = $passwordValidator;
    }

    public function register(Request $request)
    {
        $data = $this->validateRequest($request, ['name', 'email', 'password', 'password_confirmation']);

        $user = $this->findUserByEmailIfExists($data['email']);
        $this->checkAccountLockForAction($user, $request, 'registration');
        $this->validatePasswordStrength($data['password'], $request, 'registration');

        $user = $this->createUser($data);
        $user->sendEmailVerificationNotification();
        $user->load('role');

        $this->logSecurityEvent('registration_success', $request, ['user_email' => $data['email']]);

        return $this->buildSuccessResponse('User registered successfully.', [
            'user' => new UserResource($user)
        ]);
    }

    public function login(Request $request)
    {
        $data = $this->validateRequest($request, ['email', 'password', 'remember']);

        $user = $this->findUserByEmailOrFail($data['email'], $request);
        $this->checkAccountLockForAction($user, $request, 'login');
        $this->validateUserCredentials($user, $data['password'], $request);
        $this->checkPasswordExpiry($user, $request);

        $tokenData = $this->createUserToken($user);
        $this->recordSuccessfulLogin($user);
        $responseData = $this->buildLoginResponse($tokenData, $user);

        $this->logSecurityEvent('login_success', $request, [
            'user_email' => $data['email'],
            'security_score' => $user->getSecurityScore()['score']
        ]);

        return $this->buildSuccessResponse('User logged in successfully.', $responseData);
    }

    public function logout(Request $request)
    {
        $user = Auth::user();

        if ($this->hasValidToken($user)) {
            $this->revokeUserToken($user);
            $this->logSecurityEvent('logout_success', $request, ['user_email' => $user->email]);
        }

        return $this->buildSuccessResponse('User logged out successfully');
    }

    public function changePassword(Request $request)
    {
        $user = $this->getAuthenticatedUser();
        $this->validateCurrentPassword($user, $request->current_password, $request);
        $this->validatePasswordStrength($request->new_password, $request, 'password_change', $user);

        $this->updateUserPassword($user, $request->new_password);
        $this->logSecurityEvent('password_change_success', $request, ['user_email' => $user->email]);

        return $this->buildSuccessResponse('Password changed successfully', [
            'password_strength' => $this->passwordValidator->calculatePasswordStrength($request->new_password),
            'security_score' => $user->fresh()->getSecurityScore()
        ]);
    }

    public function forgotPassword(Request $request)
    {
        $data = $this->validateRequest($request, ['email']);

        $user = $this->findUserByEmailIfExists($data['email']);
        $this->checkAccountLockForAction($user, $request, 'password_reset');

        $status = $this->sendPasswordResetLink($data['email']);
        $this->logSecurityEvent('password_reset_requested', $request, ['email' => $data['email']]);

        return $this->buildSuccessResponse(__($status));
    }

    public function passwordReset(Request $request)
    {
        $data = $this->validateRequest($request, ['token', 'email', 'password', 'password_confirmation']);

        $user = $this->findUserByEmailIfExists($data['email']);
        if ($user) {
            $this->validatePasswordStrength($data['password'], $request, 'password_reset', $user);
        }

        $status = $this->resetUserPassword($data, $request);
        $this->logSecurityEvent('password_reset_success', $request, ['email' => $data['email']]);

        return $this->buildSuccessResponse(__($status));
    }

    public function getAuthenticatedUser(Request $request)
    {
        $user = $this->getAuthenticatedUserOrFail($request);
        $user->load(['role', 'userAddress', 'vendors']);

        return $this->buildSuccessResponse('User data retrieved successfully.', [
            'user' => new UserResource($user)
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = $this->getAuthenticatedUserOrFail($request);
        $user->update($request->only(['name', 'email']));
        $user->load(['role', 'userAddress', 'vendors']);

        return $this->buildSuccessResponse('Profile updated successfully.', [
            'user' => new UserResource($user)
        ]);
    }

    public function getSecurityInfo(Request $request)
    {
        $user = $this->getAuthenticatedUser();

        return $this->buildSuccessResponse('Security information retrieved', [
            'account_lock' => $this->accountLock->getAccountLockInfo($user),
            'security_score' => $user->getSecurityScore(),
            'password_expiry' => $this->getPasswordExpiryInfo($user),
            'login_history' => $this->getLoginHistoryInfo($user),
        ]);
    }

    private function validateRequest(Request $request, array $fields): array
    {
        return $request->validated($request->only($fields));
    }

    private function findUserByEmailIfExists(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    private function findUserByEmailOrFail(string $email, Request $request): User
    {
        $user = $this->findUserByEmailIfExists($email);

        if (!$user) {
            $this->logSecurityEvent('login_failed_user_not_found', $request, ['email' => $email]);
            $this->throwJsonException('Invalid credentials', 401);
        }

        return $user;
    }

    private function checkAccountLockForAction(?User $user, Request $request, string $action): void
    {
        if (!$user || !$this->accountLock->isAccountLocked($user)) {
            return;
        }

        $eventMap = [
            'registration' => 'registration_blocked_locked_account',
            'login' => 'login_blocked_account_locked',
            'password_reset' => 'password_reset_blocked_locked_account'
        ];

        $this->logSecurityEvent($eventMap[$action], $request, ['email' => $user->email]);

        if ($action === 'login') {
            $lockInfo = $this->accountLock->getAccountLockInfo($user);
            $this->throwJsonException([
                'message' => 'Account is temporarily locked due to multiple failed login attempts.',
                'locked_until' => $lockInfo['locked_until'],
                'time_remaining_seconds' => $lockInfo['time_until_unlock'],
                'time_remaining_minutes' => ceil($lockInfo['time_until_unlock'] / 60),
                'lockout_count' => $lockInfo['lockout_count'],
            ], 423);
        }

        if ($action === 'registration') {
            $timeUntilUnlock = $this->accountLock->getTimeUntilUnlock($user);
            $this->throwJsonException([
                'message' => 'Registration temporarily blocked. Please try again later.',
                'time_remaining_minutes' => ceil($timeUntilUnlock / 60)
            ], 423);
        }

        $this->throwException('Action temporarily blocked. Please try again later.', 423);
    }

    private function createUser(array $data): User
    {
        return User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'password_changed_at' => now(),
            'role_id' => Role::where('name', 'User')->first()->id
        ]);
    }

    private function validateUserCredentials(User $user, string $password, Request $request): void
    {
        if (!Hash::check($password, $user->password)) {
            $this->handleFailedLoginAttempt($user, $request);
        }
    }

    private function handleFailedLoginAttempt(User $user, Request $request): void
    {
        $this->accountLock->recordFailedAttempt($user, [
            'reason' => 'invalid_password',
            'email_attempted' => $request->email
        ]);

        $lockInfo = $this->accountLock->getAccountLockInfo($user);
        $remainingAttempts = max(0, 5 - $lockInfo['failed_attempts']);

        $this->logSecurityEvent('login_failed_invalid_password', $request, [
            'user_email' => $user->email,
            'failed_attempts' => $lockInfo['failed_attempts'],
            'remaining_attempts' => $remainingAttempts
        ]);

        $errorData = $this->buildFailedLoginErrorData($remainingAttempts, $lockInfo);
        $this->throwJsonException($errorData, 401);
    }

    private function buildFailedLoginErrorData(int $remainingAttempts, array $lockInfo): array
    {
        $errorData = ['message' => 'Invalid credentials'];

        if ($remainingAttempts <= 2 && $remainingAttempts > 0) {
            $errorData['warning'] = "You have {$remainingAttempts} login attempt(s) remaining before your account is locked.";
        } elseif ($remainingAttempts === 0) {
            $errorData['message'] = 'Account has been temporarily locked due to multiple failed login attempts.';
            $errorData['locked_until'] = $lockInfo['locked_until'];
        }

        return $errorData;
    }

    private function checkPasswordExpiry(User $user, Request $request): void
    {
        if (!$user->requiresPasswordChange()) {
            return;
        }

        $this->logSecurityEvent('login_requires_password_change', $request, [
            'user_email' => $user->email,
            'password_expired' => true
        ]);

        $this->throwJsonException([
            'message' => 'Your password has expired and must be changed before logging in.',
            'password_expired' => true,
            'change_password_required' => true
        ], 426);
    }

    private function createUserToken(User $user): array
    {
        $tokenResult = $user->createToken('User Access Token');
        $accessToken = $tokenResult->accessToken;
        $expiresAt = now()->addMinutes(30);

        $tokenResult->token->expires_at = $expiresAt;
        $tokenResult->token->save();

        $user->load(['role', 'userAddress']);

        return [
            'token_type' => 'Bearer',
            'access_token' => $accessToken,
            'expires_at' => $expiresAt->timestamp,
            'user' => new UserResource($user)
        ];
    }

    private function recordSuccessfulLogin(User $user): void
    {
        $user->recordSuccessfulLogin();
    }

    private function buildLoginResponse(array $tokenData, User $user): array
    {
        $securityScore = $user->getSecurityScore();

        if ($securityScore['score'] >= 60) {
            return $tokenData;
        }

        $tokenData['security_warning'] = [
            'message' => 'Your account security score is low. Please review your security settings.',
            'score' => $securityScore['score'],
            'issues' => $securityScore['issues']
        ];

        return $tokenData;
    }

    private function hasValidToken(?User $user): bool
    {
        return $user && $user->token();
    }

    private function revokeUserToken(User $user): void
    {
        $tokenId = $user->token()->id;
        $tokenRepository = app(TokenRepository::class);
        $tokenRepository->revokeAccessToken($tokenId);
        Token::where('id', $tokenId)->delete();
    }

    private function getAuthenticatedUser(): User
    {
        return auth()->user();
    }

    private function getAuthenticatedUserOrFail(Request $request): User
    {
        $user = $request->user();

        if (!$user) {
            $this->throwException('User not authenticated.', 401);
        }

        return $user;
    }

    private function validateCurrentPassword(User $user, string $currentPassword, Request $request): void
    {
        if (Hash::check($currentPassword, $user->password)) {
            return;
        }

        $this->accountLock->recordFailedAttempt($user, [
            'reason' => 'invalid_current_password_change_attempt'
        ]);

        $this->logSecurityEvent('password_change_failed_invalid_current', $request, [
            'user_email' => $user->email
        ]);

        $this->throwException('Current password is incorrect', 401);
    }

    private function updateUserPassword(User $user, string $newPassword): void
    {
        $this->passwordValidator->savePasswordToHistory($user, $user->password);

        $user->update([
            'password' => Hash::make($newPassword),
            'password_changed_at' => now(),
        ]);
    }

    private function sendPasswordResetLink(string $email): string
    {
        $status = Password::sendResetLink(['email' => $email]);

        if ($status !== PasswordBroker::RESET_LINK_SENT) {
            $this->throwException(__($status), 400);
        }

        return $status;
    }

    private function resetUserPassword(array $data, Request $request): string
    {
        $status = Password::reset(
            $data,
            function (User $user, string $password) {
                $this->passwordValidator->savePasswordToHistory($user, $user->password);

                $user->forceFill([
                    'password' => Hash::make($password),
                    'password_changed_at' => now(),
                ])->setRememberToken(Str::random(60));

                $user->save();
                $this->accountLock->unlockAccount($user);
                event(new PasswordReset($user));
            }
        );

        if ($status !== PasswordBroker::PASSWORD_RESET) {
            $this->throwException(__($status), 400);
        }

        return $status;
    }

    private function getPasswordExpiryInfo(User $user): array
    {
        return [
            'requires_change' => $user->requiresPasswordChange(),
            'days_until_expiry' => $user->getDaysUntilPasswordExpiry(),
            'last_changed' => $user->password_changed_at,
        ];
    }

    private function getLoginHistoryInfo(User $user): array
    {
        return [
            'last_login' => $user->last_login_at,
            'last_login_ip' => $user->last_login_ip,
        ];
    }

    private function validatePasswordStrength(string $password, Request $request, string $context, ?User $user = null): void
    {
        if ($this->passwordValidator->validate($password, $user)) {
            return;
        }

        $this->logSecurityEvent("{$context}_failed_weak_password", $request, [
            'email' => $request->email ?? ($user ? $user->email : 'unknown'),
            'password_errors' => $this->passwordValidator->getErrors()
        ]);

        $this->throwJsonException([
            'message' => 'Password does not meet security requirements.',
            'errors' => $this->passwordValidator->getErrors(),
            'strength' => $this->passwordValidator->calculatePasswordStrength($password)
        ], 422);
    }

    private function logSecurityEvent(string $event, Request $request, array $data = []): void
    {
        $this->securityLogger->logAuthEvent($event, $request, $data);
    }

    private function buildSuccessResponse(string $message, array $data = []): mixed
    {
        return $this->ok($message, $data);
    }

    private function throwException(string $message, int $code = 500): void
    {
        throw new Exception($message, $code);
    }

    private function throwJsonException($data, int $code = 500): void
    {
        $message = is_array($data) ? json_encode($data) : $data;
        throw new Exception($message, $code);
    }
}
