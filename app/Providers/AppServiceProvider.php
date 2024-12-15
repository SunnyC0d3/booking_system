<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use App\Auth\V1\UserAuth;

use App\Requests\V1\LoginUserRequest;
use App\Requests\V1\LogoutUserRequest;
use App\Requests\V1\RegisterUserRequest;
use App\Requests\V1\RefreshTokenRequest;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(UserAuth::class, function ($app) {
            return new UserAuth(
                $app->make(RegisterUserRequest::class),
                $app->make(LoginUserRequest::class),
                $app->make(LogoutUserRequest::class),
                $app->make(RefreshTokenRequest::class)
            );
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
