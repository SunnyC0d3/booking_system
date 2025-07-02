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

    /**
     * Verify a user's email address
     *
     * This endpoint verifies a user's email address using a signed URL that is sent to their email
     * after registration. The verification link contains the user ID and a hash for security.
     * Once verified, the user's email_verified_at timestamp is set and they can access protected features.
     *
     * @group Email Verification
     * @unauthenticated
     *
     * @urlParam user_id integer required The ID of the user whose email is being verified. This is automatically included in the verification link. Example: 123
     * @urlParam hash string required The verification hash that ensures the link is valid and hasn't been tampered with. This is automatically included in the verification link. Example: abc123def456ghi789
     *
     * @queryParam expires integer required The expiration timestamp for the verification link. Automatically included. Example: 1640995200
     * @queryParam signature string required The signature that validates the signed URL. Automatically included. Example: xyz789abc123def456
     *
     * @response 302 scenario="Email verified successfully" {
     *   "description": "User is redirected to the frontend email verification success page",
     *   "headers": {
     *     "Location": "https://yourfrontend.com/email-verified"
     *   }
     * }
     *
     * @response 302 scenario="Email already verified" {
     *   "description": "User is redirected to the frontend (email was already verified previously)",
     *   "headers": {
     *     "Location": "https://yourfrontend.com/email-verified"
     *   }
     * }
     *
     * @response 401 scenario="Invalid or expired verification link" {
     *   "message": "Invalid/Expired url provided.",
     *   "status": 401
     * }
     *
     * @response 404 scenario="User not found" {
     *   "message": "No query results for model [App\\Models\\User] 123"
     * }
     *
     * @response 403 scenario="Signature verification failed" {
     *   "message": "Invalid/Expired url provided.",
     *   "status": 401
     * }
     */
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

    /**
     * Resend email verification notification
     *
     * Sends a new email verification link to the authenticated user's email address.
     * This is useful when users don't receive the initial verification email or when
     * the verification link has expired. The user must be authenticated to request a resend.
     *
     * @group Email Verification
     * @authenticated
     *
     * @response 200 scenario="Verification email sent successfully" {
     *   "message": "Email verification link sent on your email id.",
     *   "status": 200
     * }
     *
     * @response 400 scenario="Email already verified" {
     *   "message": "Email already verified.",
     *   "status": 400
     * }
     *
     * @response 401 scenario="User not authenticated" {
     *   "message": "Unauthenticated.",
     *   "status": 401
     * }
     *
     * @response 429 scenario="Too many requests" {
     *   "message": "Too many verification emails sent. Please wait before requesting another.",
     *   "status": 429
     * }
     *
     * @response 500 scenario="Email sending failed" {
     *   "message": "Failed to send verification email. Please try again later.",
     *   "status": 500
     * }
     */
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
