@extends('emails.layout')

@section('title', 'New Dropship Order')

@section('content')
    <h2 style="color: #1f2937; margin-top: 0;">New Dropship Order - Action Required 📦</h2>

    <p>Dear {{ $supplier['contact_person'] ?? $supplier['name'] }},</p>

    <p>You have received a new dropship order from {{ config('app.name') }} that requires processing.</p>

    <div class="highlight-box">
        <strong>Order Information</strong><br>
        Dropship Order #{{ $dropship_order['id'] }}<br>
        Customer Order #{{ $order['order_number'] ?? $dropship_order['order_id'] }}<br>
        Order Date: {{ $dropship_order['created_at'] }}<br>
        Priority: <span class="status-badge status-processing">Standard</span>
    </div>

    <div class="order-summary">
        <h3 style="margin-top: 0; color: #1f2937;">Customer Information</h3>

        <div class="order-item">
            <div><strong>Customer Name</strong></div>
            <div>{{ $customer['name'] }}</div>
        </div>

        @if($customer['email'])
            <div class="order-item">
                <div><strong>Customer Email</strong></div>
                <div>{{ $customer['email'] }}</div>
            </div>
        @endif
    </div>

    <div class="order-summary">
        <h3 style="margin-top: 0; color: #1f2937;">Items to Fulfill</h3>

        @foreach($items as $item)
            <div class="order-item">
                <div>
                    <strong>{{ $item['product_name'] }}</strong><br>
                    <span style="color: #6b7280;">SKU: {{ $item['supplier_sku'] }}</span><br>
                    <span style="color: #6b7280;">Quantity: {{ $item['quantity'] }}</span>
                    @if($item['product_details'])
                        <br><span style="color: #6b7280; font-size: 14px;">Special Instructions: {{ $item['product_details'] }}</span>
                    @endif
                </div>
                <div style="text-align: right;">
                    <div>{{ $item['unit_price'] }} each</div>
                    <div><strong>{{ $item['total_price'] }}</strong></div>
                </div>
            </div>
        @endforeach

        <div class="order-item">
            <div><strong>Total Order Value</strong></div>
            <div><strong>{{ $total_cost }}</strong></div>
        </div>
    </div>

    <div class="order-summary">
        <h3 style="margin-top: 0; color: #1f2937;">Shipping Address</h3>

        <div style="background-color: #f9fafb; padding: 15px; border-radius: 6px;">
            <strong>{{ $shipping_address['name'] ?? $customer['name'] }}</strong><br>
            {{ $shipping_address['address_line_1'] }}<br>
            @if($shipping_address['address_line_2'])
                {{ $shipping_address['address_line_2'] }}<br>
            @endif
            {{ $shipping_address['city'] }}, {{ $shipping_address['postcode'] }}<br>
            {{ $shipping_address['country'] }}
            @if($shipping_address['phone'])
                <br><strong>Phone:</strong> {{ $shipping_address['phone'] }}
            @endif
        </div>
    </div>

    @if($notes)
        <div style="background-color: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; margin: 20px 0;">
            <strong>📝 Special Instructions:</strong><br>
            {{ $notes }}
        </div>
    @endif

    <div style="background-color: #d1fae5; border-left: 4px solid #10b981; padding: 15px; margin: 20px 0;">
        <h4 style="margin-top: 0; color: #065f46;">📋 Next Steps</h4>
        <ol style="margin: 0; color: #065f46;">
            <li>Verify product availability and pricing</li>
            <li>Confirm the order via your preferred method</li>
            <li>Process and ship the order to the customer</li>
            <li>Provide tracking information once shipped</li>
        </ol>
    </div>

    <div style="text-align: center; margin: 30px 0;">
        @if($integration && $integration['confirm_url'])
            <a href="{{ $integration['confirm_url'] }}" class="btn">Confirm Order</a>
        @endif
        <a href="mailto:{{ config('mail.from.address') }}?subject=Dropship Order {{ $dropship_order['id'] }} - Question" class="btn btn-secondary">Contact Support</a>
    </div>

    <div class="order-summary">
        <h3 style="margin-top: 0; color: #1f2937;">🤝 Partnership Information</h3>

        <div style="background-color: #f3f4f6; padding: 15px; border-radius: 6px;">
            <div style="margin-bottom: 10px;">
                <strong>Your Supplier Account:</strong> {{ $supplier['name'] }}
            </div>
            <div style="margin-bottom: 10px;">
                <strong>Expected Response Time:</strong> Within 24 hours
            </div>
            <div style="margin-bottom: 10px;">
                <strong>Integration Method:</strong> {{ $integration['method'] ?? 'Email' }}
            </div>
            @if($integration && $integration['portal_url'])
                <div>
                    <strong>Supplier Portal:</strong> <a href="{{ $integration['portal_url'] }}" style="color: #2563eb;">Access Portal</a>
                </div>
            @endif
        </div>
    </div>

    <p><strong>Questions?</strong> If you need clarification on any aspect of this order, please contact our supplier support team immediately.</p>

    <p>Thank you for your partnership!</p>

    <p>Best regards,<br>{{ config('app.name') }} Supplier Relations Team<br>
        Email: {{ config('mail.from.address') }}<br>
        Phone: {{ config('app.support_phone', '+44 20 1234 5678') }}</p>
@endsection
