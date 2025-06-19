@extends('emails.layout')

@section('title', 'Payment Update - Order #' . $order['id'])

@section('content')
    <h2 style="color: #1f2937; margin-top: 0;">Payment Update</h2>

    <p>Hi {{ $customer['name'] }},</p>

    @if($payment['status'] === 'failed')
        <p>We encountered an issue processing your payment for Order #{{ $order['id'] }}.</p>

        <div style="background-color: #fee2e2; border-left: 4px solid #ef4444; padding: 15px; margin: 20px 0;">
            ❌ <strong>Payment Failed</strong><br>
            We were unable to process your payment of {{ $payment['amount_formatted'] }}.
        </div>

        <p>This could be due to:</p>
        <ul>
            <li>Insufficient funds</li>
            <li>Expired card</li>
            <li>Bank security measures</li>
            <li>Incorrect payment details</li>
        </ul>

        <div style="text-align: center; margin: 30px 0;">
            <a href="#" class="btn">Retry Payment</a>
            <a href="#" class="btn btn-secondary">Update Payment Method</a>
        </div>

        <p><strong>Your order is currently on hold</strong> and will be cancelled if payment is not received within 24 hours.</p>

    @elseif($payment['status'] === 'succeeded')
        <p>Great news! Your payment has been successfully processed.</p>

        <div style="background-color: #d1fae5; border-left: 4px solid #10b981; padding: 15px; margin: 20px 0;">
            ✅ <strong>Payment Confirmed</strong><br>
            {{ $payment['amount_formatted'] }} has been charged to your {{ $payment['method'] }}.
        </div>

        <div style="text-align: center; margin: 30px 0;">
            <a href="#" class="btn">View Order Details</a>
        </div>

        <p>We're now preparing your order for shipment and will send tracking information soon.</p>
    @endif

    <div class="order-summary">
        <h3 style="margin-top: 0; color: #1f2937;">Payment Details</h3>

        <div class="order-item">
            <div><strong>Order #{{ $order['id'] }}</strong></div>
            <div>{{ $order['created_at'] }}</div>
        </div>

        <div class="order-item">
            <div>Payment Method</div>
            <div>{{ $payment['method'] }}</div>
        </div>

        <div class="order-item">
            <div>Amount</div>
            <div><strong>{{ $payment['amount_formatted'] }}</strong></div>
        </div>

        <div class="order-item">
            <div>Status</div>
            <div>
            <span class="status-badge status-{{ $payment['status'] === 'succeeded' ? 'completed' : 'rejected' }}">
                {{ ucfirst($payment['status']) }}
            </span>
            </div>
        </div>
    </div>

    <p>If you have any questions about this payment, please don't hesitate to contact our support team.</p>

    <p>Best regards,<br>The {{ config('app.name') }} Team</p>
@endsection
