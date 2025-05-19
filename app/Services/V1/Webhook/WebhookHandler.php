<?php

namespace App\Services\V1\Webhook;

use Illuminate\Http\Request;
interface WebhookHandler
{
    public function webhook(Request $request);
}
