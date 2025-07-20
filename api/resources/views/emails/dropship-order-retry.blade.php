@extends('emails.layout')

@section('title', 'Order Retry Attempt - #' . $order['id'])

@section('content')
    <h2 style="color: #1f2937; margin-top: 0;">Order Retry Update</h2>

    <p>Hi {{ $customer['name'] }},</p>

    <p>We're working to resolve an issue with your order and are making another attempt to process it with our supplier.</p>

    <div class="highlight-box">
        <strong>Retry Information</strong><br>
        Order #{{ $order['id'] }}<br>
        Dropship Order #{{ $dropship_order['id'] }}<br>
        Retry Attempt: <strong>#{{ $retry['attempt'] }}</strong><br>
        Initiated: {{ $retry['initiated_at'] }}<br>
        Supplier: {{ $supplier['name'] }}
    </div>

    @if($retry['reason'])
        <div style="background-color: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; margin: 20px 0;">
            <strong>Reason for Retry:</strong><br>
            {{ $retry['reason'] }}
        </div>
    @endif

    <div class="order-summary">
        <h3 style="margin-top: 0; color: #1f2937;">Order Items Being Retried</h3>

        @foreach($dropship_order['items'] as $item)
            <div class="order-item">
                <div>
                    <strong>{{ $item['product_name'] }}</strong><br>
                    <span style="color: #6b7280;">Quantity: {{ $item['quantity'] }}</span>
                </div>
                <div style="text-align: right;">
                    <strong>{{ $item['total_formatted'] }}</strong>
                </div>
            </div>
        @endforeach

        <div class="order-item">
            <div><strong>Total</strong></div>
            <div><strong>{{ $dropship_order['total_retail_formatted'] }}</strong></div>
        </div>
    </div>

    <div style="background-color: #d1fae5; border-left: 4px solid #10b981; padding: 15px; margin: 20px 0;">
        💪 <strong>What We're Doing:</strong><br>
        We've automatically retried processing your order with {{ $supplier['name'] }}. This retry attempt addresses the previous issue and should result in successful order fulfillment.
    </div>

    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ config('app.url') }}/orders/{{ $order['id'] }}" class="btn">View Order Status</a>
        <a href="{{ config('app.url') }}/support" class="btn btn-secondary">Contact Support</a>
    </div>

    <p><strong>What Happens Next:</strong></p>
    <ul style="color: #4b5563;">
        <li>We'll monitor this retry attempt closely</li>
        <li>You'll receive confirmation once the order is successfully processed</li>
        <li>If this attempt is unsuccessful, our team will contact you directly</li>
        <li>Your payment remains secure during this process</li>
    </ul>

    <p>We appreciate your patience as we work to fulfill your order. Our team is committed to ensuring you receive your items as quickly as possible.</p>

    <p>Best regards,<br>The {{ config('app.name') }} Team</p>
@endsection
