@extends('emails.layout')

@section('title', 'Dropship Supplier Alert')

@section('content')
    <h2 style="color: #1f2937; margin-top: 0;">
        @if($issue['severity'] === 'high')
            🚨 URGENT: Supplier Issue Alert
        @elseif($issue['severity'] === 'medium')
            ⚠️ Supplier Issue Alert
        @else
            📋 Supplier Update
        @endif
    </h2>

    <p>Hi Admin Team,</p>

    <p>An issue has been detected with dropship supplier <strong>{{ $supplier['name'] }}</strong> that requires attention.</p>

    <div class="highlight-box" style="background-color: {{ $issue['severity'] === 'high' ? '#fee2e2' : ($issue['severity'] === 'medium' ? '#fef3c7' : '#dbeafe') }}; border-left-color: {{ $issue['severity'] === 'high' ? '#ef4444' : ($issue['severity'] === 'medium' ? '#f59e0b' : '#3b82f6') }};">
        <strong>Issue Summary</strong><br>
        Supplier: {{ $supplier['name'] }}<br>
        Issue Type: {{ $issue['type'] }}<br>
        Severity: <span style="color: {{ $issue['severity'] === 'high' ? '#dc2626' : ($issue['severity'] === 'medium' ? '#d97706' : '#2563eb') }}; font-weight: 600;">{{ strtoupper($issue['severity']) }}</span><br>
        Detected: {{ $issue['detected_at'] }}
    </div>

    <div class="order-summary">
        <h3 style="margin-top: 0; color: #1f2937;">Supplier Details</h3>

        <div class="order-item">
            <div><strong>Supplier ID</strong></div>
            <div>#{{ $supplier['id'] }}</div>
        </div>

        <div class="order-item">
            <div><strong>Integration Type</strong></div>
            <div>{{ $supplier['integration_type'] }}</div>
        </div>

        <div class="order-item">
            <div><strong>Current Status</strong></div>
            <div>
                <span class="status-badge status-{{ $supplier['status'] === 'active' ? 'completed' : 'rejected' }}">
                    {{ ucfirst($supplier['status']) }}
                </span>
            </div>
        </div>

        <div class="order-item">
            <div><strong>Active Orders</strong></div>
            <div style="color: {{ $issue['active_orders'] > 0 ? '#dc2626' : '#059669' }}; font-weight: 600;">
                {{ $issue['active_orders'] }}
            </div>
        </div>
    </div>

    <div style="background-color: #f9fafb; border-left: 4px solid #ef4444; padding: 15px; margin: 20px 0;">
        <h4 style="margin-top: 0; color: #dc2626;">Issue Description</h4>
        <p style="margin: 0; color: #374151;">{{ $issue['description'] }}</p>
    </div>

    @if($issue['affected_orders'])
        <div class="order-summary">
            <h3 style="margin-top: 0; color: #1f2937;">Affected Orders</h3>
            @foreach($issue['affected_orders'] as $order)
                <div class="order-item">
                    <div>
                        <strong>Order #{{ $order['id'] }}</strong><br>
                        <span style="color: #6b7280;">{{ $order['customer_name'] }}</span>
                    </div>
                    <div style="text-align: right;">
                        <span class="status-badge status-{{ $order['status'] }}">{{ $order['status'] }}</span>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <div class="order-summary">
        <h3 style="margin-top: 0; color: #1f2937;">📋 Recommended Actions</h3>
        @foreach($issue['recommended_actions'] as $index => $action)
            <div style="padding: 8px 0; border-bottom: {{ $loop->last ? 'none' : '1px solid #e5e7eb' }};">
                <div style="display: flex; align-items: flex-start;">
                    <span style="background-color: #2563eb; color: white; border-radius: 50%; width: 20px; height: 20px; display: inline-flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 600; margin-right: 10px; flex-shrink: 0;">{{ $index + 1 }}</span>
                    <span style="color: #374151;">{{ $action }}</span>
                </div>
            </div>
        @endforeach
    </div>

    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ config('app.url') }}/admin/suppliers/{{ $supplier['id'] }}" class="btn">View Supplier Details</a>
        <a href="{{ config('app.url') }}/admin/dropshipping/orders?supplier_id={{ $supplier['id'] }}" class="btn btn-secondary">View Affected Orders</a>
    </div>

    @if($issue['severity'] === 'high')
        <div style="background-color: #fee2e2; border: 2px solid #ef4444; border-radius: 8px; padding: 20px; margin: 30px 0; text-align: center;">
            <h4 style="margin-top: 0; color: #dc2626;">🚨 URGENT ACTION REQUIRED</h4>
            <p style="margin: 0; color: #991b1b; font-weight: 600;">This issue requires immediate attention to prevent order fulfillment disruptions.</p>
        </div>
    @endif

    <p><strong>Next Steps:</strong> Please review this supplier issue and take appropriate action to minimize impact on customer orders.</p>

    <p>Best regards,<br>The {{ config('app.name') }} System</p>
@endsection
