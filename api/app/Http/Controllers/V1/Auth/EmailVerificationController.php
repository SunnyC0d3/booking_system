<?php

namespace App\Http\Controllers\V1\Auth;

use App\Traits\V1\ApiResponses;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class EmailVerificationController
{
    use ApiResponses;

    public function verify(Request $request, $id, $hash)
    {
        if (!$request->hasValidSignature()) {
            return $this->error('Invalid/Expired url provided.', 401);
        }

        $user = User::findOrFail($id);

        if (!hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return $this->error('Invalid verification hash.', 401);
        }

        if ($user->hasVerifiedEmail()) {
            return $this->ok('Email is already verified.');
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return $this->ok('Email verified.');
    }

    public function resend()
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            return $this->error('Email already verified.', 400);
        }

        $user->sendEmailVerificationNotification();

        return $this->ok('Email verification link sent on your email id.');
    }
}
