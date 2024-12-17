<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
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
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
    }
}
