<?php

namespace App\Models;

use App\Filters\V1\QueryFilter;
use App\Services\V1\Auth\PasswordValidation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Hash;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'role_id',
        'stripe_customer_id',
        'password_changed_at',
        'failed_login_attempts',
        'locked_until',
        'last_login_at',
        'last_login_ip',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'password_changed_at' => 'datetime',
            'locked_until' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function vendors(): HasMany
    {
        return $this->hasMany(Vendor::class);
    }

    public function userAddress(): HasOne
    {
        return $this->hasOne(UserAddress::class);
    }

    public function cart(): HasOne
    {
        return $this->hasOne(Cart::class)->active();
    }

    public function accountLocks(): HasMany
    {
        return $this->hasMany(AccountLock::class);
    }

    public function passwordHistories(): HasMany
    {
        return $this->hasMany(PasswordHistory::class);
    }

    public function currentAccountLock(): HasOne
    {
        return $this->hasOne(AccountLock::class)
            ->where('ip_address', request()->ip() ?? '127.0.0.1')
            ->latest();
    }

    public function hasRole(string|array $roles): bool
    {
        if (is_string($roles)) {
            $roles = strtolower($roles);
        } else {
            $roles = array_map('strtolower', $roles);
        }

        $rolesIds = $this->role->whereIn('name', $roles)->pluck('id')->toArray();

        return in_array($this->role_id, $rolesIds);
    }

    public function hasPermission(string|array $permissions): bool
    {
        if (is_string($permissions)) {
            $permissions = strtolower($permissions);
        } else {
            $permissions = array_map('strtolower', $permissions);
        }

        $userPermissions = $this->role->permissions->pluck('name')->toArray();

        foreach ((array)$permissions as $permission) {
            if (in_array($permission, $userPermissions)) {
                return true;
            }
        }

        return false;
    }

    public function setNameAttribute(string $value)
    {
        $words = explode(' ', $value);
        $capitalizedWords = array_map('ucwords', $words);

        $this->attributes['name'] = implode(' ', $capitalizedWords);
    }

    public function getNameAttribute(string $value)
    {
        $words = explode(' ', $value);
        $capitalizedWords = array_map('ucwords', $words);

        return implode(' ', $capitalizedWords);
    }

    public function scopeFilter(Builder $builder, QueryFilter $filters)
    {
        return $filters->apply($builder);
    }

    public function validatePassword(string $password): array
    {
        $validator = app(PasswordValidation::class);
        $isValid = $validator->validate($password, $this);

        return [
            'valid' => $isValid,
            'errors' => $validator->getErrors(),
            'strength' => $validator->calculatePasswordStrength($password),
        ];
    }

    public function updatePassword(string $newPassword): bool
    {
        $validation = $this->validatePassword($newPassword);

        if (!$validation['valid']) {
            return false;
        }

        $passwordService = app(PasswordValidation::class);
        $hashedPassword = Hash::make($newPassword);

        $passwordService->savePasswordToHistory($this, $this->password);

        $this->update([
            'password' => $hashedPassword,
            'password_changed_at' => now(),
        ]);

        return true;
    }

    public function requiresPasswordChange(): bool
    {
        if (!$this->password_changed_at) {
            return true;
        }

        $maxPasswordAge = config('auth.password_max_age_days', 90);
        return $this->password_changed_at->addDays($maxPasswordAge)->isPast();
    }

    public function getDaysUntilPasswordExpiry(): ?int
    {
        if (!$this->password_changed_at) {
            return 0;
        }

        $maxPasswordAge = config('auth.password_max_age_days', 90);
        $expiryDate = $this->password_changed_at->addDays($maxPasswordAge);

        if ($expiryDate->isPast()) {
            return 0;
        }

        return now()->diffInDays($expiryDate);
    }

    public function isAccountLocked(): bool
    {
        $accountLockService = app('App\Services\V1\Auth\AccountLock');
        return $accountLockService->isAccountLocked($this);
    }

    public function getAccountLockInfo(): ?array
    {
        $accountLockService = app('App\Services\V1\Auth\AccountLock');
        return $accountLockService->getAccountLockInfo($this);
    }

    public function unlockAccount(): bool
    {
        $accountLockService = app('App\Services\V1\Auth\AccountLock');
        return $accountLockService->unlockAccount($this);
    }

    public function recordSuccessfulLogin(): void
    {
        $this->update([
            'last_login_at' => now(),
            'last_login_ip' => request()->ip(),
        ]);

        $accountLockService = app('App\Services\V1\Auth\AccountLock');
        $accountLockService->recordSuccessfulLogin($this);
    }

    public function recordFailedLoginAttempt(array $metadata = []): void
    {
        $accountLockService = app('App\Services\V1\Auth\AccountLock');
        $accountLockService->recordFailedAttempt($this, $metadata);
    }

    public function getPasswordStrength(): array
    {
        if (!$this->password) {
            return ['score' => 0, 'strength' => 'none', 'feedback' => ['No password set']];
        }

        $passwordService = app(PasswordValidation::class);
        return $passwordService->calculatePasswordStrength($this->password);
    }

    public function hasRecentlyChangedPassword(int $hours = 24): bool
    {
        if (!$this->password_changed_at) {
            return false;
        }

        return $this->password_changed_at->isAfter(now()->subHours($hours));
    }

    public function getSecurityScore(): array
    {
        $score = 100;
        $issues = [];

        if (!$this->email_verified_at) {
            $score -= 20;
            $issues[] = 'Email not verified';
        }

        if ($this->requiresPasswordChange()) {
            $score -= 30;
            $issues[] = 'Password expired';
        }

        $passwordStrength = $this->getPasswordStrength();
        if ($passwordStrength['strength'] === 'weak') {
            $score -= 25;
            $issues[] = 'Weak password';
        } elseif ($passwordStrength['strength'] === 'medium') {
            $score -= 10;
            $issues[] = 'Medium strength password';
        }

        if (!$this->last_login_at || $this->last_login_at->isBefore(now()->subDays(30))) {
            $score -= 10;
            $issues[] = 'Inactive account';
        }

        $lockInfo = $this->getAccountLockInfo();
        if ($lockInfo && $lockInfo['lockout_count'] > 0) {
            $score -= 15;
            $issues[] = 'Account has been locked previously';
        }

        $level = 'excellent';
        if ($score < 40) $level = 'poor';
        elseif ($score < 60) $level = 'fair';
        elseif ($score < 80) $level = 'good';

        return [
            'score' => max(0, $score),
            'level' => $level,
            'issues' => $issues,
        ];
    }

    public function scopeLocked(Builder $query): Builder
    {
        return $query->whereHas('accountLocks', function (Builder $q) {
            $q->where('locked_until', '>', now());
        });
    }

    public function scopeWithExpiredPasswords(Builder $query): Builder
    {
        $maxPasswordAge = config('auth.password_max_age_days', 90);

        return $query->where(function (Builder $q) use ($maxPasswordAge) {
            $q->whereNull('password_changed_at')
                ->orWhere('password_changed_at', '<', now()->subDays($maxPasswordAge));
        });
    }

    public function scopeRecentlyActive(Builder $query, int $days = 30): Builder
    {
        return $query->where('last_login_at', '>', now()->subDays($days));
    }

    public function getOrCreateCart(): Cart
    {
        $cart = $this->cart;

        if (!$cart) {
            $cart = $this->cart()->create([
                'expires_at' => now()->addDays(30),
            ]);
        }

        return $cart;
    }
}
