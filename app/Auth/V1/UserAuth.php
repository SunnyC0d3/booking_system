<?php

namespace App\Auth\V1;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use App\Traits\V1\ApiResponses;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Passport\TokenRepository;
use Laravel\Passport\RefreshTokenRepository;
use Illuminate\Support\Facades\Password;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Auth\PasswordBroker;
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
            'role_id' => Role::where('name', 'User')->first()->id
        ]);

        $user->sendEmailVerificationNotification();

        return $this->ok(
            'User registered successfully.',
            []
        );
    }

    public function login(Request $request)
    {
        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw new Exception('Invalid credentials.', 401);
        }

        $tokenExpiration = $request->remember ? now()->addWeeks(1) : now();

        $tokenResult = $user->createToken('User Access Token');
        $accessToken = $tokenResult->accessToken;
        $expiresIn = $tokenResult->token->expires_at->diffInSeconds($tokenExpiration);

        return $this->ok(
            'User logged in successfully.',
            [
                'token_type' => 'Bearer',
                'access_token' => $accessToken,
                'expires_in' => $expiresIn
            ]
        );
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

        return $this->ok(
            'User logged out successfully',
            []
        );
    }

    public function forgotPassword(Request $request)
    {
        $status = Password::sendResetLink(
            $request->only('email')
        );

        if ($status !== PasswordBroker::RESET_LINK_SENT) {
            throw new Exception(__($status), 400);
        }

        return $this->ok(
            __($status),
            []
        );
    }

    public function passwordReset(Request $request)
    {
        $status = Password::reset(
            $request->only('token', 'email', 'password', 'password_confirmation'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password)
                ])->setRememberToken(Str::random(60));

                $user->save();

                event(new PasswordReset($user));
            }
        );

        if ($status !== PasswordBroker::PASSWORD_RESET) {
            throw new Exception(__($status), 400);
        }

        return $this->ok(
            __($status),
            []
        );
    }
}
