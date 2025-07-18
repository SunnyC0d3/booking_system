@extends('emails.layout')

@section('title', 'Shipping Update - #' . $order['id'])

@section('content')
    <h2 style="color: #1f2937; margin-top: 0;">Shipping Update for Your Order</h2>

    <p>Hi {{ $customer['name'] }},</p>

    <p>We wanted to give you an update on your order shipment. Your package is taking a bit longer than expected to arrive.</p>

    <div class="highlight-box">
        <strong>Shipment Details</strong><br>
        Order #{{ $order['id'] }}<br>
        Tracking Number: {{ $shipment['tracking_number'] }}<br>
        Carrier: {{ $shipment['carrier'] }}<br>
        @if($shipment['service_name'])
            Service: {{ $shipment['service_name'] }}<br>
        @endif
        Original Estimated Delivery: {{ $shipment['estimated_delivery'] }}
    </div>

    <div style="background-color: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; margin: 20px 0;">
        ⏰ <strong>Delivery Status:</strong> Your package is currently {{ $delay['days_overdue'] }} day(s) past the original estimated delivery date.
    </div>

    @if($delay['reason'])
        <div style="margin: 20px 0;">
            <strong>Reason for Delay:</strong><br>
            {{ $delay['reason'] }}
        </div>
    @endif

    @if($delay['new_estimated_delivery'])
        <div style="background-color: #d1fae5; border-left: 4px solid #10b981; padding: 15px; margin: 20px 0;">
            📅 <strong>Updated Delivery Estimate:</strong> {{ $delay['new_estimated_delivery'] }}
        </div>
    @endif

    <div class="order-summary">
        <h3 style="margin-top: 0; color: #1f2937;">Order Summary</h3>

        @foreach($order['items'] as $item)
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
            <div><strong>{{ $order['total_formatted'] }}</strong></div>
        </div>
    </div>

    @if($shipment['tracking_url'])
        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ $shipment['tracking_url'] }}" class="btn">Track Your Package</a>
            <a href="{{ config('app.url') }}/support" class="btn btn-secondary">Contact Support</a>
        </div>
    @endif

    <p><strong>What's Next?</strong></p>
    <ul style="color: #4b5563;">
        <li>We're actively monitoring your shipment with {{ $shipment['carrier'] }}</li>
        <li>You'll receive another update once your package is delivered</li>
        <li>If you have any concerns, our support team is here to help</li>
    </ul>

    <p>We sincerely apologize for any inconvenience this delay may cause. Thank you for your patience and understanding.</p>

    <p>Best regards,<br>The {{ config('app.name') }} Team</p>
@endsection
