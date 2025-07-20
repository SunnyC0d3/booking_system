@extends('emails.layout')

@section('title', 'Dropship Order Shipped - #' . $order['id'])

@section('content')
    <h2 style="color: #1f2937; margin-top: 0;">Your Order Has Shipped! 📦</h2>

    <p>Hi {{ $customer['name'] }},</p>

    <p>Excellent news! Your order has been shipped by our trusted supplier and is on its way to you.</p>

    <div class="highlight-box">
        <strong>Shipping Information</strong><br>
        Order #{{ $order['id'] }}<br>
        Tracking Number: <strong>{{ $dropship_order['tracking_number'] }}</strong><br>
        Carrier: {{ $dropship_order['carrier'] ?? 'Standard Shipping' }}<br>
        Shipped by: {{ $supplier['name'] }}<br>
        Shipped: {{ $dropship_order['shipped_at'] }}
    </div>

    @if($dropship_order['estimated_delivery'])
        <div style="background-color: #d1fae5; border-left: 4px solid #10b981; padding: 15px; margin: 20px 0;">
            🚚 <strong>Estimated Delivery:</strong> {{ $dropship_order['estimated_delivery'] }}
        </div>
    @endif

    <div class="order-summary">
        <h3 style="margin-top: 0; color: #1f2937;">Shipped Items</h3>

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
    </div>

    @if($dropship_order['tracking_url'])
        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ $dropship_order['tracking_url'] }}" class="btn">Track Your Package</a>
        </div>
    @endif

    <p><strong>Package Details:</strong></p>
    <ul style="color: #4b5563;">
        <li>Shipped directly from {{ $supplier['name'] }}</li>
        <li>You can track your package using the tracking number above</li>
        <li>You'll receive delivery confirmation once it arrives</li>
        <li>Contact us if you have any questions about your shipment</li>
    </ul>

    <p>Thank you for choosing {{ config('app.name') }}!</p>

    <p>Best regards,<br>The {{ config('app.name') }} Team</p>
@endsection
