<?php

namespace App\Services\V1\Orders\Refunds;

use App\Constants\PaymentStatuses;
use App\Traits\V1\ApiResponses;
use Illuminate\Http\Request;
use Stripe\Refund as SR;
use Stripe\Stripe;

class StripeRefund extends Refund implements RefundHandler
{
    use ApiResponses;

    protected $secret;

    public function __construct()
    {
        $this->secret = config('services.stripe_secret');
        Stripe::setApiKey($this->secret);
    }

    public function refund(Request $request, int $orderReturnId)
    {
        $user = $request->user();

        if ($user->hasPermission('manage_refunds')) {
            $this->getOrders($orderReturnId);
            $this->stripeRefund();
            $this->setState();

            return $this->ok('Refund processed successfully.');
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    private function stripeRefund() {
        $payment = $this->order->payments->where('status', PaymentStatuses::PAID)->firstOrFail();

        SR::create([
            'payment_intent' => $payment->transaction_reference,
            'amount' => $this->orderItem->refundAmount(),
        ]);
    }
}
