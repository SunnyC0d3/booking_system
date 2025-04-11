<?php

namespace App\Models;

use App\Filters\V1\QueryFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
}
