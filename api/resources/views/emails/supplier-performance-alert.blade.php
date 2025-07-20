@extends('emails.layout')

@section('title', 'Supplier Performance Alert')

@section('content')
    <h2 style="color: #1f2937; margin-top: 0;">⚠️ Supplier Performance Alert</h2>

    <p>Hi Admin Team,</p>

    <p>Performance monitoring has detected issues with supplier <strong>{{ $supplier['name'] }}</strong> that require your attention.</p>

    <div style="background-color: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; margin: 20px 0;">
        <strong>⚠️ PERFORMANCE THRESHOLD EXCEEDED</strong><br>
        {{ $supplier['name'] }} has fallen below acceptable performance standards for: {{ $performance['metric'] }}
    </div>

    <div class="order-summary">
        <h3 style="margin-top: 0; color: #1f2937;">Performance Metrics</h3>

        <div class="order-item">
            <div><strong>Supplier</strong></div>
            <div>{{ $supplier['name'] }} (#{{ $supplier['id'] }})</div>
        </div>

        <div class="order-item">
            <div><strong>Success Rate</strong></div>
            <div style="color: {{ $performance['success_rate'] >= 90 ? '#059669' : ($performance['success_rate'] >= 75 ? '#d97706' : '#dc2626') }}; font-weight: 600;">
                {{ $performance['success_rate'] }}%
                <span style="color: #6b7280; font-weight: normal;">(Threshold: {{ $performance['threshold'] }}%)</span>
            </div>
        </div>

        <div class="order-item">
            <div><strong>Average Fulfillment Time</strong></div>
            <div style="color: {{ $performance['avg_fulfillment_time'] <= 3 ? '#059669' : ($performance['avg_fulfillment_time'] <= 5 ? '#d97706' : '#dc2626') }}; font-weight: 600;">
                {{ $performance['avg_fulfillment_time'] }} days
            </div>
        </div>

        <div class="order-item">
            <div><strong>Orders This Month</strong></div>
            <div>{{ $performance['orders_this_month'] }}</div>
        </div>

        <div class="order-item">
            <div><strong>Failed Orders</strong></div>
            <div style="color: {{ $performance['failed_orders'] > 10 ? '#dc2626' : '#059669' }}; font-weight: 600;">
                {{ $performance['failed_orders'] }}
            </div>
        </div>

        <div class="order-item">
            <div><strong>Alert Detected</strong></div>
            <div>{{ $performance['detected_at'] }}</div>
        </div>

        @if($performance['action_required'])
            <div class="order-item">
                <div><strong>Action Required</strong></div>
                <div>
                    <span class="status-badge status-rejected">YES</span>
                </div>
            </div>
        @endif
    </div>

    @if($performance['recent_issues'])
        <div class="order-summary">
            <h3 style="margin-top: 0; color: #1f2937;">Recent Issues (Last 7 Days)</h3>
            @foreach($performance['recent_issues'] as $issue)
                <div style="padding: 10px 0; border-bottom: {{ $loop->last ? 'none' : '1px solid #e5e7eb' }};">
                    <div style="font-weight: 600; color: #dc2626;">{{ $issue['type'] }}</div>
                    <div style="color: #4b5563; font-size: 14px;">{{ $issue['description'] }}</div>
                    <div style="color: #6b7280; font-size: 12px;">{{ $issue['date'] }}</div>
                </div>
            @endforeach
        </div>
    @endif

    <div class="order-summary">
        <h3 style="margin-top: 0; color: #1f2937;">📋 Recommended Actions</h3>
        @foreach($performance['recommendations'] as $index => $recommendation)
            <div style="padding: 8px 0; border-bottom: {{ $loop->last ? 'none' : '1px solid #e5e7eb' }};">
                <div style="display: flex; align-items: flex-start;">
                    <span style="background-color: #f59e0b; color: white; border-radius: 50%; width: 20px; height: 20px; display: inline-flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 600; margin-right: 10px; flex-shrink: 0;">{{ $index + 1 }}</span>
                    <span style="color: #374151;">{{ $recommendation }}</span>
                </div>
            </div>
        @endforeach
    </div>

    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ config('app.url') }}/admin/suppliers/{{ $supplier['id'] }}/performance" class="btn">View Detailed Analytics</a>
        <a href="{{ config('app.url') }}/admin/suppliers/{{ $supplier['id'] }}/orders" class="btn btn-secondary">Review Recent Orders</a>
    </div>

    @if($performance['action_required'])
        <div style="background-color: #fef3c7; border: 2px solid #f59e0b; border-radius: 8px; padding: 20px; margin: 30px 0;">
            <h4 style="margin-top: 0; color: #92400e;">⚠️ Action Required</h4>
            <p style="margin-bottom: 10px; color: #92400e;">Performance has dropped significantly and requires immediate intervention:</p>
            <ul style="margin: 0; color: #92400e;">
                <li>Consider contacting the supplier to discuss performance issues</li>
                <li>Review SLA agreements and performance expectations</li>
                <li>Evaluate backup suppliers for critical products</li>
                <li>Monitor closely for the next 7 days</li>
            </ul>
        </div>
    @endif

    <div style="background-color: #eff6ff; border-left: 4px solid #2563eb; padding: 15px; margin: 20px 0;">
        <h4 style="margin-top: 0; color: #1e40af;">Performance Tracking</h4>
        <p style="margin: 0; color: #1e3a8a;">
            This supplier's performance is automatically monitored across key metrics including success rate, fulfillment time, and customer satisfaction.
            Alerts are triggered when performance falls below established thresholds to ensure quality service delivery.
        </p>
    </div>

    <p><strong>Next Steps:</strong> Please review the supplier's performance data and take appropriate action to address the identified issues. Consider scheduling a performance review meeting with the supplier.</p>

    <p>Best regards,<br>The {{ config('app.name') }} Performance Monitoring System</p>
@endsection
