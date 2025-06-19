@extends('emails.layout')

@section('title', 'Return Request Update - Order #' . $order['id'])

@section('content')
    <h2 style="color: #1f2937; margin-top: 0;">Return Request Update</h2>

    <p>Hi {{ $customer['name'] }},</p>

    <p>We have an update regarding your return request for Order #{{ $order['id'] }}.</p>

    <div class="highlight-box">
        <strong>Return Request #{{ $return['id'] }}</strong><br>
        Status: <span class="status-badge status-{{ strtolower($return['status']) }}">{{ $return['status'] }}</span><br>
        Submitted: {{ $return['created_at'] }}
    </div>

    <div class="order-summary">
        <h3 style="margin-top: 0; color: #1f2937;">Return Details</h3>

        <div class="order-item">
            <div>
                <strong>{{ $return['item']['product_name'] }}</strong><br>
                <span style="color: #6b7280;">Quantity: {{ $return['item']['quantity'] }}</span>
            </div>
            <div style="text-align: right;">
                <strong>{{ $return['item']['price_formatted'] }}</strong>
            </div>
        </div>

        <div style="margin-top: 15px;">
            <strong>Reason:</strong> {{ $return['reason'] }}
        </div>
    </div>

    @if($return['status'] === 'Approved')
        <p style="color: #065f46;">✅ <strong>Your return has been approved!</strong></p>
        <p>We will process your refund once we receive the returned item. Please package the item securely and send it back to us.</p>

        <div style="text-align: center; margin: 30px 0;">
            <a href="#" class="btn">View Return Instructions</a>
        </div>
    @elseif($return['status'] === 'Rejected')
        <p style="color: #991b1b;">❌ <strong>Your return request has been reviewed.</strong></p>
        <p>Unfortunately, we're unable to process this return request at this time. If you have questions about this decision, please contact our support team.</p>

        <div style="text-align: center; margin: 30px 0;">
            <a href="#" class="btn btn-secondary">Contact Support</a>
        </div>
    @else
        <p>Your return request is currently being reviewed. We'll update you soon with our decision.</p>
    @endif

    <p>Best regards,<br>The {{ config('app.name') }} Team</p>
@endsection
