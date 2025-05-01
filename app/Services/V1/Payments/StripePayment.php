<?php

namespace App\Services\V1\Payments;

use App\Constants\PaymentGateways;
use Illuminate\Http\Request;
use App\Models\Payment as DB;
use App\Models\Order;
use App\Traits\V1\ApiResponses;

class StripePayment implements PaymentHandler
{
    use ApiResponses;

    public function __construct()
    {
    }

    public function createPayment(Request $request)
    {
        $user = $request->user();

        if ($user->hasPermission('create_payments')) {
            $data = $request->validated();

            $order = Order::findOrFail($data['order_id']);

            Stripe::setApiKey(config('services.stripe.secret'));

            $intent = PaymentIntent::create([
                'amount' => $order->total_amount * 100,
                'currency' => 'gbp',
                'metadata' => [
                    'order_id' => $order->id,
                    'user_id' => $user->id,
                ],
            ]);

            $stripeMethod = PaymentMethod::where('name', PaymentGateways::STRIPE)->firstOrFail();

            $payment = DB::create([
                'order_id' => $order->id,
                'user_id' => $user->id,
                'payment_method_id' => $stripeMethod->id,
                'amount' => $order->total_amount,
                'status' => 'pending',
                'processed_at' => null,
                'transaction_reference' => $intent->id,
            ]);

            return $this->ok('PaymentIntent created successfully.', [
                'client_secret' => $intent->client_secret,
            ]);
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    public function webhook(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $secret = config('services.stripe.webhook_secret');

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $secret);
        } catch (\Exception $e) {
            Log::error('Stripe webhook error: ' . $e->getMessage());
            return response()->json(['error' => 'Invalid webhook'], 400);
        }

        if ($event->type === 'payment_intent.succeeded') {
            $intent = $event->data->object;
            $payment = Payment::where('transaction_reference', $intent->id)->first();

            if ($payment) {
                $payment->status = 'paid';
                $payment->processed_at = now();
                $payment->response_payload = json_encode($intent);
                $payment->save();
            }
        }

        if ($event->type === 'payment_intent.payment_failed') {
            $intent = $event->data->object;
            $payment = Payment::where('transaction_reference', $intent->id)->first();

            if ($payment) {
                $payment->status = 'failed';
                $payment->response_payload = json_encode($intent);
                $payment->save();
            }
        }

        return response()->json(['received' => true]);
    }

}
