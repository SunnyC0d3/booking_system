<?php

namespace App\Http\Controllers\V1;

use App\Traits\V1\ApiResponses;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class EmailVerificationController
{
    use ApiResponses;

    public function verify($user_id, Request $request) {
        if (!$request->hasValidSignature()) {
            return $this->error('Invalid/Expired url provided.', 401);
        }
    
        $user = User::findOrFail($user_id);
    
        if (!$user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        return redirect(env('APP_URL_FRONTEND') . env('APP_URL_FRONTEND_EMAIL_VERIFIED'));
    }

    public function resend() {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            return $this->error('Email already verified.', 400);
        }
    
        $user->sendEmailVerificationNotification();
    
        return $this->ok('Email verification link sent on your email id');
    }
}