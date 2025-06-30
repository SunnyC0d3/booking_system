<?php

namespace App\Services\V1\Auth;

use App\Constants\AccountLockSettings;
use App\Models\User;
use App\Models\AccountLock as DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AccountLock
{
    protected Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function isAccountLocked(User $user): bool
    {
        $accountLock = $this->getAccountLock($user);

        if (!$accountLock) {
            return false;
        }

        if ($accountLock->shouldResetAttempts()) {
            $this->resetFailedAttempts($accountLock);
            return false;
        }

        return $accountLock->isLocked();
    }

    public function getTimeUntilUnlock(User $user): ?int
    {
        $accountLock = $this->getAccountLock($user);

        if (!$accountLock || !$accountLock->isLocked()) {
            return null;
        }

        return $accountLock->getTimeUntilUnlock();
    }

    public function recordFailedAttempt(User $user, array $metadata = []): DB
    {
        $accountLock = $this->getOrCreateAccountLock($user);

        if ($accountLock->shouldResetAttempts()) {
            $this->resetFailedAttempts($accountLock);
        }

        $accountLock->failed_attempts++;
        $accountLock->last_attempt_at = now();

        $accountLock->addAttemptToHistory('failed_login', array_merge($metadata, [
            'attempt_number' => $accountLock->failed_attempts,
            'user_agent' => $this->request->userAgent(),
            'referer' => $this->request->header('referer'),
        ]));

        if ($accountLock->failed_attempts >= AccountLockSettings::MAX_LOGIN_ATTEMPTS) {
            $this->lockAccount($accountLock);
        }

        $accountLock->save();

        Log::warning('Failed login attempt recorded', [
            'user_id' => $user->id,
            'email' => $user->email,
            'ip_address' => $this->request->ip(),
            'attempts' => $accountLock->failed_attempts,
            'locked' => $accountLock->isLocked(),
            'user_agent' => $this->request->userAgent(),
        ]);

        return $accountLock;
    }

    public function recordSuccessfulLogin(User $user): void
    {
        $accountLock = $this->getAccountLock($user);

        if ($accountLock) {
            $accountLock->failed_attempts = 0;
            $accountLock->locked_until = null;
            $accountLock->last_successful_login = now();

            $accountLock->addAttemptToHistory('successful_login', [
                'user_agent' => $this->request->userAgent(),
                'referer' => $this->request->header('referer'),
            ]);

            $accountLock->save();
        } else {
            $this->getOrCreateAccountLock($user)->update([
                'last_successful_login' => now(),
            ]);
        }

        Log::info('Successful login recorded', [
            'user_id' => $user->id,
            'email' => $user->email,
            'ip_address' => $this->request->ip(),
            'user_agent' => $this->request->userAgent(),
        ]);
    }

    public function unlockAccount(User $user): bool
    {
        $accountLock = $this->getAccountLock($user);

        if (!$accountLock) {
            return false;
        }

        $wasLocked = $accountLock->isLocked();

        $accountLock->failed_attempts = 0;
        $accountLock->locked_until = null;

        $accountLock->addAttemptToHistory('manual_unlock', [
            'unlocked_by' => 'admin',
            'was_locked' => $wasLocked,
        ]);

        $accountLock->save();

        Log::info('Account manually unlocked', [
            'user_id' => $user->id,
            'email' => $user->email,
            'was_locked' => $wasLocked,
        ]);

        return $wasLocked;
    }

    public function getAccountLockInfo(User $user): ?array
    {
        $accountLock = $this->getAccountLock($user);

        if (!$accountLock) {
            return null;
        }

        return [
            'is_locked' => $accountLock->isLocked(),
            'failed_attempts' => $accountLock->failed_attempts,
            'max_attempts' => AccountLockSettings::MAX_LOGIN_ATTEMPTS,
            'lockout_count' => $accountLock->lockout_count,
            'locked_until' => $accountLock->locked_until,
            'time_until_unlock' => $accountLock->getTimeUntilUnlock(),
            'last_attempt_at' => $accountLock->last_attempt_at,
            'last_successful_login' => $accountLock->last_successful_login,
            'should_reset' => $accountLock->shouldResetAttempts(),
        ];
    }

    public function getUserLockHistory(User $user, int $limit = 20): array
    {
        $accountLock = $this->getAccountLock($user);

        if (!$accountLock || !$accountLock->attempt_history) {
            return [];
        }

        $history = $accountLock->attempt_history;

        return array_slice(array_reverse($history), 0, $limit);
    }

    public function cleanupExpiredLocks(): int
    {
        $cleaned = DB::where('locked_until', '<', now())
            ->where('locked_until', '!=', null)
            ->update([
                'locked_until' => null,
                'failed_attempts' => 0,
            ]);

        Log::info('Expired account locks cleaned up', [
            'count' => $cleaned,
        ]);

        return $cleaned;
    }

    public function getGlobalLockStatistics(): array
    {
        $totalLocks = DB::whereNotNull('locked_until')
            ->where('locked_until', '>', now())
            ->count();

        $recentAttempts = DB::where('last_attempt_at', '>', now()->subHour())
            ->sum('failed_attempts');

        $uniqueIpsLocked = DB::whereNotNull('locked_until')
            ->where('locked_until', '>', now())
            ->distinct('ip_address')
            ->count();

        return [
            'currently_locked_accounts' => $totalLocks,
            'recent_failed_attempts' => $recentAttempts,
            'unique_ips_locked' => $uniqueIpsLocked,
        ];
    }

    protected function getAccountLock(User $user): ?DB
    {
        return DB::where('user_id', $user->id)
            ->where('ip_address', $this->request->ip())
            ->first();
    }

    protected function getOrCreateAccountLock(User $user): DB
    {
        return DB::firstOrCreate(
            [
                'user_id' => $user->id,
                'ip_address' => $this->request->ip(),
            ],
            [
                'user_agent' => $this->request->userAgent(),
                'failed_attempts' => 0,
                'lockout_count' => 0,
            ]
        );
    }

    protected function lockAccount(AccountLock $accountLock): void
    {
        $accountLock->lockout_count++;
        $lockoutDuration = $accountLock->getNextLockoutDuration();
        $accountLock->locked_until = now()->addMinutes($lockoutDuration);
        $accountLock->failed_attempts = 0;

        $accountLock->addAttemptToHistory('account_locked', [
            'lockout_count' => $accountLock->lockout_count,
            'lockout_duration_minutes' => $lockoutDuration,
            'locked_until' => $accountLock->locked_until->toISOString(),
        ]);

        Log::warning('Account locked due to failed attempts', [
            'user_id' => $accountLock->user_id,
            'ip_address' => $accountLock->ip_address,
            'lockout_count' => $accountLock->lockout_count,
            'locked_until' => $accountLock->locked_until,
            'lockout_duration_minutes' => $lockoutDuration,
        ]);
    }

    protected function resetFailedAttempts(AccountLock $accountLock): void
    {
        $oldAttempts = $accountLock->failed_attempts;

        $accountLock->failed_attempts = 0;
        $accountLock->locked_until = null;

        $accountLock->addAttemptToHistory('attempts_reset', [
            'previous_attempts' => $oldAttempts,
            'reason' => 'time_based_reset',
        ]);

        Log::info('Failed attempts reset due to time elapsed', [
            'user_id' => $accountLock->user_id,
            'ip_address' => $accountLock->ip_address,
            'previous_attempts' => $oldAttempts,
        ]);
    }
}
