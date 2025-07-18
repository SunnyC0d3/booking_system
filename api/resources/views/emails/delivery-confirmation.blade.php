@extends('emails.layout')

@section('title', 'Package Delivered - #' . $order['id'])

@section('content')
    <h2 style="color: #1f2937; margin-top: 0;">Package Delivered! 🎉</h2>

    <p>Hi {{ $customer['name'] }},</p>

    <p>Great news! Your package has been successfully delivered.</p>

    <div class="highlight-box">
        <strong>Delivery Confirmation</strong><br>
        Order #{{ $order['id'] }}<br>
        Tracking Number: {{ $shipment['tracking_number'] }}<br>
        Delivered: <strong>{{ $shipment['delivered_at'] }}</strong><br>
        Carrier: {{ $shipment['carrier'] }}
    </div>

    @if($shipping_address)
        <div style="background-color: #d1fae5; border-left: 4px solid #10b981; padding: 15px; margin: 20px 0;">
            📍 <strong>Delivered to:</strong><br>
            {{ $shipping_address['name'] }}<br>
            {{ $shipping_address['full_address'] }}
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

        @if($order['shipping_cost'] > 0)
            <div class="order-item">
                <div>Shipping</div>
                <div>{{ $order['shipping_cost_formatted'] }}</div>
            </div>
        @endif

        <div class="order-item">
            <div><strong>Total</strong></div>
            <div><strong>{{ $order['total_formatted'] }}</strong></div>
        </div>
    </div>

    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ config('app.url') }}/orders/{{ $order['id'] }}" class="btn">View Order Details</a>
        <a href="{{ config('app.url') }}/products" class="btn btn-secondary">Continue Shopping</a>
    </div>

    <div style="background-color: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; margin: 20px 0;">
        💭 <strong>How was your experience?</strong><br>
        We'd love to hear about your purchase! Consider leaving a review to help other customers.
    </div>

    <p><strong>Need to return something?</strong> You have 30 days from delivery to initiate a return. Visit your order history for return options.</p>

    <p>Thank you for choosing {{ config('app.name') }}. We hope you love your purchase!</p>

    <p>Best regards,<br>The {{ config('app.name') }} Team</p>
@endsection
