@extends('emails.layout')

@section('title', 'Dropship Order Failed')

@section('content')
    <h2 style="color: #dc2626; margin-top: 0;">🚨 URGENT: Dropship Order Failed</h2>

    <div style="background-color: #fef2f2; border-left: 4px solid #dc2626; padding: 15px; margin: 20px 0;">
        <strong>Order #{{ $dropship_order['id'] }} has failed after {{ $error['attempts'] }} attempts.</strong>
    </div>

    <div class="order-summary">
        <h3 style="margin-top: 0; color: #1f2937;">Order Details</h3>

        <div class="order-item">
            <div><strong>Dropship Order ID</strong></div>
            <div>#{{ $dropship_order['id'] }}</div>
        </div>

        <div class="order-item">
            <div><strong>Original Order ID</strong></div>
            <div>#{{ $dropship_order['order_id'] }}</div>
        </div>

        <div class="order-item">
            <div><strong>Supplier</strong></div>
            <div>{{ $dropship_order['supplier_name'] }}</div>
        </div>

        <div class="order-item">
            <div><strong>Customer</strong></div>
            <div>{{ $customer['name'] }} ({{ $customer['email'] }})</div>
        </div>

        <div class="order-item">
            <div><strong>Integration Method</strong></div>
            <div>{{ ucfirst($error['integration_type']) }}</div>
        </div>

        <div class="order-item">
            <div><strong>Created</strong></div>
            <div>{{ $dropship_order['created_at'] }}</div>
        </div>
    </div>

    <div style="background-color: #fef2f2; border-radius: 6px; padding: 20px; margin: 20px 0;">
        <h4 style="margin-top: 0; color: #dc2626;">Error Details</h4>
        <div style="color: #7f1d1d; line-height: 1.6;">
            <strong>Message:</strong> {{ $error['message'] }}<br>
            <strong>Attempts Made:</strong> {{ $error['attempts'] }}<br>
            <strong>Failed At:</strong> {{ $error['occurred_at'] }}
        </div>
    </div>

    <div style="background-color: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; margin: 20px 0;">
        <h4 style="margin-top: 0; color: #92400e;">⚠️ Immediate Actions Required</h4>
        <ol style="margin: 0; color: #92400e;">
            <li>Check supplier integration status and configuration</li>
            <li>Verify supplier is operational and accepting orders</li>
            <li>Contact customer about the delay</li>
            <li>Consider manual order placement with supplier</li>
            <li>Update integration settings if necessary</li>
        </ol>
    </div>

    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ config('app.url') }}/admin/dropship-orders/{{ $dropship_order['id'] }}" class="btn">View Order Details</a>
        <a href="{{ config('app.url') }}/admin/suppliers" class="btn btn-secondary">Manage Suppliers</a>
    </div>

    <p><strong>Customer Impact:</strong> This order failure may result in customer dissatisfaction. Please address urgently.</p>

    <p>Best regards,<br>{{ config('app.name') }} System Alert</p>
@endsection
