<?php

namespace App\Http\Middleware\V1;

use Closure;
use Illuminate\Http\Request;

class GuestReviewRestriction
{
    /**
     * Handle requests for review actions that require authentication
     */
    public function handle(Request $request, Closure $next, string $action = 'general')
    {
        $user = $request->user();

        if (!$user) {
            $messages = [
                'create' => [
                    'message' => 'You must be logged in to create reviews.',
                    'suggestion' => 'Please log in or create an account to submit reviews.',
                ],
                'vote' => [
                    'message' => 'You must be logged in to vote on review helpfulness.',
                    'suggestion' => 'Please log in to help others find helpful reviews.',
                ],
                'report' => [
                    'message' => 'You must be logged in to report reviews.',
                    'suggestion' => 'Please log in to help us maintain review quality.',
                ],
                'respond' => [
                    'message' => 'You must be logged in as a vendor to respond to reviews.',
                    'suggestion' => 'Please log in to your vendor account.',
                ],
            ];

            $responseData = $messages[$action] ?? [
                'message' => 'Authentication required for this action.',
                'suggestion' => 'Please log in to continue.',
            ];

            return response()->json([
                ...$responseData,
                'error' => 'authentication_required',
                'action' => $action,
            ], 401);
        }

        return $next($request);
    }
}
