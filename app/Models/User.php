<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function hasRole(string|array $roles): bool
    {
        $rolesIds = $this->role->whereIn('name', $roles)->pluck('id')->toArray();

        return in_array($this->role_id, $rolesIds);
    }

    public function setNameAttribute($value)
    {
        $words = explode(' ', $value);
        $capitalizedWords = array_map('ucwords', $words);

        $this->attributes['name'] = implode(' ', $capitalizedWords);
    }

    public function getNameAttribute($value)
    {
        $words = explode(' ', $value);
        $capitalizedWords = array_map('ucwords', $words);

        return implode(' ', $capitalizedWords);
    }
}
