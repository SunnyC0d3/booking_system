@extends('emails.layout')

@section('title', 'Dropship Integration Failed')

@section('content')
    <h2 style="color: #1f2937; margin-top: 0;">🚨 Dropship Integration Failure Alert</h2>

    <p>Hi Admin Team,</p>

    <p>A critical integration failure has been detected with supplier <strong>{{ $supplier['name'] }}</strong> that requires immediate attention.</p>

    <div style="background-color: #fee2e2; border-left: 4px solid #ef4444; padding: 15px; margin: 20px 0;">
        <strong>⚠️ INTEGRATION FAILURE</strong><br>
        The automated integration with {{ $supplier['name'] }} has failed and dropship operations may be affected.
    </div>

    <div class="order-summary">
        <h3 style="margin-top: 0; color: #1f2937;">Failure Details</h3>

        <div class="order-item">
            <div><strong>Supplier</strong></div>
            <div>{{ $supplier['name'] }} (#{{ $supplier['id'] }})</div>
        </div>

        <div class="order-item">
            <div><strong>Integration Type</strong></div>
            <div>
                <span class="status-badge status-processing">{{ strtoupper($integration['type']) }}</span>
            </div>
        </div>

        <div class="order-item">
            <div><strong>Failed At</strong></div>
            <div>{{ $integration['failed_at'] }}</div>
        </div>

        <div class="order-item">
            <div><strong>Consecutive Failures</strong></div>
            <div style="color: {{ $integration['consecutive_failures'] >= 5 ? '#dc2626' : '#d97706' }}; font-weight: 600;">
                {{ $integration['consecutive_failures'] }}
            </div>
        </div>

        @if($integration['last_successful_sync'])
            <div class="order-item">
                <div><strong>Last Successful Sync</strong></div>
                <div>{{ $integration['last_successful_sync'] }}</div>
            </div>
        @endif

        <div class="order-item">
            <div><strong>Pending Orders</strong></div>
            <div style="color: {{ $integration['pending_orders'] > 0 ? '#dc2626' : '#059669' }}; font-weight: 600;">
                {{ $integration['pending_orders'] }}
            </div>
        </div>
    </div>

    <div style="background-color: #f9fafb; border-left: 4px solid #ef4444; padding: 15px; margin: 20px 0;">
        <h4 style="margin-top: 0; color: #dc2626;">Error Details</h4>
        <p style="margin: 0; color: #374151; font-family: monospace; background-color: #f3f4f6; padding: 10px; border-radius: 4px;">
            {{ $integration['error_message'] }}
        </p>
    </div>

    @if($integration['affected_operations'])
        <div class="order-summary">
            <h3 style="margin-top: 0; color: #1f2937;">Affected Operations</h3>
            @foreach($integration['affected_operations'] as $operation)
                <div style="padding: 8px 0; border-bottom: 1px solid #e5e7eb;">
                    <span style="color: #374151;">{{ $operation }}</span>
                </div>
            @endforeach
        </div>
    @endif

    <div class="order-summary">
        <h3 style="margin-top: 0; color: #1f2937;">📋 Immediate Actions Required</h3>

        <div style="padding: 8px 0; border-bottom: 1px solid #e5e7eb;">
            <div style="display: flex; align-items: flex-start;">
                <span style="background-color: #dc2626; color: white; border-radius: 50%; width: 20px; height: 20px; display: inline-flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 600; margin-right: 10px; flex-shrink: 0;">1</span>
                <span style="color: #374151;">Verify supplier integration configuration and credentials</span>
            </div>
        </div>

        <div style="padding: 8px 0; border-bottom: 1px solid #e5e7eb;">
            <div style="display: flex; align-items: flex-start;">
                <span style="background-color: #dc2626; color: white; border-radius: 50%; width: 20px; height: 20px; display: inline-flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 600; margin-right: 10px; flex-shrink: 0;">2</span>
                <span style="color: #374171;">Test connection to supplier's API/system</span>
            </div>
        </div>

        <div style="padding: 8px 0; border-bottom: 1px solid #e5e7eb;">
            <div style="display: flex; align-items: flex-start;">
                <span style="background-color: #dc2626; color: white; border-radius: 50%; width: 20px; height: 20px; display: inline-flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 600; margin-right: 10px; flex-shrink: 0;">3</span>
                <span style="color: #374151;">Contact supplier technical support if needed</span>
            </div>
        </div>

        <div style="padding: 8px 0; border-bottom: 1px solid #e5e7eb;">
            <div style="display: flex; align-items: flex-start;">
                <span style="background-color: #dc2626; color: white; border-radius: 50%; width: 20px; height: 20px; display: inline-flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 600; margin-right: 10px; flex-shrink: 0;">4</span>
                <span style="color: #374151;">Review and process pending orders manually if necessary</span>
            </div>
        </div>

        <div style="padding: 8px 0;">
            <div style="display: flex; align-items: flex-start;">
                <span style="background-color: #dc2626; color: white; border-radius: 50%; width: 20px; height: 20px; display: inline-flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 600; margin-right: 10px; flex-shrink: 0;">5</span>
                <span style="color: #374151;">Monitor integration status and retry failed operations</span>
            </div>
        </div>
    </div>

    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ config('app.url') }}/admin/suppliers/{{ $supplier['id'] }}/integrations" class="btn">Fix Integration</a>
        <a href="{{ config('app.url') }}/admin/dropshipping/orders?supplier_id={{ $supplier['id'] }}&status=pending" class="btn btn-secondary">View Pending Orders</a>
    </div>

    @if($integration['consecutive_failures'] >= 5)
        <div style="background-color: #fee2e2; border: 2px solid #ef4444; border-radius: 8px; padding: 20px; margin: 30px 0; text-align: center;">
            <h4 style="margin-top: 0; color: #dc2626;">🚨 CRITICAL: Multiple Consecutive Failures</h4>
            <p style="margin: 0; color: #991b1b; font-weight: 600;">This integration has failed {{ $integration['consecutive_failures'] }} times in a row. Consider temporarily disabling automated processing until the issue is resolved.</p>
        </div>
    @endif

    <p><strong>Impact:</strong> Until this integration is restored, all dropship orders for this supplier will require manual processing, which may cause delays in order fulfillment.</p>

    <p>Best regards,<br>The {{ config('app.name') }} System</p>
@endsection
