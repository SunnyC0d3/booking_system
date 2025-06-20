<?php

namespace App\Http\Middleware\V1;

use App\Traits\V1\ApiResponses;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckPasswordExpiry
{
    use ApiResponses;

    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if (!$user) {
            return $next($request);
        }

        if ($user->requiresPasswordChange()) {
            $daysUntilExpiry = $user->getDaysUntilPasswordExpiry();

            return $this->error([
                'message' => 'Your password has expired and must be changed.',
                'password_expired' => true,
                'days_overdue' => abs($daysUntilExpiry),
                'change_password_url' => route('password.change'),
            ], 426);
        }

        $daysUntilExpiry = $user->getDaysUntilPasswordExpiry();
        if ($daysUntilExpiry !== null && $daysUntilExpiry <= 7) {
            $response = $next($request);

            if ($response instanceof \Illuminate\Http\JsonResponse) {
                $data = $response->getData(true);
                $data['password_warning'] = [
                    'message' => "Your password will expire in {$daysUntilExpiry} day(s).",
                    'days_remaining' => $daysUntilExpiry,
                    'change_password_url' => route('password.change'),
                ];
                $response->setData($data);
            }

            return $response;
        }

        return $next($request);
    }
}
