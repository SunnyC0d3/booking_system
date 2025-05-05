<?php

namespace App\Services\V1\Payments;

use App\Constants\PaymentMethods;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use App\Models\Payment as DB;
use App\Models\PaymentMethod;
use App\Models\Order;
use App\Traits\V1\ApiResponses;
use Stripe\PaymentIntent;
use Stripe\Stripe;

class StripePayment implements PaymentHandler
{
    use ApiResponses;

    private $secret;
    private $webhook_secret;

    public function __construct()
    {
        $this->secret = config('services.stripe_secret');
        $this->webhook_secret = config('services.stripe_webhook_secret');
    }

    public function createPayment(Request $request)
    {
        $user = $request->user();
//BRING THIS BACK IN AFTER TESTING
//        if ($user->hasPermission('create_payments')) {
            $data = $request->validated();

            $order = Order::with(['orderItems.product', 'user'])->findOrFail($data['order_id']);

            Stripe::setApiKey($this->secret);

            $metaData = [
                'order_id' => $order->id,
                'order_total' => (string) $order->total_amount,
                'user' => json_encode($this->getUserDetails($order->user)),
                'products' => json_encode($this->getProductDetails($order->orderItems)),
            ];

            $intent = PaymentIntent::create([
                'amount' => $order->total_amount,
                'currency' => 'gbp',
                'automatic_payment_methods' => [
                    'enabled' => true
                ],
                'metadata' => $metaData,
            ]);

            $paymentMethod = PaymentMethod::where('name', PaymentMethods::STRIPE)->firstOrFail();

            $payment = DB::create([
                'order_id' => $order->id,
                'user_id' => $order->user->id,
                'payment_method_id' => $paymentMethod->id,
                'amount' => $order->total_amount,
                'status' => 'pending',
                'processed_at' => now(),
                'transaction_reference' => $intent->id,
            ]);

            return $this->ok('PaymentIntent created successfully.', [
                'client_secret' => $intent->client_secret,
            ]);
        //}

        //return $this->error('You do not have the required permissions.', 403);
    }

    public function webhook(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');

        $event = Webhook::constructEvent($payload, $sigHeader, $this->webhook_secret);

        if ($event->type === 'payment_intent.succeeded') {
            $intent = $event->data->object;
            $payment = DB::where('transaction_reference', $intent->id)->first();

            if ($payment) {
                $payment->status = 'paid';
                $payment->processed_at = now();
                $payment->response_payload = json_encode($intent);
                $payment->save();
            }
        }

        if ($event->type === 'payment_intent.payment_failed') {
            $intent = $event->data->object;
            $payment = DB::where('transaction_reference', $intent->id)->first();

            if ($payment) {
                $payment->status = 'failed';
                $payment->response_payload = json_encode($intent);
                $payment->save();
            }
        }

        return $this->ok('Webhook update.', true);
    }

    private function getProductDetails(Collection $orderItems)
    {
        $products = [];

        foreach ($orderItems as $item) {
            $products[] = [
                'product_id' => $item->product->id,
                'product_name' => $item->product->name,
                'quantity' => $item->quantity,
                'price' => $item->price,
            ];
        }

        return $products;
    }

    private function getUserDetails(User $user)
    {
        $userDetails = [
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_email' => $user->email,
        ];

        if ($user->userAddress) {
            $userDetails['user_address'] = [
                'line1' => $user->userAddress->address_line1,
                'line2' => $user->userAddress->address_line2,
                'city' => $user->userAddress->city,
                'postcode' => $user->userAddress->postal_code,
                'country' => $user->userAddress->country,
            ];
        }

        return $userDetails;
    }

}
