@extends('emails.layout')

@section('title', 'Shipping Issue Alert - Order #' . $order['id'])

@section('content')
    <h2 style="color: #1f2937; margin-top: 0;">
        @if($shipment['priority_level'] === 'high')
            🚨 URGENT: Shipping Issue Detected
        @elseif($shipment['priority_level'] === 'medium')
            ⚠️ Shipping Issue Alert
        @else
            📦 Shipping Update Required
        @endif
    </h2>

    <p>Hi Admin Team,</p>

    <p>A shipping issue has been detected that requires immediate attention.</p>

    <div class="highlight-box" style="background-color: {{ $shipment['priority_level'] === 'high' ? '#fee2e2' : ($shipment['priority_level'] === 'medium' ? '#fef3c7' : '#dbeafe') }}; border-left-color: {{ $shipment['priority_level'] === 'high' ? '#ef4444' : ($shipment['priority_level'] === 'medium' ? '#f59e0b' : '#3b82f6') }};">
        <strong>Issue Summary</strong><br>
        Status: <span class="status-badge status-{{ $shipment['status'] === 'failed' || $shipment['status'] === 'returned' ? 'rejected' : 'processing' }}">{{ strtoupper($shipment['status']) }}</span><br>
        Priority: <span style="color: {{ $shipment['priority_level'] === 'high' ? '#dc2626' : ($shipment['priority_level'] === 'medium' ? '#d97706' : '#2563eb') }}; font-weight: 600;">{{ strtoupper($shipment['priority_level']) }}</span><br>
        Detected: {{ now()->format('M j, Y g:i A') }}
    </div>

    <div class="order-summary">
        <h3 style="margin-top: 0; color: #1f2937;">Shipment Details</h3>

        <div class="order-item">
            <div><strong>Order #{{ $order['id'] }}</strong></div>
            <div>{{ $order['created_at'] }}</div>
        </div>

        <div class="order-item">
            <div><strong>Shipment ID</strong></div>
            <div>#{{ $shipment['id'] }}</div>
        </div>

        <div class="order-item">
            <div><strong>Customer</strong></div>
            <div>{{ $customer['name'] }} ({{ $customer['email'] }})</div>
        </div>

        @if($shipment['tracking_number'])
            <div class="order-item">
                <div><strong>Tracking Number</strong></div>
                <div>
                    @if($shipment['tracking_url'])
                        <a href="{{ $shipment['tracking_url'] }}" style="color: #2563eb; text-decoration: none;">{{ $shipment['tracking_number'] }}</a>
                    @else
                        {{ $shipment['tracking_number'] }}
                    @endif
                </div>
            </div>
        @endif

        <div class="order-item">
            <div><strong>Shipped Date</strong></div>
            <div>{{ $shipment['shipped_at'] ?? 'Not shipped' }}</div>
        </div>

        @if($shipment['estimated_delivery'])
            <div class="order-item">
                <div><strong>Expected Delivery</strong></div>
                <div>{{ $shipment['estimated_delivery'] }}</div>
            </div>
        @endif

        <div class="order-item">
            <div><strong>Order Value</strong></div>
            <div><strong>{{ $order['total_formatted'] }}</strong></div>
        </div>
    </div>

    <div style="background-color: #f9fafb; border-left: 4px solid #ef4444; padding: 15px; margin: 20px 0;">
        <h4 style="margin-top: 0; color: #dc2626;">Issue Description</h4>
        <p style="margin: 0; color: #374151;">{{ $shipment['issue_description'] }}</p>
    </div>

    @if($shipment['notes'])
        <div style="background-color: #f3f4f6; border-radius: 6px; padding: 15px; margin: 20px 0;">
            <h4 style="margin-top: 0; color: #374151;">Shipment Notes:</h4>
            <p style="margin: 0; color: #4b5563;">{{ $shipment['notes'] }}</p>
        </div>
    @endif

    @if($shipment['carrier_data'] && isset($shipment['carrier_data']['tracking_history']))
        <div class="order-summary">
            <h3 style="margin-top: 0; color: #1f2937;">Recent Tracking Events</h3>
            @foreach(array_slice($shipment['carrier_data']['tracking_history'], -3) as $event)
                <div style="padding: 10px 0; border-bottom: 1px solid #e5e7eb;">
                    <div style="font-weight: 600; color: #1f2937;">{{ $event['status'] ?? 'Unknown' }}</div>
                    <div style="color: #6b7280; font-size: 14px;">
                        {{ $event['location'] ?? '' }} - {{ $event['datetime'] ?? 'Unknown time' }}
                    </div>
                    @if(isset($event['status_details']))
                        <div style="color: #4b5563; font-size: 14px; margin-top: 4px;">{{ $event['status_details'] }}</div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    <div class="order-summary">
        <h3 style="margin-top: 0; color: #1f2937;">📋 Recommended Actions</h3>
        @foreach($shipment['recommended_actions'] as $index => $action)
            <div style="padding: 8px 0; border-bottom: {{ $loop->last ? 'none' : '1px solid #e5e7eb' }};">
                <div style="display: flex; align-items: flex-start;">
                    <span style="background-color: #2563eb; color: white; border-radius: 50%; width: 20px; height: 20px; display: inline-flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 600; margin-right: 10px; flex-shrink: 0;">{{ $index + 1 }}</span>
                    <span style="color: #374151;">{{ $action }}</span>
                </div>
            </div>
        @endforeach
    </div>

    <div class="order-summary">
        <h3 style="margin-top: 0; color: #1f2937;">Order Items</h3>
        @foreach($order['items'] as $item)
            <div class="order-item">
                <div>
                    <strong>{{ $item['product_name'] }}</strong><br>
                    <span style="color: #6b7280;">Qty: {{ $item['quantity'] }}</span>
                </div>
                <div style="text-align: right;">
                    <strong>{{ $item['total_formatted'] }}</strong>
                </div>
            </div>
        @endforeach
    </div>

    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ config('app.url') }}/admin/orders/{{ $order['id'] }}" class="btn">View Order Details</a>
        <a href="{{ config('app.url') }}/admin/shipments/{{ $shipment['id'] }}" class="btn btn-secondary">Manage Shipment</a>
    </div>

    @if($shipment['priority_level'] === 'high')
        <div style="background-color: #fee2e2; border: 2px solid #ef4444; border-radius: 8px; padding: 20px; margin: 30px 0; text-align: center;">
            <h4 style="margin-top: 0; color: #dc2626;">🚨 URGENT ACTION REQUIRED</h4>
            <p style="margin: 0; color: #991b1b; font-weight: 600;">This issue requires immediate attention to prevent customer escalation.</p>
        </div>
    @endif

    <p><strong>Next Steps:</strong> Please review this shipment immediately and take appropriate action. Update the customer as needed and document any actions taken.</p>

    <p>Best regards,<br>The {{ config('app.name') }} System</p>
@endsection
