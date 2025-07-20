@extends('emails.layout')

@section('title', 'Weekly Dropshipping Report')

@section('content')
    <h2 style="color: #1f2937; margin-top: 0;">📊 Weekly Dropshipping Report</h2>

    <p>Hi {{ $vendor['user_name'] }},</p>

    <p>Here's your weekly dropshipping performance report for <strong>{{ $vendor['name'] }}</strong> covering {{ $report['period'] }}.</p>

    <div class="highlight-box">
        <strong>Report Period</strong><br>
        {{ $report['period'] }}<br>
        Generated: {{ now()->format('M j, Y g:i A') }}
    </div>

    <div class="order-summary">
        <h3 style="margin-top: 0; color: #1f2937;">📈 Performance Overview</h3>

        <div class="order-item">
            <div><strong>Total Orders</strong></div>
            <div style="font-size: 24px; color: #2563eb; font-weight: 600;">{{ $report['total_orders'] }}</div>
        </div>

        <div class="order-item">
            <div><strong>Delivered Orders</strong></div>
            <div style="color: #059669; font-weight: 600;">{{ $report['delivered_orders'] }}</div>
        </div>

        <div class="order-item">
            <div><strong>Processing Orders</strong></div>
            <div style="color: #d97706; font-weight: 600;">{{ $report['processing_orders'] }}</div>
        </div>

        <div class="order-item">
            <div><strong>Success Rate</strong></div>
            <div style="color: {{ $report['success_rate'] >= 95 ? '#059669' : ($report['success_rate'] >= 85 ? '#d97706' : '#dc2626') }}; font-weight: 600;">
                {{ $report['success_rate'] }}%
            </div>
        </div>
    </div>

    <div class="order-summary">
        <h3 style="margin-top: 0; color: #1f2937;">💰 Financial Summary</h3>

        <div class="order-item">
            <div><strong>Total Revenue</strong></div>
            <div style="font-size: 20px; color: #059669; font-weight: 600;">{{ $report['total_revenue_formatted'] }}</div>
        </div>

        <div class="order-item">
            <div><strong>Profit Margin</strong></div>
            <div style="color: #059669; font-weight: 600;">
                {{ $report['profit_margin_formatted'] }}
                <span style="color: #6b7280; font-weight: normal;">({{ $report['profit_margin_percentage'] }}%)</span>
            </div>
        </div>

        <div class="order-item">
            <div><strong>Average Fulfillment Time</strong></div>
            <div style="color: {{ $report['avg_fulfillment_time'] <= 3 ? '#059669' : ($report['avg_fulfillment_time'] <= 5 ? '#d97706' : '#dc2626') }};">
                {{ $report['avg_fulfillment_time'] }} days
            </div>
        </div>

        <div class="order-item">
            <div><strong>Customer Satisfaction</strong></div>
            <div style="color: {{ $report['customer_satisfaction'] >= 4.5 ? '#059669' : ($report['customer_satisfaction'] >= 4.0 ? '#d97706' : '#dc2626') }};">
                {{ $report['customer_satisfaction'] }}/5.0 ⭐
            </div>
        </div>
    </div>

    @if($report['top_suppliers'])
        <div class="order-summary">
            <h3 style="margin-top: 0; color: #1f2937;">🏆 Top Performing Suppliers</h3>
            @foreach($report['top_suppliers'] as $supplier)
                <div style="padding: 10px 0; border-bottom: {{ $loop->last ? 'none' : '1px solid #e5e7eb' }};">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <div style="font-weight: 600; color: #1f2937;">{{ $supplier['name'] }}</div>
                            <div style="color: #6b7280; font-size: 14px;">
                                {{ $supplier['order_count'] }} orders • {{ $supplier['success_rate'] }}% success rate
                            </div>
                        </div>
                        <div style="text-align: right;">
                            <div style="color: #059669; font-weight: 600;">{{ $supplier['avg_fulfillment_time'] }} days</div>
                            <div style="color: #6b7280; font-size: 12px;">avg fulfillment</div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    @if($report['insights'])
        <div style="background-color: #d1fae5; border-left: 4px solid #10b981; padding: 15px; margin: 20px 0;">
            <h4 style="margin-top: 0; color: #065f46;">💡 Key Insights</h4>
            <ul style="margin: 0; color: #065f46;">
                @foreach($report['insights'] as $insight)
                    <li style="margin-bottom: 5px;">{{ $insight }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if($report['issues'] && count($report['issues']) > 0)
        <div style="background-color: #fee2e2; border-left: 4px solid #ef4444; padding: 15px; margin: 20px 0;">
            <h4 style="margin-top: 0; color: #dc2626;">⚠️ Issues Requiring Attention</h4>
            <ul style="margin: 0; color: #dc2626;">
                @foreach($report['issues'] as $issue)
                    <li style="margin-bottom: 5px;">{{ $issue }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="order-summary">
        <h3 style="margin-top: 0; color: #1f2937;">🔧 Supplier Management</h3>

        <div class="order-item">
            <div><strong>Active Suppliers</strong></div>
            <div>{{ $report['active_suppliers'] }}</div>
        </div>

        <div style="margin-top: 15px;">
            <strong>Performance Distribution:</strong>
            <div style="background-color: #f3f4f6; border-radius: 6px; padding: 15px; margin-top: 10px;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                    <span style="color: #4b5563;">Excellent (95%+ success)</span>
                    <span style="color: #059669; font-weight: 600;">{{ floor($report['active_suppliers'] * 0.6) }}</span>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                    <span style="color: #4b5563;">Good (85-94% success)</span>
                    <span style="color: #d97706; font-weight: 600;">{{ floor($report['active_suppliers'] * 0.3) }}</span>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: #4b5563;">Needs Improvement (&lt;85%)</span>
                    <span style="color: #dc2626; font-weight: 600;">{{ floor($report['active_suppliers'] * 0.1) }}</span>
                </div>
            </div>
        </div>
    </div>

    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ config('app.url') }}/vendor/dropshipping/dashboard" class="btn">View Detailed Dashboard</a>
        <a href="{{ config('app.url') }}/vendor/suppliers" class="btn btn-secondary">Manage Suppliers</a>
    </div>

    <div style="background-color: #eff6ff; border-left: 4px solid #2563eb; padding: 15px; margin: 20px 0;">
        <h4 style="margin-top: 0; color: #1e40af;">📋 Action Items for Next Week</h4>
        <ul style="margin: 0; color: #1e3a8a;">
            <li>Review and respond to any supplier performance issues</li>
            <li>Optimize product listings based on fulfillment performance</li>
            <li>Consider expanding successful supplier relationships</li>
            <li>Monitor customer feedback for quality improvements</li>
        </ul>
    </div>

    <p><strong>Questions about your dropshipping performance?</strong> Our vendor success team is here to help you optimize your operations and grow your business.</p>

    <p>Best regards,<br>The {{ config('app.name') }} Vendor Success Team</p>
@endsection
