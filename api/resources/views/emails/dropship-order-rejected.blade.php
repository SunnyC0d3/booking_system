@extends('emails.layout')

@section('title', 'Order Update Required - #' . $order['id'])

@section('content')
    <h2 style="color: #1f2937; margin-top: 0;">Order Update - Action Required</h2>

    <p>Hi {{ $customer['name'] }},</p>

    <p>We're writing to inform you about an issue with your recent order that requires your attention.</p>

    <div style="background-color: #fee2e2; border-left: 4px solid #ef4444; padding: 15px; margin: 20px 0;">
        ⚠️ <strong>Supplier Unable to Fulfill Order</strong><br>
        Unfortunately, our supplier {{ $supplier['name'] }} is unable to fulfill part of your order at this time.
    </div>

    <div class="highlight-box">
        <strong>Order Information</strong><br>
        Order #{{ $order['id'] }}<br>
        Dropship Order #{{ $dropship_order['id'] }}<br>
        Supplier: {{ $supplier['name'] }}<br>
        Issue Date: {{ $dropship_order['rejected_at'] }}
    </div>

    @if($dropship_order['rejection_reason'])
        <div style="margin: 20px 0;">
            <strong>Reason:</strong><br>
            {{ $dropship_order['rejection_reason'] }}
        </div>
    @endif

    <div class="order-summary">
        <h3 style="margin-top: 0; color: #1f2937;">Affected Items</h3>

        @foreach($dropship_order['items'] as $item)
            <div class="order-item">
                <div>
                    <strong>{{ $item['product_name'] }}</strong><br>
                    <span style="color: #6b7280;">Quantity: {{ $item['quantity'] }}</span><br>
                    <span class="status-badge status-rejected">{{ $item['status'] }}</span>
                </div>
                <div style="text-align: right;">
                    <strong>{{ $item['total_formatted'] }}</strong>
                </div>
            </div>
        @endforeach

        <div class="order-item">
            <div><strong>Affected Total</strong></div>
            <div><strong>{{ $dropship_order['total_retail_formatted'] }}</strong></div>
        </div>
    </div>

    <div style="background-color: #d1fae5; border-left: 4px solid #10b981; padding: 15px; margin: 20px 0;">
        💡 <strong>What We're Doing:</strong><br>
        We're actively working to find an alternative supplier or solution for your order. Our team will update you within 24 hours with next steps.
    </div>

    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ config('app.url') }}/orders/{{ $order['id'] }}" class="btn">View Order Status</a>
        <a href="{{ config('app.url') }}/support" class="btn btn-secondary">Contact Support</a>
    </div>

    <p><strong>Your Options:</strong></p>
    <ul style="color: #4b5563;">
        <li>Wait for us to find an alternative supplier (recommended)</li>
        <li>Choose a different product variant if available</li>
        <li>Request a full refund for the affected items</li>
        <li>Contact our support team for personalized assistance</li>
    </ul>

    <p>We sincerely apologize for this inconvenience and appreciate your patience while we resolve this issue.</p>

    <p>Best regards,<br>The {{ config('app.name') }} Team</p>
@endsection
