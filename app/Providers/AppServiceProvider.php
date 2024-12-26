<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;
use App\Auth\V1\UserAuth;
use App\Models\User;

class AppServiceProvider extends ServiceProvider
{
    protected $policies = [
        User::class => \App\Policies\V1\UserPolicy::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(UserAuth::class, function ($app) {
            return new UserAuth();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Passport::enablePasswordGrant();
        Passport::tokensExpireIn(now()->addMinutes(30));
        Passport::refreshTokensExpireIn(now()->addDays(7));
        Passport::hashClientSecrets();
    }
}
