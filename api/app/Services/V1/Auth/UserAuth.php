<?php

namespace App\Services\V1\Auth;

use App\Models\Role;
use App\Models\User;
use App\Resources\V1\UserResource;
use Illuminate\Http\Request;
use App\Traits\V1\ApiResponses;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Passport\Token;
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
            'password_changed_at' => now(),
            'role_id' => Role::where('name', 'User')->first()->id
        ]);

        $user->sendEmailVerificationNotification();

        // Load the role relationship for the UserResource
        $user->load('role');

        return $this->ok(
            'User registered successfully.',
            [
                'user' => new UserResource($user)
            ]
        );
    }

    public function login(Request $request)
    {
        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw new Exception('Invalid credentials.', 401);
        }

        $tokenResult = $user->createToken('User Access Token');
        $accessToken = $tokenResult->accessToken;
        $expiresAt = now()->addMinutes(30);

        $tokenResult->token->expires_at = $expiresAt;
        $tokenResult->token->save();

        $user->load(['role', 'userAddress']);

        return $this->ok(
            'User logged in successfully.',
            [
                'token_type' => 'Bearer',
                'access_token' => $accessToken,
                'expires_at' => $expiresAt->timestamp,
                'user' => new UserResource($user)
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

            Token::where('id', $tokenId)->delete();
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

    public function getAuthenticatedUser(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            throw new Exception('User not authenticated.', 401);
        }

        $user->load(['role', 'userAddress', 'vendors']);

        return $this->ok(
            'User data retrieved successfully.',
            [
                'user' => new UserResource($user)
            ]
        );
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            throw new Exception('User not authenticated.', 401);
        }

        $user->update($request->only(['name', 'email']));
        $user->load(['role', 'userAddress', 'vendors']);

        return $this->ok(
            'Profile updated successfully.',
            [
                'user' => new UserResource($user)
            ]
        );
    }
}
