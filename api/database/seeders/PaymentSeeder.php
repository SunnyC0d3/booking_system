<?php

namespace Database\Seeders;

use Carbon\Carbon;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Constants\BookingPaymentStatuses;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class PaymentSeeder extends Seeder
{
    public function run(): void
    {
        Log::info('Starting PaymentSeeder...');

        $bookings = Booking::with('user')->get();
        $paymentMethods = PaymentMethod::pluck('id')->toArray();

        if (empty($paymentMethods)) {
            Log::warning('No payment methods found. Creating default payment methods...');
            $this->createDefaultPaymentMethods();
            $paymentMethods = PaymentMethod::pluck('id')->toArray();
        }

        $payments = [];

        foreach ($bookings as $booking) {
            $createdAt = Carbon::parse($booking->created_at);
            $bookingPayments = $this->generatePaymentsForBooking($booking, $paymentMethods, $createdAt);
            $payments = array_merge($payments, $bookingPayments);
        }

        if (!empty($payments)) {
            Payment::insert($payments);
            Log::info('Created ' . count($payments) . ' payment records');
        } else {
            Log::warning('No payments were created');
        }
    }

    /**
     * Generate appropriate payments for a booking based on its payment status
     */
    private function generatePaymentsForBooking(Booking $booking, array $paymentMethods, Carbon $createdAt): array
    {
        $payments = [];
        $paymentMethodId = $paymentMethods[array_rand($paymentMethods)];

        switch ($booking->payment_status) {
            case BookingPaymentStatuses::PAID:
                // Full payment or deposit + final payment
                if ($booking->deposit_amount && $booking->remaining_amount) {
                    // Deposit payment
                    $payments[] = $this->createPaymentRecord(
                        $booking,
                        $paymentMethodId,
                        $booking->deposit_amount / 100, // Convert from pence
                        'deposit',
                        'completed',
                        $createdAt->copy()->addMinutes(rand(5, 30))
                    );

                    // Final payment
                    $payments[] = $this->createPaymentRecord(
                        $booking,
                        $paymentMethodId,
                        $booking->remaining_amount / 100,
                        'final_payment',
                        'completed',
                        $createdAt->copy()->addDays(rand(1, 14))
                    );
                } else {
                    // Full payment
                    $payments[] = $this->createPaymentRecord(
                        $booking,
                        $paymentMethodId,
                        $booking->total_amount / 100,
                        'full_payment',
                        'completed',
                        $createdAt->copy()->addMinutes(rand(5, 60))
                    );
                }
                break;

            case BookingPaymentStatuses::DEPOSIT_PAID:
                // Only deposit paid
                $payments[] = $this->createPaymentRecord(
                    $booking,
                    $paymentMethodId,
                    $booking->deposit_amount / 100,
                    'deposit',
                    'completed',
                    $createdAt->copy()->addMinutes(rand(5, 30))
                );
                break;

            case BookingPaymentStatuses::PENDING:
                // Maybe a failed payment attempt
                if (fake()->boolean(30)) { // 30% chance of failed payment
                    $payments[] = $this->createPaymentRecord(
                        $booking,
                        $paymentMethodId,
                        $booking->total_amount / 100,
                        'full_payment',
                        'failed',
                        $createdAt->copy()->addMinutes(rand(5, 120))
                    );
                }
                break;

            case BookingPaymentStatuses::REFUNDED:
            case BookingPaymentStatuses::PARTIALLY_REFUNDED:
                // Original payment + refund
                $originalAmount = $booking->total_amount / 100;
                $refundAmount = $booking->payment_status === BookingPaymentStatuses::PARTIALLY_REFUNDED
                    ? $originalAmount * fake()->randomFloat(2, 0.3, 0.8) // 30-80% refund
                    : $originalAmount;

                // Original payment
                $payments[] = $this->createPaymentRecord(
                    $booking,
                    $paymentMethodId,
                    $originalAmount,
                    'full_payment',
                    'completed',
                    $createdAt->copy()->addMinutes(rand(5, 60))
                );

                // Refund
                $payments[] = $this->createPaymentRecord(
                    $booking,
                    $paymentMethodId,
                    $refundAmount,
                    'refund',
                    'completed',
                    $createdAt->copy()->addDays(rand(1, 7)),
                    $this->getRefundReason()
                );
                break;
        }

        return $payments;
    }

    /**
     * Create a payment record array
     */
    private function createPaymentRecord(
        Booking $booking,
        int $paymentMethodId,
        float $amount,
        string $paymentType,
        string $status,
        Carbon $processedAt,
        ?string $notes = null
    ): array {
        $gateway = fake()->randomElement(['stripe', 'paypal', 'square', 'bank_transfer']);

        return [
            'booking_id'            => $booking->id,
            'user_id'               => $booking->user_id,
            'payment_method_id'     => $paymentMethodId,
            'amount'                => $amount,
            'status'                => $status,
            'transaction_reference' => $this->generateTransactionReference($gateway),
            'processed_at'          => $processedAt,
            'payment_type'          => $paymentType,
            'payment_notes'         => $notes,
            'gateway'               => $gateway,
            'gateway_payment_id'    => $this->generateGatewayPaymentId($gateway),
            'response_payload'      => $status === 'completed' ? $this->generateSuccessResponse() : $this->generateFailureResponse(),
            'created_at'            => $processedAt->copy()->subMinutes(rand(1, 5)),
            'updated_at'            => $processedAt,
        ];
    }

    /**
     * Generate transaction reference based on gateway
     */
    private function generateTransactionReference(string $gateway): string
    {
        $patterns = [
            'stripe' => 'pi_' . fake()->regexify('[A-Za-z0-9]{24}'),
            'paypal' => 'PAY-' . fake()->regexify('[A-Z0-9]{17}'),
            'square' => 'sq_' . fake()->regexify('[a-z0-9]{22}'),
            'bank_transfer' => 'TXN' . fake()->regexify('[0-9]{12}'),
        ];

        return $patterns[$gateway] ?? 'ref_' . fake()->regexify('[A-Za-z0-9]{16}');
    }

    /**
     * Generate gateway-specific payment ID
     */
    private function generateGatewayPaymentId(string $gateway): string
    {
        $patterns = [
            'stripe' => 'ch_' . fake()->regexify('[A-Za-z0-9]{24}'),
            'paypal' => 'PAYID-' . fake()->regexify('[A-Z0-9]{20}'),
            'square' => fake()->regexify('[a-z0-9]{32}'),
            'bank_transfer' => 'BANK' . fake()->regexify('[0-9]{10}'),
        ];

        return $patterns[$gateway] ?? fake()->regexify('[A-Za-z0-9]{20}');
    }

    /**
     * Generate success response payload
     */
    private function generateSuccessResponse(): string
    {
        return json_encode([
            'status' => 'success',
            'message' => 'Payment processed successfully',
            'transaction_id' => fake()->regexify('[A-Za-z0-9]{16}'),
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Generate failure response payload
     */
    private function generateFailureResponse(): string
    {
        $errors = [
            'insufficient_funds',
            'card_declined',
            'invalid_card',
            'expired_card',
            'processing_error',
            'network_timeout'
        ];

        return json_encode([
            'status' => 'failed',
            'error_code' => fake()->randomElement($errors),
            'message' => 'Payment could not be processed',
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Get a random refund reason
     */
    private function getRefundReason(): string
    {
        $reasons = [
            'Booking cancelled by client',
            'Service cancelled due to weather',
            'Venue unavailable',
            'Date changed by client request',
            'Service quality issue',
            'Client emergency',
            'Vendor cancellation',
            'Force majeure event'
        ];

        return 'Refund: ' . fake()->randomElement($reasons);
    }

    /**
     * Create default payment methods if none exist
     */
    private function createDefaultPaymentMethods(): void
    {
        $methods = [
            ['name' => 'Credit Card'],
            ['name' => 'Debit Card'],
            ['name' => 'PayPal'],
            ['name' => 'Bank Transfer'],
            ['name' => 'Cash'],
        ];

        foreach ($methods as $method) {
            PaymentMethod::firstOrCreate($method);
        }
    }
}
