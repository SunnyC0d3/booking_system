@extends('emails.layout')

@section('title', 'Booking Confirmation - ' . $service['name'])

@section('content')
    <h2 style="color: #1f2937; margin-top: 0;">🎈 Booking Confirmed!</h2>

    <p>Hi {{ $client['name'] }},</p>

    <p>Thank you for choosing {{ $company['name'] }} for your special event! Your booking has been confirmed.</p>

    <div class="highlight-box">
        <h3 style="margin-top: 0; color: #1e40af;">Booking Reference: #{{ $booking['reference'] }}</h3>
        <span class="status-badge status-{{ strtolower($booking['status']) }}">{{ $booking['status'] }}</span>
    </div>

    <div class="order-summary">
        <h3 style="margin-top: 0; color: #1f2937;">🎈 Service Details</h3>

        <div class="order-item">
            <div><strong>Service</strong></div>
            <div>{{ $service['name'] }}</div>
        </div>

        <div class="order-item">
            <div><strong>📅 Date & Time</strong></div>
            <div>{{ $booking['scheduled_at'] }} at {{ $booking['scheduled_time'] }}</div>
        </div>

        <div class="order-item">
            <div><strong>⏱️ Duration</strong></div>
            <div>{{ $booking['duration_display'] }} ({{ $booking['scheduled_time'] }} - {{ $booking['ends_at'] }})</div>
        </div>

        <div class="order-item">
            <div><strong>💳 Payment Status</strong></div>
            <div>
                <span class="status-badge status-{{ strtolower($booking['payment_status']) }}">
                    {{ $booking['payment_status'] }}
                </span>
            </div>
        </div>

        <div class="order-item">
            <div><strong>💰 Total Amount</strong></div>
            <div><strong>{{ $booking['total_amount'] }}</strong></div>
        </div>
    </div>

    @if($service['short_description'])
        <div style="background-color: #f9fafb; border-radius: 6px; padding: 20px; margin: 20px 0;">
            <h4 style="margin-top: 0; color: #374151;">Service Description:</h4>
            <p style="color: #4b5563; margin: 0;">{{ $service['short_description'] }}</p>
        </div>
    @endif

    <!-- Client Information -->
    <div class="order-summary">
        <h3 style="margin-top: 0; color: #1f2937;">👤 Client Information</h3>

        <div class="order-item">
            <div><strong>Name</strong></div>
            <div>{{ $client['name'] }}</div>
        </div>

        <div class="order-item">
            <div><strong>Email</strong></div>
            <div>{{ $client['email'] }}</div>
        </div>

        @if($client['phone'])
            <div class="order-item">
                <div><strong>Phone</strong></div>
                <div>{{ $client['phone'] }}</div>
            </div>
        @endif
    </div>

    <!-- Location Information -->
    @if($location)
        <div class="highlight-box">
            <h4 style="margin-top: 0; color: #1e40af;">📍 Service Location</h4>
            <p style="margin: 5px 0;"><strong>{{ $location['name'] }}</strong></p>
            <p style="margin: 5px 0;">{{ $location['full_address'] }}</p>
            @if($location['phone'])
                <p style="margin: 5px 0;">📞 {{ $location['phone'] }}</p>
            @endif
            @if($location['notes'])
                <p style="margin: 5px 0; font-style: italic;">Note: {{ $location['notes'] }}</p>
            @endif
        </div>
    @endif

    <!-- Consultation Notice -->
    @if($consultation)
        <div style="background-color: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; margin: 20px 0;">
            <h4 style="margin-top: 0; color: #92400e;">💬 Consultation Required</h4>
            <p style="margin: 0; color: #92400e;">This service includes a {{ $consultation['duration_display'] }} consultation. {{ $consultation['notes'] }}</p>
        </div>
    @endif

    <!-- Add-ons -->
    @if(count($add_ons) > 0)
        <div class="order-summary">
            <h3 style="margin-top: 0; color: #1f2937;">✨ Add-ons & Extras</h3>
            @foreach($add_ons as $addOn)
                <div class="order-item">
                    <div>{{ $addOn['name'] }} (x{{ $addOn['quantity'] }})</div>
                    <div>{{ $addOn['total_price'] }}</div>
                </div>
            @endforeach
        </div>
    @endif

    <!-- Pricing Summary -->
    <div class="order-summary">
        <h3 style="margin-top: 0; color: #1f2937;">💰 Pricing Summary</h3>

        <div class="order-item">
            <div>Service Base Price</div>
            <div>{{ $booking['base_price'] }}</div>
        </div>

        @if(count($add_ons) > 0)
            @foreach($add_ons as $addOn)
                <div class="order-item">
                    <div>{{ $addOn['name'] }} (x{{ $addOn['quantity'] }})</div>
                    <div>{{ $addOn['total_price'] }}</div>
                </div>
            @endforeach
        @endif

        <div class="order-item">
            <div><strong>Total Amount</strong></div>
            <div><strong>{{ $booking['total_amount'] }}</strong></div>
        </div>
    </div>

    <!-- Booking Notes -->
    @if($booking['notes'])
        <div style="background-color: #f9fafb; border-radius: 6px; padding: 20px; margin: 20px 0;">
            <h4 style="margin-top: 0; color: #374151;">📝 Booking Notes:</h4>
            <p style="color: #4b5563; margin: 0;">{{ $booking['notes'] }}</p>
        </div>
    @endif

    <!-- Special Requirements -->
    @if($booking['special_requirements'])
        <div style="background-color: #fef2f2; border-left: 4px solid #ef4444; padding: 15px; margin: 20px 0;">
            <h4 style="margin-top: 0; color: #dc2626;">⚠️ Special Requirements:</h4>
            <p style="color: #dc2626; margin: 0;">{{ $booking['special_requirements'] }}</p>
        </div>
    @endif

    <!-- Next Steps -->
    @if(count($next_steps) > 0)
        <div class="highlight-box">
            <h4 style="margin-top: 0; color: #1e40af;">🚀 What Happens Next?</h4>
            <ul style="padding-left: 20px; margin: 10px 0;">
                @foreach($next_steps as $step)
                    <li style="margin: 5px 0;">{{ $step }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Cancellation Policy -->
    @if($booking['cancellation_policy'])
        <div style="background-color: #fef2f2; border-left: 4px solid #ef4444; padding: 15px; margin: 20px 0;">
            <h4 style="margin-top: 0; color: #dc2626;">📋 Cancellation Policy:</h4>
            <p style="color: #4b5563; margin: 0;">{{ $booking['cancellation_policy'] }}</p>
        </div>
    @endif

    <div style="text-align: center; margin: 30px 0;">
        <a href="mailto:{{ $company['email'] }}?subject=Question about Booking {{ $booking['reference'] }}" class="btn">Contact Us</a>
        @if($company['phone'])
            <a href="tel:{{ $company['phone'] }}" class="btn btn-secondary">📞 Call Us</a>
        @endif
    </div>

    <p><strong>💡 Tip:</strong> Save this email for your records. You'll receive a reminder 24 hours before your service.</p>

    <p>We're excited to create beautiful balloon decorations for your special event!</p>

    <p>Best regards,<br>The {{ $company['name'] }} Team</p>
@endsection
