<?php

namespace App\Traits\V1;

use Illuminate\Http\Request;

trait TokenHelper
{
    private function getTokenFromRequest(Request $request, string $tokenType = 'access')
    {
        $token = '';

        if ($tokenType === 'access') {
            $token = explode('|', $request->access_token, 2)[1] ?? $request->access_token;
        }

        if ($tokenType === 'refresh') {
            $token = $request->refresh_token;
        }

        return hash('sha256', $token);
    }

    private function getTokenFromString(string $token = '', string $tokenType = 'access')
    {
        if ($tokenType === 'access') {
            $token = explode('|', $token, 2)[1] ?? $token;
        }

        if ($tokenType === 'refresh') {
            $token = $token;
        }

        return hash('sha256', $token);
    }
}
