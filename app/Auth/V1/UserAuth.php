<?php

namespace App\Auth\V1;

use App\Models\User;
use Illuminate\Http\Request;
use App\Traits\V1\ApiResponses;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Passport\TokenRepository;
use Laravel\Passport\RefreshTokenRepository;

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

        return redirect('login')->withErrors(['success' => 'Successfully registered.']);
    }

    public function login(Request $request)
    {
        if(Auth::attempt(['email' => $request->email, 'password' => $request->password])){
            return redirect()->intended();
        }

        return back()->withErrors(['error' => 'Invalid username or password']);
    }

    public function logout()
    {
        $user = Auth::user();

        if ($user && $user->token()) {
            $tokenId = $user->token()->id;

            $tokenRepository = app(TokenRepository::class);
            $tokenRepository->revokeAccessToken($tokenId);

            $refreshTokenRepository = app(RefreshTokenRepository::class);
            $refreshTokenRepository->revokeRefreshTokensByAccessTokenId($tokenId);
        }

        return $this->ok('User logged out Successfully.');
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
