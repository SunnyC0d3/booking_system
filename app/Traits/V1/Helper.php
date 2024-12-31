<?php

namespace App\Traits\V1;

use Illuminate\Http\Request;

trait Helper
{
    public function verifyHmac(Request $request)
    {
        $data = json_decode($request->input('data'), true);
        $receivedHmac = $request->input('hmac');

        if (abs(time() - $data['timestamp']) > 300) { 
            return false;
        }

        $calculatedHmac = hash_hmac('sha256', $data, env('HMAC_SECRET_KEY'));

        if (hash_equals($calculatedHmac, $receivedHmac)) {
            return true;
        }

        return false;
    }
}
