<?php

namespace Database\Factories;

use App\Models\PaymentMethod;
use App\Models\User;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        $paymentTypes = ['deposit', 'final_payment', 'full_payment', 'refund'];
        $statuses = ['pending', 'completed', 'failed', 'cancelled', 'refunded'];
        $gateways = ['stripe', 'paypal', 'square', 'bank_transfer'];

        return [
            'booking_id'            => Booking::factory(),
            'user_id'               => User::factory(),
            'payment_method_id'     => PaymentMethod::factory(),
            'amount'                => fake()->randomFloat(2, 50.00, 2000.00), // £50 to £2000
            'status'                => fake()->randomElement($statuses),
            'transaction_reference' => $this->generateTransactionReference(),
            'processed_at'          => Carbon::now()->subMinutes(fake()->numberBetween(0, 1440)), // Within last 24 hours
            'payment_type'          => fake()->randomElement($paymentTypes),
            'payment_notes'         => fake()->optional(0.3)->sentence(), // 30% chance of notes
            'gateway'               => fake()->randomElement($gateways),
            'gateway_payment_id'    => $this->generateGatewayPaymentId(),
            'response_payload'      => fake()->optional(0.7)->text(200), // 70% chance of response data
            'created_at'            => Carbon::now(),
            'updated_at'            => Carbon::now(),
        ];
    }

    /**
     * Generate a realistic transaction reference based on gateway
     */
    private function generateTransactionReference(): string
    {
        $prefixes = ['pi_', 'ch_', 'txn_', 'pay_', 'ref_'];
        $prefix = fake()->randomElement($prefixes);

        return $prefix . fake()->regexify('[A-Za-z0-9]{24}');
    }

    /**
     * Generate gateway-specific payment ID
     */
    private function generateGatewayPaymentId(): string
    {
        $patterns = [
            'stripe' => 'pi_' . fake()->regexify('[A-Za-z0-9]{24}'),
            'paypal' => 'PAY-' . fake()->regexify('[A-Z0-9]{17}'),
            'square' => fake()->regexify('[a-z0-9]{32}'),
            'bank'   => 'TXN' . fake()->regexify('[0-9]{12}'),
        ];

        return fake()->randomElement($patterns);
    }

    /**
     * State for deposit payments
     */
    public function deposit(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_type' => 'deposit',
            'amount' => fake()->randomFloat(2, 25.00, 500.00), // Smaller amounts for deposits
            'status' => fake()->randomElement(['completed', 'pending']),
        ]);
    }

    /**
     * State for final payments
     */
    public function finalPayment(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_type' => 'final_payment',
            'amount' => fake()->randomFloat(2, 100.00, 1500.00),
            'status' => fake()->randomElement(['completed', 'pending']),
        ]);
    }

    /**
     * State for full payments (no deposit)
     */
    public function fullPayment(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_type' => 'full_payment',
            'amount' => fake()->randomFloat(2, 150.00, 2000.00),
            'status' => 'completed',
        ]);
    }

    /**
     * State for refunds
     */
    public function refund(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_type' => 'refund',
            'amount' => fake()->randomFloat(2, 25.00, 1000.00),
            'status' => 'completed',
            'payment_notes' => 'Refund processed due to ' . fake()->randomElement([
                    'cancellation',
                    'service issue',
                    'venue change',
                    'date change',
                    'customer request'
                ]),
        ]);
    }

    /**
     * State for failed payments
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'payment_notes' => 'Payment failed: ' . fake()->randomElement([
                    'Insufficient funds',
                    'Card declined',
                    'Invalid card details',
                    'Payment timeout',
                    'Bank error'
                ]),
        ]);
    }
}
