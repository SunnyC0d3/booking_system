@extends('emails.layout')

@section('title', 'Booking Reminder - ' . $service['name'])

@section('content')
    <h2 style="color: {{ $reminder['urgency_level'] === 'high' ? '#dc2626' : '#1f2937' }}; margin-top: 0;">⏰ Booking Reminder</h2>

    <p>Hi {{ $client['name'] }},</p>

    <p>Your balloon decoration service {{ $booking['time_until'] }}!</p>

    <!-- Urgency and Countdown -->
    <div style="background: linear-gradient(135deg, {{ $booking['countdown']['is_urgent'] ? '#ef4444, #dc2626' : '#3b82f6, #1d4ed8' }}); color: white; border-radius: 8px; padding: 25px; text-align: center; margin: 20px 0;">
        <div style="font-size: 18px; margin-bottom: 15px; opacity: 0.9;">Time Until Your Service</div>
        <div style="font-size: 32px; font-weight: 700; margin: 10px 0;">{{ $booking['time_until'] }}</div>

        @if($booking['countdown']['days'] > 0 || $booking['countdown']['hours'] > 0)
            <div style="display: flex; justify-content: center; gap: 20px; margin-top: 15px;">
                @if($booking['countdown']['days'] > 0)
                    <div style="text-align: center;">
                        <div style="font-size: 24px; font-weight: 600;">{{ $booking['countdown']['days'] }}</div>
                        <div style="font-size: 12px; opacity: 0.8; text-transform: uppercase;">{{ $booking['countdown']['days'] == 1 ? 'Day' : 'Days' }}</div>
                    </div>
                @endif

                @if($booking['countdown']['hours'] > 0)
                    <div style="text-align: center;">
                        <div style="font-size: 24px; font-weight: 600;">{{ $booking['countdown']['hours'] }}</div>
                        <div style="font-size: 12px; opacity: 0.8; text-transform: uppercase;">{{ $booking['countdown']['hours'] == 1 ? 'Hour' : 'Hours' }}</div>
                    </div>
                @endif

                <div style="text-align: center;">
                    <div style="font-size: 24px; font-weight: 600;">{{ $booking['countdown']['minutes'] }}</div>
                    <div style="font-size: 12px; opacity: 0.8; text-transform: uppercase;">{{ $booking['countdown']['minutes'] == 1 ? 'Minute' : 'Minutes' }}</div>
                </div>
            </div>
        @endif
    </div>

    <!-- Urgency Badge -->
    <div style="margin: 20px 0;">
        <span class="status-badge status-{{ $reminder['urgency_level'] === 'high' ? 'rejected' : ($reminder['urgency_level'] === 'medium' ? 'processing' : 'approved') }}">
            {{ ucfirst($reminder['urgency_level']) }} Priority
        </span>
    </div>

    <div class="highlight-box">
        <h3 style="margin-top: 0; color: {{ $reminder['urgency_level'] === 'high' ? '#dc2626' : '#1e40af' }};">Booking Reference: #{{ $booking['reference'] }}</h3>
    </div>

    <!-- Payment Warning -->
    @if($booking['payment_status'] === 'pending')
        <div style="background-color: #fef2f2; border-left: 4px solid #ef4444; padding: 15px; margin: 20px 0; text-align: center;">
            <h4 style="margin-top: 0; color: #dc2626;">⚠️ Payment Required</h4>
            <p style="color: #dc2626;">Your booking is confirmed but payment is still pending. Please complete your payment to avoid any service delays.</p>
            <a href="mailto:{{ $company['email'] }}?subject=Payment for Booking {{ $booking['reference'] }}" class="btn" style="background-color: #ef4444;">Complete Payment Now</a>
        </div>
    @endif

    <!-- Service Information -->
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
            <div><strong>💰 Total Amount</strong></div>
            <div><strong>{{ $booking['total_amount'] }}</strong></div>
        </div>
    </div>

    <!-- Location Information -->
    @if($location)
        <div style="background-color: #f0fdf4; border-left: 4px solid #22c55e; padding: 15px; margin: 20px 0;">
            <h4 style="margin-top: 0; color: #15803d;">📍 Service Location</h4>
            <p style="margin: 5px 0;"><strong>{{ $location['name'] }}</strong></p>
            <p style="margin: 5px 0;">{{ $location['full_address'] }}</p>
            @if($location['phone'])
                <p style="margin: 5px 0;">📞 {{ $location['phone'] }}</p>
            @endif
            @if($location['directions'])
                <p style="margin: 5px 0; font-style: italic;"><strong>Directions:</strong> {{ $location['directions'] }}</p>
            @endif
            @if($location['parking_info'])
                <p style="margin: 5px 0; font-style: italic;"><strong>Parking:</strong> {{ $location['parking_info'] }}</p>
            @endif
        </div>
    @endif

    <!-- Add-ons -->
    @if(count($add_ons) > 0)
        <div class="order-summary">
            <h3 style="margin-top: 0; color: #1f2937;">✨ Add-ons & Extras</h3>
            @foreach($add_ons as $addOn)
                <div style="padding: 5px 0;">
                    • {{ $addOn['name'] }} (x{{ $addOn['quantity'] }})
                    @if($addOn['description'])
                        <div style="font-size: 14px; color: #6b7280; margin-left: 15px;">{{ $addOn['description'] }}</div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    <!-- Consultation Notice -->
    @if($consultation)
        <div style="background-color: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; margin: 20px 0;">
            <h4 style="margin-top: 0; color: #92400e;">💬 Consultation Required</h4>
            <p style="margin: 0; color: #92400e;">This service includes a {{ $consultation['duration_display'] }} consultation. {{ $consultation['notes'] }}</p>
        </div>
    @endif

    <!-- Preparation Checklist -->
    <div style="background-color: #fffbeb; border-left: 4px solid #f59e0b; padding: 15px; margin: 20px 0;">
        <h4 style="margin-top: 0; color: #92400e;">✅ Preparation Checklist</h4>
        <p style="margin-bottom: 15px; color: #92400e;"><strong>{{ $reminder['preparation_time'] }}</strong></p>
        <ul style="padding-left: 20px; margin: 10px 0; color: #78350f;">
            @foreach($reminder['checklist'] as $item)
                <li style="margin: 5px 0;">{{ $item }}</li>
            @endforeach
        </ul>
    </div>

    <!-- Weather Considerations -->
    @if($weather)
        <div style="background-color: #ecfdf5; border-left: 4px solid #10b981; padding: 15px; margin: 20px 0;">
            <h4 style="margin-top: 0; color: #047857;">🌤️ Weather Considerations</h4>
            <p style="color: #065f46;">We noticed this might be an outdoor event. Here are some important considerations:</p>
            <ul style="margin: 15px 0; padding-left: 20px; color: #064e3b;">
                @foreach($weather['considerations'] as $consideration)
                    <li style="margin: 5px 0;">{{ $consideration }}</li>
                @endforeach
            </ul>
            <p style="font-style: italic; color: #047857; margin: 0;">{{ $weather['contact_advice'] }}</p>
        </div>
    @endif

    <!-- Booking Notes -->
    @if($booking['notes'])
        <div style="background-color: #f9fafb; border-radius: 6px; padding: 20px; margin: 20px 0;">
            <h4 style="margin-top: 0; color: #374151;">📝 Your Booking Notes:</h4>
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

    <!-- Action Buttons -->
    <div style="text-align: center; margin: 30px 0;">
        @if($actions['can_reschedule'])
            <a href="mailto:{{ $company['email'] }}?subject=Reschedule Request - {{ $booking['reference'] }}" class="btn btn-secondary">📅 Reschedule</a>
        @endif

        @if($actions['can_cancel'])
            <a href="mailto:{{ $company['email'] }}?subject=Cancellation Request - {{ $booking['reference'] }}" class="btn btn-secondary">❌ Cancel</a>
        @endif

        <a href="mailto:{{ $company['email'] }}?subject=Question about Booking {{ $booking['reference'] }}" class="btn">💬 Contact Us</a>
    </div>

    <!-- Emergency Contact -->
    @if($reminder['urgency_level'] === 'high' && $contact['emergency_phone'])
        <div style="background-color: #dc2626; color: white; padding: 15px; border-radius: 8px; text-align: center; margin: 20px 0;">
            <h4 style="margin-top: 0;">🚨 Need Immediate Assistance?</h4>
            <p style="margin: 10px 0;">For urgent matters regarding your booking:</p>
            <a href="tel:{{ $contact['emergency_phone'] }}" style="color: white; text-decoration: none; font-weight: 600;">📞 {{ $contact['emergency_phone'] }}</a>
        </div>
    @endif

    <!-- Policy Reminders -->
    <div style="background-color: #f8fafc; border-radius: 6px; padding: 20px; margin: 20px 0;">
        <h4 style="color: #374151; margin-top: 0;">📋 Important Reminders</h4>

        @if($actions['can_reschedule'])
            <p style="color: #4b5563;"><strong>Rescheduling:</strong> Can be done up to {{ $actions['reschedule_deadline'] }}.</p>
        @endif

        @if($actions['can_cancel'])
            <p style="color: #4b5563;"><strong>Cancellation:</strong> For full refund, cancel by {{ $actions['cancel_deadline'] }}.</p>
        @endif

        <p style="color: #4b5563;"><strong>Contact Hours:</strong> {{ $contact['support_hours'] }}</p>

        @if($contact['whatsapp'])
            <p style="color: #4b5563;"><strong>WhatsApp:</strong> <a href="https://wa.me/{{ $contact['whatsapp'] }}" style="color: #3b82f6;">{{ $contact['whatsapp'] }}</a></p>
        @endif
    </div>

    <!-- Thank You Message -->
    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 8px; padding: 25px; margin: 25px 0; text-align: center;">
        <h4 style="margin-top: 0;">🎉 We're Excited to Serve You!</h4>
        <p style="margin: 10px 0;">Thank you for choosing {{ $company['name'] }} for your special event. Our team is ready to create beautiful balloon decorations that will make your celebration unforgettable!</p>
        <p style="margin-bottom: 0; font-style: italic;">See you {{ $booking['time_until'] }}!</p>
    </div>

    <p><strong>💡 Tip:</strong> Please ensure the decoration area is clear and accessible when we arrive. This helps us set up quickly and efficiently!</p>

    <p>Looking forward to making your event spectacular!</p>

    <p>Best regards,<br>The {{ $company['name'] }} Team</p>
@endsection
