@extends('emails.layout')

@section('title', 'Delivery Update - #' . $order['id'])

@section('content')
    <h2 style="color: #1f2937; margin-top: 0;">Delivery Update for Your Order</h2>

    <p>Hi {{ $customer['name'] }},</p>

    <p>We wanted to give you an update on your order delivery. Your package from our supplier is taking longer than initially expected.</p>

    <div class="highlight-box">
        <strong>Order Information</strong><br>
        Order #{{ $order['id'] }}<br>
        Supplier: {{ $supplier['name'] }}<br>
        Original Estimated Delivery: {{ $dropship_order['original_estimated_delivery'] }}<br>
        Current Status: <span class="status-badge status-processing">{{ $dropship_order['status'] }}</span>
    </div>

    <div style="background-color: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; margin: 20px 0;">
        ⏰ <strong>Delay Information:</strong><br>
        Your package is currently {{ $delay['days_delayed'] }} day(s) past the original estimated delivery date.
    </div>

    @if($delay['reason'])
        <div style="margin: 20px 0;">
            <strong>Reason for Delay:</strong><br>
            {{ $delay['reason'] }}
        </div>
    @endif

    @if($dropship_order['new_estimated_delivery'])
        <div style="background-color: #d1fae5; border-left: 4px solid #10b981; padding: 15px; margin: 20px 0;">
            📅 <strong>Updated Delivery Estimate:</strong> {{ $dropship_order['new_estimated_delivery'] }}
        </div>
    @endif

    <div class="order-summary">
        <h3 style="margin-top: 0; color: #1f2937;">Delayed Items</h3>

        @foreach($dropship_order['items'] as $item)
            <div class="order-item">
                <div>
                    <strong>{{ $item['product_name'] }}</strong><br>
                    <span style="color: #6b7280;">Supplier: {{ $supplier['name'] }}</span><br>
                    <span style="color: #6b7280;">Quantity: {{ $item['quantity'] }}</span>
                </div>
                <div style="text-align: right;">
                    <strong>{{ $item['total_formatted'] }}</strong>
                </div>
            </div>
        @endforeach
    </div>

    @if($dropship_order['tracking_number'])
        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ $dropship_order['tracking_url'] ?? '#' }}" class="btn">Track Package</a>
            <a href="{{ config('app.url') }}/support" class="btn btn-secondary">Contact Support</a>
        </div>
    @endif

    <p><strong>What's Being Done:</strong></p>
    <ul style="color: #4b5563;">
        <li>We're in direct contact with {{ $supplier['name'] }} for updates</li>
        <li>Your order is being prioritized for the next available shipment</li>
        <li>We'll notify you immediately when it ships</li>
        <li>Our support team is monitoring the situation closely</li>
    </ul>

    <p>We sincerely apologize for this delay and any inconvenience it may cause. Thank you for your patience and understanding.</p>

    <p>Best regards,<br>The {{ config('app.name') }} Team</p>
@endsection
