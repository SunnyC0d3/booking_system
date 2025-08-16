<?php

namespace App\Services\V1\Emails;

use App\Mail\ReturnStatusMail;
use App\Mail\RefundProcessedMail;
use App\Mail\PaymentStatusMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class Email
{
    public function sendReturnStatus(array $returnData, string $customerEmail): bool
    {
        try {
            Mail::to($customerEmail)->send(new ReturnStatusMail($returnData));

            Log::info('Return status email sent', [
                'return_id' => $returnData['return']['id'],
                'status' => $returnData['return']['status'],
                'customer_email' => $customerEmail
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send return status email', [
                'return_id' => $returnData['return']['id'],
                'customer_email' => $customerEmail,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    public function formatOrderData($order): array
    {
        return [
            'order' => [
                'id' => $order->id,
                'total_amount' => $order->total_amount,
                'total_formatted' => $this->formatPrice($order->total_amount),
                'shipping_cost' => $order->shipping_cost ?? 0,
                'shipping_cost_formatted' => $this->formatPrice($order->shipping_cost ?? 0),
                'status' => $order->status->name ?? 'Unknown',
                'created_at' => $order->created_at->format('M j, Y g:i A'),
                'items' => $order->orderItems->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'product_name' => $item->product->name ?? 'Unknown Product',
                        'quantity' => $item->quantity,
                        'price' => $item->price,
                        'price_formatted' => $this->formatPrice($item->price),
                        'total_formatted' => $this->formatPrice($item->price * $item->quantity)
                    ];
                })->toArray()
            ],
            'customer' => [
                'email' => $order->user->email,
                'name' => $order->user->name ?? 'Valued Customer'
            ]
        ];
    }

    public function formatReturnData($orderReturn): array
    {
        $orderData = $this->formatOrderData($orderReturn->orderItem->order);

        return array_merge($orderData, [
            'return' => [
                'id' => $orderReturn->id,
                'reason' => $orderReturn->reason,
                'status' => $orderReturn->status->name ?? 'Unknown',
                'created_at' => $orderReturn->created_at->format('M j, Y g:i A'),
                'item' => [
                    'product_name' => $orderReturn->orderItem->product->name ?? 'Unknown Product',
                    'quantity' => $orderReturn->orderItem->quantity,
                    'price_formatted' => $this->formatPrice($orderReturn->orderItem->price)
                ]
            ]
        ]);
    }

    public function formatRefundData($orderRefund): array
    {
        $returnData = $this->formatReturnData($orderRefund->orderReturn);

        return array_merge($returnData, [
            'refund' => [
                'id' => $orderRefund->id,
                'amount' => $orderRefund->amount,
                'amount_formatted' => $this->formatPrice($orderRefund->amount),
                'processed_at' => $orderRefund->processed_at?->format('M j, Y g:i A') ?? 'Processing',
                'notes' => $orderRefund->notes
            ]
        ]);
    }

    public function formatPaymentData($payment): array
    {
        $orderData = $this->formatOrderData($payment->order);

        return array_merge($orderData, [
            'payment' => [
                'id' => $payment->id,
                'amount' => $payment->amount,
                'amount_formatted' => $this->formatPrice($payment->amount),
                'status' => $payment->status,
                'method' => $payment->payment_method->name ?? 'Unknown',
                'processed_at' => $payment->processed_at?->format('M j, Y g:i A') ?? 'Processing'
            ]
        ]);
    }

    private function formatTrackingEvents(array $events): array
    {
        return array_map(function ($event) {
            return [
                'timestamp' => isset($event['timestamp'])
                    ? $event['timestamp']->format('M j, g:i A')
                    : 'Unknown',
                'description' => $event['description'] ?? 'Status update',
                'location' => $event['location'] ?? null,
                'status' => $event['status'] ?? null
            ];
        }, $events);
    }

    private function formatPrice(int $priceInPennies): string
    {
        return '£' . number_format($priceInPennies / 100, 2);
    }

    public function sendRefundProcessed(array $refundData, string $customerEmail): bool
    {
        try {
            Mail::to($customerEmail)->send(new RefundProcessedMail($refundData));

            Log::info('Refund processed email sent', [
                'refund_id' => $refundData['refund']['id'],
                'amount' => $refundData['refund']['amount'],
                'customer_email' => $customerEmail
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send refund processed email', [
                'refund_id' => $refundData['refund']['id'],
                'customer_email' => $customerEmail,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    public function sendPaymentStatus(array $paymentData, string $customerEmail): bool
    {
        try {
            Mail::to($customerEmail)->send(new PaymentStatusMail($paymentData));

            Log::info('Payment status email sent', [
                'payment_id' => $paymentData['payment']['id'],
                'status' => $paymentData['payment']['status'],
                'customer_email' => $customerEmail
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send payment status email', [
                'payment_id' => $paymentData['payment']['id'],
                'customer_email' => $customerEmail,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }
}
