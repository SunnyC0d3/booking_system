<?php

namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;
use Illuminate\Database\Eloquent\Builder;

class PersonalAccessToken extends SanctumPersonalAccessToken
{
    protected $fillable = [
        'name',
        'token',
        'abilities',
        'refresh_token',
        'refresh_token_expires_at',
    ];

    protected $casts = [
        'refresh_token_expires_at' => 'datetime',
        'abilities' => 'json',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected $hidden = [
        'token',
    ];

    public function scopeExpired(Builder $query)
    {
        return $query->where('expires_at', '<', now())
            ->orWhere('refresh_token_expires_at', '<', now());
    }
}
