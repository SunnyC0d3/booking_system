<?php

namespace App\Http\Middleware\V1;

use App\Services\V1\Auth\AccountLock;
use App\Traits\V1\ApiResponses;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckAccountLock
{
    use ApiResponses;

    protected AccountLock $accountLock;

    public function __construct(AccountLock $accountLock)
    {
        $this->accountLock = $accountLock;
    }

    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if (!$user) {
            return $next($request);
        }

        if ($this->accountLock->isAccountLocked($user)) {
            $timeUntilUnlock = $this->accountLock->getTimeUntilUnlock($user);
            $lockInfo = $this->accountLock->getAccountLockInfo($user);

            return $this->error([
                'message' => 'Account is temporarily locked due to multiple failed login attempts.',
                'locked_until' => $lockInfo['locked_until'],
                'time_remaining_seconds' => $timeUntilUnlock,
                'time_remaining_minutes' => ceil($timeUntilUnlock / 60),
                'lockout_count' => $lockInfo['lockout_count'],
            ], 423);
        }

        return $next($request);
    }
}
