<?php

namespace App\Http\Controllers\V1\Auth;

use App\Traits\V1\ApiResponses;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class EmailVerificationController
{
    use ApiResponses;

    /**
     * Verify a user's email address.
     *
     * Once a user registers, a notification is sent out to their specified email address, which requires them to verify.
     *
     * @group Email Verification
     *
     * @queryParam id string required The user's id. Example: 1
     * @queryParam hash string required The hash to verify the email. Example: 1234567890
     *
     * @response 200 Redirected to specified URL
     *
     * @response 401 {
     *   "message": "Invalid/Expired url provided.",
     *   "status": 401
     * }
     */
    public function verify(int $user_id,  Request $request) {
        if (!$request->hasValidSignature()) {
            return $this->error('Invalid/Expired url provided.', 401);
        }

        $user = User::findOrFail($user_id);

        if (!$user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        return redirect(env('APP_URL_FRONTEND') . env('APP_URL_FRONTEND_EMAIL_VERIFIED'));
    }

    /**
     * Resend email to user.
     *
     * Once a user registers, a notification is sent out to their specified email address, which requires them to verify. Incase they dont recieve one, they can request again.
     *
     * @group Email Verification
     *
     * @header Authorization Bearer token required.
     *
     * @response 200 {
     *   "message": "Email verification link sent on your email id.",
     *   "status": 200
     * }
     *
     * @response 401 {
     *   "message": "Email already verified.",
     *   "status": 400
     * }
     */
    public function resend() {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            return $this->error('Email already verified.', 400);
        }

        $user->sendEmailVerificationNotification();

        return $this->ok('Email verification link sent on your email id.');
    }
}
