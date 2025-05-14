<?php

namespace App\Http\Controllers\V1\Admin;

use App\Constants\PaymentMethods;
use App\Http\Controllers\Controller;
use App\Services\V1\Refunds\StripeRefund;
use Illuminate\Http\Request;
use App\Traits\V1\ApiResponses;
use \Exception;

class RefundController extends Controller
{
    use ApiResponses;
    public function refund(Request $request, string $gateway, int $orderReturnId)
    {
        try {
            $handler = $this->handle($gateway);

            if (!class_exists($handler)) {
                return $this->error('Unsupported payment gateway', 400);
            }

            $handler = app($handler);

            return $handler->refund($orderReturnId);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    private function handle(string $gateway)
    {
        return match ($gateway) {
            PaymentMethods::STRIPE => StripeRefund::class,
            default => null,
        };
    }
}
