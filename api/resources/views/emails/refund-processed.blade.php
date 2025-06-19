@extends('emails.layout')

@section('title', 'Refund Processed - Order #' . $order['id'])

@section('content')
    <h2 style="color: #1f2937; margin-top: 0;">Refund Processed</h2>

    <p>Hi {{ $customer['name'] }},</p>

    <p>Great news! Your refund has been processed successfully.</p>

    <div class="highlight-box">
        <strong>Refund Details</strong><br>
        Refund Amount: <strong style="color: #065f46;">{{ $refund['amount_formatted'] }}</strong><br>
        Processed: {{ $refund['processed_at'] }}<br>
        Refund ID: {{ $refund['id'] }}
    </div>

    <div class="order-summary">
        <h3 style="margin-top: 0; color: #1f2937;">Original Order</h3>

        <div class="order-item">
            <div><strong>Order #{{ $order['id'] }}</strong></div>
            <div>{{ $order['created_at'] }}</div>
        </div>

        <div class="order-item">
            <div>
                <strong>{{ $return['item']['product_name'] }}</strong><br>
                <span style="color: #6b7280;">Quantity: {{ $return['item']['quantity'] }}</span>
            </div>
            <div style="text-align: right;">
                <strong>{{ $refund['amount_formatted'] }}</strong>
            </div>
        </div>
    </div>

    <p style="background-color: #d1fae5; border-left: 4px solid #10b981; padding: 15px; margin: 20px 0;">
        💳 <strong>Your refund will appear on your original payment method within 3-5 business days.</strong>
    </p>

    @if($refund['notes'])
        <div style="margin: 20px 0;">
            <strong>Additional Notes:</strong><br>
            {{ $refund['notes'] }}
        </div>
    @endif

    <div style="text-align: center; margin: 30px 0;">
        <a href="#" class="btn">View Order History</a>
    </div>

    <p>Thank you for your patience during the return process.</p>

    <p>Best regards,<br>The {{ config('app.name') }} Team</p>
@endsection
