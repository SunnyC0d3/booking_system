<?php

namespace App\Services\V1\Orders\Refunds;

use App\Constants\PaymentStatuses;
use App\Traits\V1\ApiResponses;
use Illuminate\Http\Request;
use Stripe\Refund as SR;
use Stripe\Stripe;
use \Stripe\Exception\ApiErrorException;

class StripeRefund extends Refund implements RefundHandler
{
    use ApiResponses;

    protected $secret;

    public function __construct()
    {
        parent::__construct();
        $this->secret = config('services.stripe_secret');
        Stripe::setApiKey($this->secret);
    }

    public function refund(Request $request, int $orderReturnId, bool $webhookEnabled = false)
    {
        $user = $request->user();

        if ($user->hasPermission('manage_refunds') || $webhookEnabled) {
            $this->getOrders($orderReturnId);

            if (!$this->stripeRefund($webhookEnabled)) {
                return $this->error('Refund failed via Stripe. Please try again later.', 422);
            }

            $this->setState();

            return $this->ok('Refund processed successfully.');
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    private function stripeRefund(bool $webhookEnabled)
    {
        $this->payment = $this->order->payments->where('status', PaymentStatuses::PAID)->firstOrFail();

        try {
            if (!$webhookEnabled) {
                SR::create([
                    'payment_intent' => $this->payment->transaction_reference,
                    'amount' => $this->orderItem->refundAmount(),
                ]);
            }
            return true;
        } catch (ApiErrorException $e) {
            $this->refundMarkAsFailed($e->getMessage());
            return false;
        }
    }
}
