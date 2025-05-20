<?php

namespace App\Services\V1\Webhook;

use Illuminate\Http\Request;
interface WebhookHandlerInterface
{
    public function webhook(Request $request);
}
