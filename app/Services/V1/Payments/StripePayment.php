<?php

namespace App\Services\V1\Payments;

use App\Constants\PaymentMethods;
use App\Constants\PaymentStatuses;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use App\Models\Payment as DB;
use App\Models\PaymentMethod;
use App\Models\Order;
use App\Traits\V1\ApiResponses;
use Stripe\PaymentIntent;
use Stripe\Stripe;
use Stripe\Customer;

class StripePayment implements PaymentHandlerInterface
{
    use ApiResponses;

    private $secret;

    public function __construct()
    {
        $this->secret = config('services.stripe_secret');
        Stripe::setApiKey($this->secret);
    }

    public function createPayment(Request $request)
    {
        $data = $request->validated();

        $order = Order::with(['orderItems.product', 'user'])->findOrFail($data['order_id']);
        $paymentMethod = PaymentMethod::where('name', PaymentMethods::STRIPE)->firstOrFail();

        $existingPayment = DB::where('order_id', $order->id)
            ->where('payment_method_id', $paymentMethod->id)
            ->first();

        if ($existingPayment) {
            if ($existingPayment->status === PaymentStatuses::PAID) {
                return $this->ok('Payment has already been processed for this order.');
            }

            $intent = PaymentIntent::retrieve($existingPayment->transaction_reference);

            return $this->ok('Existing PaymentIntent retrieved.', [
                'client_secret' => $intent->client_secret,
            ]);
        }

        if (!$order->user->stripe_customer_id) {
            $customer = Customer::create($this->getUserDetails($order->user));
            $order->user->stripe_customer_id = $customer->id;
            $order->user->save();
        } else {
            $customer = Customer::retrieve($order->user->stripe_customer_id);
        }

        $intent = PaymentIntent::create([
            'amount' => $order->total_amount,
            'currency' => 'gbp',
            'automatic_payment_methods' => [
                'enabled' => true
            ],
            'customer' => $customer->id,
            'metadata' => [
                'order_id' => $order->id,
                'user_id' => $order->user->id
            ]
        ]);

        DB::create([
            'order_id' => $order->id,
            'user_id' => $order->user->id,
            'payment_method_id' => $paymentMethod->id,
            'amount' => $order->total_amount,
            'status' => PaymentStatuses::PENDING,
            'processed_at' => now(),
            'transaction_reference' => $intent->id,
        ]);

        return $this->ok('PaymentIntent created successfully.', [
            'client_secret' => $intent->client_secret,
        ]);
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
            'name' => $user->name,
            'email' => $user->email,
        ];

        if ($user->userAddress) {
            $userDetails['address'] = [
                'line1' => $user->userAddress->address_line1,
                'line2' => $user->userAddress->address_line2,
                'city' => $user->userAddress->city,
                'postal_code' => $user->userAddress->postal_code,
                'country' => $user->userAddress->country,
            ];
        }

        return $userDetails;
    }

}
