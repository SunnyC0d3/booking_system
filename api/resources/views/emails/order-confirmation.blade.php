@extends('emails.layout')

@section('title', 'Order Confirmation #' . $order['id'])

@section('content')
    <h2 style="color: #1f2937; margin-top: 0;">Order Confirmation</h2>

    <p>Hi {{ $customer['name'] }},</p>

    <p>Thank you for your order! We've received your payment and are preparing your items for shipment.</p>

    <div class="highlight-box">
        <strong>Order #{{ $order['id'] }}</strong><br>
        Order Date: {{ $order['created_at'] }}<br>
        Status: <span class="status-badge status-completed">{{ $order['status'] }}</span>
    </div>

    <div class="order-summary">
        <h3 style="margin-top: 0; color: #1f2937;">Order Summary</h3>

        @foreach($order['items'] as $item)
            <div class="order-item">
                <div>
                    <strong>{{ $item['product_name'] }}</strong><br>
                    <span style="color: #6b7280;">Quantity: {{ $item['quantity'] }}</span>
                </div>
                <div style="text-align: right;">
                    <div>{{ $item['price_formatted'] }} each</div>
                    <div><strong>{{ $item['total_formatted'] }}</strong></div>
                </div>
            </div>
        @endforeach

        <div class="order-item">
            <div><strong>Total</strong></div>
            <div><strong>{{ $order['total_formatted'] }}</strong></div>
        </div>
    </div>

    <div style="text-align: center; margin: 30px 0;">
        <a href="#" class="btn">Track Your Order</a>
    </div>

    <p>We'll send you another email with tracking information once your order ships.</p>

    <p>Best regards,<br>The {{ config('app.name') }} Team</p>
@endsection





