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

final class UserAuth
{
    use ApiResponses;

    private $userScopes = [
        'read-products'
    ];

    private function revokeOldTokens($userId)
    {
        $tokenRepository = app(TokenRepository::class);
        $refreshTokenRepository = app(RefreshTokenRepository::class);

        $tokens = $tokenRepository->forUser($userId);

        foreach ($tokens as $token) {
            $tokenRepository->revokeAccessToken($token->id);
            $refreshTokenRepository->revokeRefreshTokensByAccessTokenId($token->id);
        }
    }

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
        $targetUrl = redirect()->intended()->getTargetUrl();
        $parsedPath = parse_url($targetUrl, PHP_URL_PATH);

        if (Auth::attempt(['email' => $request->email, 'password' => $request->password])) {
            $request->session()->regenerate();

            if ($parsedPath === '/oauth/authorize') {
                redirect()->intended()->getTargetUrl();
            } else {
                $user = Auth::user();

                $this->revokeOldTokens($user->id);

                $tokenResult = $user->createToken('User Access Token', $this->userScopes);
                $accessToken = $tokenResult->accessToken;
                $expiresIn = $tokenResult->token->expires_at->diffInSeconds(now());
        
                $accessTokenCookie = cookie(
                    'access_token', 
                    $accessToken,    
                    $expiresIn / 60, 
                    env('APP_URL_FRONTEND'),             
                    null,            
                    true,           
                    true,          
                    false,          
                    'None'           
                );
        
                return redirect(env('APP_URL_FRONTEND'))->withCookie($accessTokenCookie);
            }
        }

        return back()->withErrors([
            'global' => 'Username or password is incorrect.'
        ]);
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

        redirect(env('AFTER_LOGOUT_REDIRECT_PATH'));
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
