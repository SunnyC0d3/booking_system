<?php

namespace App\Http\Controllers\V1\Admin;

use App\Constants\PaymentMethods;
use App\Http\Controllers\Controller;
use App\Services\V1\Orders\Refunds\RefundProcessor;
use App\Services\V1\Orders\Refunds\Refunds;
use App\Services\V1\Orders\Refunds\ManualStripeRefund;
use App\Traits\V1\ApiResponses;
use Exception;
use Illuminate\Http\Request;

class RefundController extends Controller
{
    use ApiResponses;

    private $refunds;

    public function __construct(Refunds $refunds)
    {
        $this->refunds = $refunds;
    }

    public function index(Request $request)
    {
        try {
            return $this->refunds->all($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function refund(Request $request, string $gateway, int $id)
    {
        try {
            $handler = $this->handle($gateway);

            if (!class_exists($handler)) {
                return $this->error('Unsupported payment gateway', 400);
            }

            $refundProcessor = new RefundProcessor(app($handler));

            return $refundProcessor->refund($request, $id);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    private function handle(string $gateway)
    {
        return match ($gateway) {
            PaymentMethods::STRIPE => ManualStripeRefund::class,
            default => null,
        };
    }
}
