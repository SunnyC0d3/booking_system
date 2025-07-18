@extends('emails.layout')

@section('title', 'Order Shipped - #' . $order['id'])

@section('content')
    <h2 style="color: #1f2937; margin-top: 0;">Your Order Has Shipped! 📦</h2>

    <p>Hi {{ $customer['name'] }},</p>

    <p>Great news! Your order has been shipped and is on its way to you.</p>

    <div class="highlight-box">
        <strong>Shipping Details</strong><br>
        Order #{{ $order['id'] }}<br>
        Tracking Number: <strong>{{ $shipment['tracking_number'] }}</strong><br>
        Carrier: {{ $shipment['carrier'] }}<br>
        @if($shipment['service_name'])
            Service: {{ $shipment['service_name'] }}<br>
        @endif
        Shipped: {{ $shipment['shipped_at'] }}
    </div>

    @if($shipment['estimated_delivery'])
        <div style="background-color: #d1fae5; border-left: 4px solid #10b981; padding: 15px; margin: 20px 0;">
            🚚 <strong>Estimated Delivery:</strong> {{ $shipment['estimated_delivery'] }}
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

    @if($shipment['tracking_url'])
        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ $shipment['tracking_url'] }}" class="btn">Track Your Package</a>
        </div>
    @endif

    @if($shipping_address)
        <div style="margin: 20px 0;">
            <strong>Shipping Address:</strong><br>
            {{ $shipping_address['name'] }}<br>
            {{ $shipping_address['full_address'] }}
        </div>
    @endif

    <p>You'll receive another email once your package is delivered. If you have any questions about your shipment, please don't hesitate to contact us.</p>

    <p>Best regards,<br>The {{ config('app.name') }} Team</p>
@endsection
