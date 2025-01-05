<?php

namespace App\Http\Middleware\V1;

use Closure;
use Illuminate\Http\Request;
use App\Traits\V1\ApiResponses;

class CheckRedirectPath
{
    use ApiResponses;
    
    public function handle(Request $request, Closure $next)
    {
        $frontendURL            = env('APP_URL_FRONTEND');
        $registerRedirectPath   = env('AFTER_REGISTER_REDIRECT_PATH');
        $loginRedirectPath      = env('AFTER_LOGIN_REDIRECT_PATH');
        $logoutRedirectPath     = env('AFTER_LOGOUT_REDIRECT_PATH');

        if (
            $this->isValidPath($frontendURL) &&
            $this->isValidPath($registerRedirectPath) &&
            $this->isValidPath($loginRedirectPath) &&
            $this->isValidPath($logoutRedirectPath)
        ) {
            return $next($request);
        }


        return $this->error('Invalid redirect path', 400);
    }

    private function isValidPath($path)
    {
        return !empty($path) && (filter_var($path, FILTER_VALIDATE_URL) || preg_match('/^\/(?!\/|.*:\/\/)/', $path));
    }
}
