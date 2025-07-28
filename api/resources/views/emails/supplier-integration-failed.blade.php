@extends('emails.layout')

@section('title', 'Supplier Integration Failed')

@section('content')
    <h2 style="color: #dc2626; margin-top: 0;">⚠️ Supplier Integration Failure</h2>

    <p>Hello Administrator,</p>

    <p>We've detected a critical failure with the integration for <strong>{{ $supplier['name'] }}</strong>.</p>

    <div class="order-summary">
        <h3 style="margin-top: 0; color: #1f2937;">Integration Details</h3>

        <div class="order-item">
            <div><strong>Supplier</strong></div>
            <div>{{ $supplier['name'] }}</div>
        </div>

        <div class="order-item">
            <div><strong>Integration Type</strong></div>
            <div>{{ ucfirst($integration['type']) }}</div>
        </div>

        <div class="order-item">
            <div><strong>Status</strong></div>
            <div><span style="color: #dc2626; font-weight: bold;">FAILED</span></div>
        </div>

        <div class="order-item">
            <div><strong>Last Successful Sync</strong></div>
            <div>{{ $integration['last_success'] ?? 'Never' }}</div>
        </div>

        <div class="order-item">
            <div><strong>Failure Time</strong></div>
            <div>{{ $error['occurred_at'] }}</div>
        </div>
    </div>

    <div style="background-color: #fef2f2; border-radius: 6px; padding: 20px; margin: 20px 0;">
        <h4 style="margin-top: 0; color: #dc2626;">Error Information</h4>
        <div style="color: #7f1d1d; line-height: 1.6;">
            <strong>Error Message:</strong><br>
            {{ $error['message'] }}
        </div>
        @if(isset($error['code']))
            <div style="color: #7f1d1d; margin-top: 10px;">
                <strong>Error Code:</strong> {{ $error['code'] }}
            </div>
        @endif
    </div>

    <div style="background-color: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; margin: 20px 0;">
        <h4 style="margin-top: 0; color: #92400e;">Impact & Actions Required</h4>
        <ul style="margin: 0; color: #92400e;">
            <li><strong>Orders may not be automatically sent to supplier</strong></li>
            <li><strong>Stock levels may not sync properly</strong></li>
            <li><strong>Product pricing may become outdated</strong></li>
            <li>Check supplier connection settings</li>
            <li>Verify API credentials or FTP access</li>
            <li>Contact supplier if necessary</li>
            <li>Consider manual order processing until resolved</li>
        </ul>
    </div>

    @if(isset($integration['retry_scheduled']))
        <div style="background-color: #dbeafe; border-left: 4px solid #2563eb; padding: 15px; margin: 20px 0;">
            <h4 style="margin-top: 0; color: #1e40af;">🔄 Automatic Retry</h4>
            <p style="margin: 0; color: #1e40af;">
                System will automatically retry the connection at: <strong>{{ $integration['retry_scheduled'] }}</strong>
            </p>
        </div>
    @endif

    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ config('app.url') }}/admin/suppliers/{{ $supplier['id'] }}" class="btn">View Supplier Details</a>
        <a href="{{ config('app.url') }}/admin/supplier-integrations" class="btn btn-secondary">Manage Integrations</a>
    </div>

    <p><strong>Priority:</strong> High - Please address this issue as soon as possible to prevent order fulfillment delays.</p>

    <p>Best regards,<br>{{ config('app.name') }} Integration Monitor</p>
@endsection
