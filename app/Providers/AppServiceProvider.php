<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;
use App\Services\V1\Auth\UserAuth;
use App\Permissions\V1\Abilities;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void{}

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Passport::tokensCan(Abilities::Scopes);

        Passport::enablePasswordGrant();
        Passport::tokensExpireIn(now()->addMinutes(30));
        Passport::refreshTokensExpireIn(now()->addDays(7));

        ResetPassword::createUrlUsing(function (User $user, string $token) {
            return env('APP_URL_FRONTEND') . env('APP_URL_FRONTEND_PASSWORD_RESET') . '?token=' . $token;
        });
    }
}
