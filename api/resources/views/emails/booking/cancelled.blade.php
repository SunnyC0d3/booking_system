@extends('emails.layout')

@section('title', 'Booking Cancelled - ' . $service['name'])

@section('content')
    <h2 style="color: #dc2626; margin-top: 0;">❌ Booking Cancelled</h2>

    <p>Hi {{ $client['name'] }},</p>

    <p>We're sorry to inform you that your booking has been cancelled.</p>

    <div style="background-color: #fef2f2; border-left: 4px solid #ef4444; padding: 15px; margin: 20px 0;">
        <h3 style="margin-top: 0; color: #dc2626;">Booking Reference: #{{ $booking['reference'] }}</h3>
        <span class="status-badge status-rejected">{{ $booking['status'] }}</span>
    </div>

    <!-- Cancellation Details -->
    <div class="order-summary">
        <h3 style="margin-top: 0; color: #1f2937;">🚫 Cancellation Details</h3>

        <div class="order-item">
            <div><strong>Cancelled On</strong></div>
            <div>{{ $cancellation['cancelled_at'] }}</div>
        </div>

        <div class="order-item">
            <div><strong>Cancelled By</strong></div>
            <div>{{ $cancellation['cancelled_by'] }}</div>
        </div>

        <div class="order-item">
            <div><strong>Reason</strong></div>
            <div>{{ $cancellation['reason'] }}</div>
        </div>
    </div>

    <!-- Original Booking Details -->
    <div class="order-summary">
        <h3 style="margin-top: 0; color: #1f2937;">📋 Original Booking Details</h3>

        <div class="order-item">
            <div><strong>Service</strong></div>
            <div>{{ $service['name'] }}</div>
        </div>

        <div class="order-item">
            <div><strong>Date & Time</strong></div>
            <div>{{ $booking['scheduled_at'] }} at {{ $booking['scheduled_time'] }}</div>
        </div>

        <div class="order-item">
            <div><strong>Duration</strong></div>
            <div>{{ $booking['duration_display'] }}</div>
        </div>

        <div class="order-item">
            <div><strong>Total Amount</strong></div>
            <div>{{ $booking['total_amount'] }}</div>
        </div>
    </div>

    <!-- Location Information -->
    @if($location)
        <div class="highlight-box">
            <h4 style="margin-top: 0; color: #1e40af;">📍 Original Location</h4>
            <p style="margin: 5px 0;"><strong>{{ $location['name'] }}</strong></p>
            <p style="margin: 5px 0;">{{ $location['full_address'] }}</p>
        </div>
    @endif

    <!-- Refund Information -->
    @if($refund['status'] === 'full_refund' || $refund['status'] === 'partial_refund')
        <div style="background-color: #f0fdf4; border-left: 4px solid #22c55e; padding: 15px; margin: 20px 0;">
            <h4 style="margin-top: 0; color: #15803d;">💰 Refund Information</h4>
            <p style="color: #15803d;">{{ $cancellation['refund_info'] }}</p>

            @if($refund['status'] === 'partial_refund')
                <div style="margin: 15px 0;">
                    <div style="display: flex; justify-content: space-between; margin: 5px 0;">
                        <span>Original Amount:</span>
                        <span>{{ $refund['original_amount'] }}</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin: 5px 0;">
                        <span>Refund Percentage:</span>
                        <span>{{ $refund['percentage'] }}%</span>
                    </div>
                </div>
            @endif

            <div style="background-color: #dcfce7; padding: 15px; border-radius: 6px; margin: 15px 0; text-align: center;">
                <h4 style="margin: 0; color: #15803d;">Refund Amount: {{ $refund['amount'] }}</h4>
                <p style="margin: 10px 0 0; font-size: 14px; color: #16a34a;">Processing Time: {{ $refund['processing_time'] }}</p>
            </div>
        </div>
    @elseif($refund['status'] === 'no_refund')
        <div style="background-color: #fffbeb; border-left: 4px solid #f59e0b; padding: 15px; margin: 20px 0;">
            <h4 style="margin-top: 0; color: #d97706;">⚠️ Refund Policy</h4>
            <p style="color: #92400e;">{{ $cancellation['refund_info'] }}</p>
            <p style="font-size: 14px; color: #a16207; margin: 10px 0 0;">We understand this may be disappointing. Please contact us if you have any questions about our cancellation policy.</p>
        </div>
    @else
        <div class="order-summary">
            <h3 style="margin-top: 0; color: #1f2937;">💳 Payment Information</h3>
            <p>{{ $cancellation['refund_info'] }}</p>
        </div>
    @endif

    <!-- Rebooking Section -->
    <div style="background-color: #eff6ff; border-left: 4px solid #3b82f6; padding: 15px; margin: 20px 0;">
        <h4 style="margin-top: 0; color: #1d4ed8;">🔄 We'd Love to Serve You Again</h4>
        <p style="color: #1e40af;">We're sorry we couldn't provide our service this time. We'd be delighted to help you with your balloon decoration needs in the future!</p>

        @if($rebooking['discount_offered'])
            <div style="background-color: #dbeafe; border: 2px dashed #3b82f6; border-radius: 6px; padding: 15px; margin: 15px 0; text-align: center;">
                <div style="background-color: #3b82f6; color: white; padding: 8px 16px; border-radius: 20px; display: inline-block; margin-bottom: 10px; font-weight: 600;">Special Offer</div>
                <p style="margin: 10px 0; color: #1e40af;"><strong>15% Discount on Your Next Booking</strong></p>
                <p style="margin: 0; color: #3730a3;">As an apology for this cancellation, we're offering you a 15% discount on your next service. Contact us to book again!</p>
            </div>
        @endif
    </div>

    <!-- Booking Notes (if any) -->
    @if($booking['notes'])
        <div style="background-color: #f9fafb; border-radius: 6px; padding: 20px; margin: 20px 0;">
            <h4 style="margin-top: 0; color: #374151;">📝 Original Booking Notes:</h4>
            <p style="color: #4b5563; margin: 0;">{{ $booking['notes'] }}</p>
        </div>
    @endif

    <div style="text-align: center; margin: 30px 0;">
        <a href="mailto:{{ $rebooking['contact_info']['email'] }}?subject=Rebooking Request - {{ $booking['reference'] }}" class="btn">📧 Contact Us to Rebook</a>
        @if($company['phone'])
            <a href="tel:{{ $company['phone'] }}" class="btn btn-secondary">📞 Call Us</a>
        @endif
    </div>

    <!-- Apology -->
    <div style="background-color: #f8fafc; border-radius: 6px; padding: 20px; margin: 20px 0; text-align: center;">
        <h4 style="color: #374151; margin-top: 0;">We Sincerely Apologize</h4>
        <p style="color: #4b5563;">We understand that cancellations can be inconvenient, especially when you're planning a special event. Our team is committed to providing exceptional service, and we hope to have the opportunity to work with you in the future.</p>
        <p style="margin-bottom: 0; color: #4b5563;"><strong>Need assistance or have questions?</strong><br>Don't hesitate to reach out to our team.</p>
    </div>

    <p>We appreciate your understanding and look forward to serving you in the future.</p>

    <p>Best regards,<br>The {{ $company['name'] }} Team</p>
@endsection
