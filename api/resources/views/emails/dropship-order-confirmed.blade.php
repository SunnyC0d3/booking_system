@extends('emails.layout')

@section('title', 'Dropship Order Confirmed - #' . $dropship_order['id'])

@section('content')
    <h2 style="color: #1f2937; margin-top: 0;">Your Order Has Been Confirmed! ✅</h2>

    <p>Hi {{ $customer['name'] }},</p>

    <p>Great news! Your order has been confirmed by our supplier and is being prepared for shipment.</p>

    <div class="highlight-box">
        <strong>Order Confirmation</strong><br>
        Order #{{ $order['id'] }}<br>
        Dropship Order #{{ $dropship_order['id'] }}<br>
        Supplier: {{ $supplier['name'] }}<br>
        Supplier Order ID: {{ $dropship_order['supplier_order_id'] }}<br>
        Confirmed: {{ $dropship_order['confirmed_at'] }}
    </div>

    @if($dropship_order['estimated_delivery'])
        <div style="background-color: #d1fae5; border-left: 4px solid #10b981; padding: 15px; margin: 20px 0;">
            🚚 <strong>Estimated Delivery:</strong> {{ $dropship_order['estimated_delivery'] }}
        </div>
    @endif

    <div class="order-summary">
        <h3 style="margin-top: 0; color: #1f2937;">Order Details</h3>

        @foreach($dropship_order['items'] as $item)
            <div class="order-item">
                <div>
                    <strong>{{ $item['product_name'] }}</strong><br>
                    <span style="color: #6b7280;">SKU: {{ $item['supplier_sku'] }}</span><br>
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

    @if($shipping_address)
        <div style="margin: 20px 0;">
            <strong>Shipping Address:</strong><br>
            {{ $shipping_address['name'] }}<br>
            {{ $shipping_address['address_line_1'] }}<br>
            {{ $shipping_address['city'] }}, {{ $shipping_address['postcode'] }}<br>
            {{ $shipping_address['country'] }}
        </div>
    @endif

    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ config('app.url') }}/orders/{{ $order['id'] }}" class="btn">Track Your Order</a>
    </div>

    <p><strong>What's Next?</strong></p>
    <ul style="color: #4b5563;">
        <li>Your order is being prepared by {{ $supplier['name'] }}</li>
        <li>You'll receive tracking information once shipped</li>
        <li>Delivery is expected {{ $dropship_order['estimated_delivery'] ?? 'within 5-7 business days' }}</li>
    </ul>

    <p>Thank you for your order!</p>

    <p>Best regards,<br>The {{ config('app.name') }} Team</p>
@endsection
