<?php

namespace App\Services\V1\Emails;

use App\Mail\OrderConfirmationMail;
use App\Mail\ReturnStatusMail;
use App\Mail\RefundProcessedMail;
use App\Mail\PaymentStatusMail;
use App\Mail\ShippingConfirmationMail;
use App\Mail\DeliveryConfirmationMail;
use App\Mail\ShippingDelayNotificationMail;
use App\Mail\TrackingUpdateMail;
use App\Constants\ShippingStatuses;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class Email
{
    public function sendOrderConfirmation(array $orderData, string $customerEmail): bool
    {
        try {
            Mail::to($customerEmail)->send(new OrderConfirmationMail($orderData));

            Log::info('Order confirmation email sent', [
                'order_id' => $orderData['order']['id'],
                'customer_email' => $customerEmail
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send order confirmation email', [
                'order_id' => $orderData['order']['id'],
                'customer_email' => $customerEmail,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

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

    public function sendShippingConfirmation(array $shippingData, string $customerEmail): bool
    {
        try {
            Mail::to($customerEmail)->send(new ShippingConfirmationMail($shippingData));

            Log::info('Shipping confirmation email sent', [
                'order_id' => $shippingData['order']['id'],
                'tracking_number' => $shippingData['shipment']['tracking_number'] ?? 'N/A',
                'customer_email' => $customerEmail
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send shipping confirmation email', [
                'order_id' => $shippingData['order']['id'],
                'customer_email' => $customerEmail,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    public function sendDeliveryConfirmation(array $deliveryData, string $customerEmail): bool
    {
        try {
            Mail::to($customerEmail)->send(new DeliveryConfirmationMail($deliveryData));

            Log::info('Delivery confirmation email sent', [
                'order_id' => $deliveryData['order']['id'],
                'tracking_number' => $deliveryData['shipment']['tracking_number'] ?? 'N/A',
                'customer_email' => $customerEmail
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send delivery confirmation email', [
                'order_id' => $deliveryData['order']['id'],
                'customer_email' => $customerEmail,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    public function sendShippingDelayNotification(array $delayData, string $customerEmail): bool
    {
        try {
            Mail::to($customerEmail)->send(new ShippingDelayNotificationMail($delayData));

            Log::info('Shipping delay notification email sent', [
                'order_id' => $delayData['order']['id'],
                'tracking_number' => $delayData['shipment']['tracking_number'] ?? 'N/A',
                'days_overdue' => $delayData['delay']['days_overdue'] ?? 'Unknown',
                'customer_email' => $customerEmail
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send shipping delay notification email', [
                'order_id' => $delayData['order']['id'],
                'customer_email' => $customerEmail,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    public function sendTrackingUpdate(array $trackingData, string $customerEmail): bool
    {
        try {
            Mail::to($customerEmail)->send(new TrackingUpdateMail($trackingData));

            Log::info('Tracking update email sent', [
                'order_id' => $trackingData['order']['id'],
                'tracking_number' => $trackingData['shipment']['tracking_number'] ?? 'N/A',
                'status' => $trackingData['tracking']['status'] ?? 'Unknown',
                'customer_email' => $customerEmail
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send tracking update email', [
                'order_id' => $trackingData['order']['id'],
                'customer_email' => $customerEmail,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    public function sendShippingIssueAlert(array $orderData, string $recipientEmail): bool
    {
        try {
            Mail::to($recipientEmail)->send(new ShippingIssueAlertMail($orderData));

            Log::info('Shipping issue alert email sent', [
                'order_id' => $orderData['order']['id'],
                'shipment_id' => $orderData['shipment']['id'],
                'recipient_email' => $recipientEmail,
                'issue_type' => $orderData['shipment']['status']
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send shipping issue alert email', [
                'order_id' => $orderData['order']['id'],
                'shipment_id' => $orderData['shipment']['id'],
                'recipient_email' => $recipientEmail,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    private function getShippingIssueDescription(string $status): string
    {
        return match ($status) {
            'failed' => 'Shipment delivery failed - package may be undeliverable or returned to sender',
            'returned' => 'Package has been returned to sender due to delivery issues',
            'exception' => 'An exception occurred during shipping that requires manual intervention',
            'lost' => 'Package appears to be lost in transit and cannot be located',
            'damaged' => 'Package was damaged during shipping',
            'delayed' => 'Shipment is significantly delayed beyond estimated delivery date',
            default => 'An unknown shipping issue has occurred'
        };
    }

    private function getRecommendedActions(string $status): array
    {
        return match ($status) {
            'failed' => [
                'Contact the customer to verify shipping address',
                'Arrange for package pickup or re-delivery',
                'Consider issuing a refund if multiple delivery attempts failed'
            ],
            'returned' => [
                'Inspect returned package for damage',
                'Contact customer to confirm address and arrange re-shipment',
                'Process refund if customer no longer wants the item'
            ],
            'exception' => [
                'Contact shipping carrier for detailed exception information',
                'Update customer with current status and expected resolution',
                'Monitor shipment closely for status updates'
            ],
            'lost' => [
                'File insurance claim with shipping carrier if applicable',
                'Offer replacement or full refund to customer',
                'Investigate with carrier to locate package'
            ],
            'damaged' => [
                'Document damage with photos if package is returned',
                'File damage claim with shipping carrier',
                'Offer replacement or refund to customer'
            ],
            'delayed' => [
                'Contact carrier for updated delivery estimate',
                'Notify customer of delay and provide new timeline',
                'Consider expedited shipping for replacement if needed'
            ],
            default => [
                'Investigate the specific issue with the shipping carrier',
                'Update customer with current status',
                'Take appropriate action based on carrier response'
            ]
        };
    }

    private function getIssuePriority(string $status): string
    {
        return match ($status) {
            'lost', 'damaged' => 'high',
            'failed', 'returned', 'exception' => 'medium',
            'delayed' => 'low',
            default => 'medium'
        };
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

    public function formatDelayData($shipment, string $reason = null, $newEstimatedDelivery = null): array
    {
        $orderData = $this->formatOrderData($shipment->order);
        $shippingData = $this->formatShippingData($shipment);

        $daysOverdue = $shipment->estimated_delivery
            ? now()->diffInDays($shipment->estimated_delivery)
            : 0;

        return array_merge($shippingData, [
            'delay' => [
                'days_overdue' => $daysOverdue,
                'reason' => $reason,
                'new_estimated_delivery' => $newEstimatedDelivery?->format('M j, Y') ?? null,
            ]
        ]);
    }

    public function formatTrackingData($shipment, array $trackingInfo): array
    {
        $orderData = $this->formatOrderData($shipment->order);
        $shippingData = $this->formatShippingData($shipment);

        return array_merge($shippingData, [
            'tracking' => [
                'status' => $trackingInfo['status'] ?? ShippingStatuses::UNKNOWN,
                'status_label' => ShippingStatuses::getLabel($trackingInfo['status'] ?? ShippingStatuses::UNKNOWN),
                'description' => $trackingInfo['description'] ?? null,
                'location' => $trackingInfo['location'] ?? null,
                'updated_at' => isset($trackingInfo['updated_at'])
                    ? $trackingInfo['updated_at']->format('M j, Y g:i A')
                    : now()->format('M j, Y g:i A'),
                'estimated_delivery' => isset($trackingInfo['estimated_delivery'])
                    ? $trackingInfo['estimated_delivery']->format('M j, Y')
                    : null,
                'events' => $this->formatTrackingEvents($trackingInfo['events'] ?? [])
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

    public function formatShippingData(Shipment $shipment): array
    {
        return [
            'shipment' => [
                'id' => $shipment->id,
                'tracking_number' => $shipment->tracking_number,
                'carrier' => $shipment->carrier,
                'service_name' => $shipment->service_name,
                'status' => $shipment->status,
                'status_label' => $shipment->getStatusLabel(),
                'shipped_at' => $shipment->shipped_at,
                'estimated_delivery' => $shipment->estimated_delivery,
                'tracking_url' => $shipment->getTrackingUrl(),
            ],
            'order' => [
                'id' => $shipment->order->id,
                'order_number' => $shipment->order->order_number ?? $shipment->order->id,
                'total_formatted' => $shipment->order->getTotalFormattedAttribute(),
                'created_at' => $shipment->order->created_at->format('M j, Y g:i A'),
            ],
            'customer' => [
                'name' => $shipment->order->user->name ?? 'Valued Customer',
                'email' => $shipment->order->user->email ?? '',
            ],
            'shipping_address' => $shipment->order->shippingAddress ? [
                'name' => $shipment->order->shippingAddress->name,
                'address_line_1' => $shipment->order->shippingAddress->address_line_1,
                'address_line_2' => $shipment->order->shippingAddress->address_line_2,
                'city' => $shipment->order->shippingAddress->city,
                'postcode' => $shipment->order->shippingAddress->postcode,
                'country' => $shipment->order->shippingAddress->country,
            ] : null,
        ];
    }

    public function formatShipmentData(Shipment $shipment): array
    {
        return $this->formatShippingData($shipment);
    }
}
