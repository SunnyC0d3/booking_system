<?php

namespace App\Auth\V1;

use App\Models\User;
use Illuminate\Http\Request;
use App\Traits\V1\ApiResponses;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Passport\TokenRepository;
use Laravel\Passport\RefreshTokenRepository;
use Illuminate\Auth\Events\Registered;
use \Exception;

final class UserAuth
{
    use ApiResponses;

    public function register(Request $request)
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'user'
        ]);

        Auth::login($user);

        event(new Registered($user));

        return route(env('AFTER_REGISTER_REDIRECT_PATH'));
    }

    public function login(Request $request)
    {
        if (Auth::attempt(['email' => $request->email, 'password' => $request->password])) {
            $request->session()->regenerate();

            return redirect()->intended()->getTargetUrl();
        }

        throw new Exception('Invalid username or password', 400);
    }

    public function logout(Request $request)
    {
        $user = Auth::user();

        if ($user && $user->token()) {
            $tokenId = $user->token()->id;

            $tokenRepository = app(TokenRepository::class);
            $tokenRepository->revokeAccessToken($tokenId);

            $refreshTokenRepository = app(RefreshTokenRepository::class);
            $refreshTokenRepository->revokeRefreshTokensByAccessTokenId($tokenId);
        }

        Auth::logout();
 
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect(env('AFTER_LOGOUT_REDIRECT_PATH'));
    }

    public function hasPermission(string $scope)
    {
        $user = Auth::user();

        if ($user) {
            return $user->tokenCan($scope);
        }

        return false;
    }

    public function checkRole(array $roles)
    {
        $user = Auth::user();

        if ($user) {
            return in_array($user->role, $roles) ?? false;
        }
    }
}
