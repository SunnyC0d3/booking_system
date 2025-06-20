<?php

namespace App\Models;

use App\Constants\AccountLockSettings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AccountLock extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'ip_address',
        'user_agent',
        'failed_attempts',
        'lockout_count',
        'last_attempt_at',
        'locked_until',
        'last_successful_login',
        'attempt_history',
    ];

    protected function casts(): array
    {
        return [
            'last_attempt_at' => 'datetime',
            'locked_until' => 'datetime',
            'last_successful_login' => 'datetime',
            'attempt_history' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isLocked(): bool
    {
        return $this->locked_until && $this->locked_until->isFuture();
    }

    public function getTimeUntilUnlock(): ?int
    {
        if (!$this->isLocked()) {
            return null;
        }

        return $this->locked_until->diffInSeconds(now());
    }

    public function shouldResetAttempts(): bool
    {
        if (!$this->last_attempt_at) {
            return false;
        }

        $resetHours = AccountLockSettings::RESET_ATTEMPTS_AFTER_HOURS;
        return $this->last_attempt_at->addHours($resetHours)->isPast();
    }

    public function getNextLockoutDuration(): int
    {
        if (!AccountLockSettings::PROGRESSIVE_LOCKOUT_ENABLED) {
            return AccountLockSettings::LOCKOUT_DURATION_MINUTES;
        }

        $nextLockoutCount = $this->lockout_count + 1;
        $durations = AccountLockSettings::LOCKOUT_DURATIONS;

        return $durations[$nextLockoutCount] ?? end($durations);
    }

    public function addAttemptToHistory(string $type, array $metadata = []): void
    {
        $history = $this->attempt_history ?? [];

        $history[] = [
            'type' => $type,
            'timestamp' => now()->toISOString(),
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'metadata' => $metadata,
        ];

        $this->attempt_history = array_slice($history, -50);
    }
}
