<?php

namespace App\Http\Controllers\V1\Admin;

use Illuminate\Http\Request;
use App\Requests\V1\StorePaymentRequest;
use App\Http\Controllers\Controller;
use App\Services\V1\Payments\StripePayment;
use App\Traits\V1\ApiResponses;
use App\Constants\PaymentGateways;
use \Exception;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    use ApiResponses;

    public function __construct()
    {
    }

    public function stripeWebhook(Request $request)
    {
        try {
            $handler = $this->handle(PaymentGateways::STRIPE);

            if (!class_exists($handler)) {
                return $this->error('Unsupported payment gateway', 400);
            }

            $handler = app($handler);

            return $handler->webhook($request);
        } catch (Exception $e) {
            Log::error('Stripe webhook error: ' . $e->getMessage());
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function store(StorePaymentRequest $request, string $gateway)
    {
        try {
            $handler = $this->handle($gateway);

            if (!class_exists($handler)) {
                return $this->error('Unsupported payment gateway', 400);
            }

            $handler = app($handler);

            return $handler->createPayment($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    private function handle(string $gateway)
    {
        return match ($gateway) {
            PaymentGateways::STRIPE => StripePayment::class,
            default => null,
        };
    }
}
