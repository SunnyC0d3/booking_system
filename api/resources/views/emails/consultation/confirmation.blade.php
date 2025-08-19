@extends('emails.layout')

@section('title', 'Consultation Confirmed - ' . $service['name'])

@section('content')
    <h2 style="color: #059669; margin-top: 0;">✅ Consultation Confirmed!</h2>

    <p>Hi {{ $client['name'] }},</p>

    <p>Thank you for booking a consultation with us! We're excited to discuss your <strong>{{ $service['name'] }}</strong> requirements and help bring your vision to life.</p>

    <div style="background-color: #f0fdf4; border-left: 4px solid #10b981; padding: 15px; margin: 20px 0;">
        <h3 style="margin-top: 0; color: #059669;">Consultation Reference: #{{ $consultation['reference'] }}</h3>
        <span class="status-badge status-confirmed">{{ $consultation['status'] }}</span>
        @if($booking)
            <br><small style="color: #065f46;">Related to Booking: #{{ $booking['reference'] }}</small>
        @endif
    </div>

    <!-- Consultation Details -->
    <div class="order-summary">
        <h3 style="margin-top: 0; color: #1f2937;">📅 Consultation Details</h3>

        <div class="order-item">
            <div><strong>Service</strong></div>
            <div>{{ $service['name'] }}</div>
        </div>

        <div class="order-item">
            <div><strong>Type</strong></div>
            <div>{{ $consultation['type'] }}</div>
        </div>

        <div class="order-item">
            <div><strong>Format</strong></div>
            <div>{{ $meeting['format_display'] }}</div>
        </div>

        <div class="order-item">
            <div><strong>Date & Time</strong></div>
            <div>{{ $consultation['scheduled_at'] }} at {{ $consultation['scheduled_time'] }}</div>
        </div>

        <div class="order-item">
            <div><strong>Duration</strong></div>
            <div>{{ $consultation['duration_display'] }}</div>
        </div>

        <div class="order-item">
            <div><strong>Timezone</strong></div>
            <div>{{ $consultation['timezone'] }}</div>
        </div>
    </div>

    <!-- Meeting Format Specific Details -->
    @if($meeting['is_video'])
        <div style="background-color: #eff6ff; border-left: 4px solid #3b82f6; padding: 15px; margin: 20px 0;">
            <h4 style="margin-top: 0; color: #1d4ed8;">💻 Video Meeting Details</h4>
            <p><strong>Platform:</strong> {{ $meeting['platform'] }}</p>

            @if($meeting['meeting_link'])
                <div style="text-align: center; margin: 20px 0;">
                    <a href="{{ $meeting['meeting_link'] }}" class="btn" style="background-color: #3b82f6; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; display: inline-block; font-weight: 600;">
                        🎥 Join Video Meeting
                    </a>
                </div>
            @endif

            @if($meeting['meeting_id'])
                <p><strong>Meeting ID:</strong> {{ $meeting['meeting_id'] }}</p>
            @endif

            @if($meeting['access_code'])
                <p><strong>Access Code:</strong> {{ $meeting['access_code'] }}</p>
            @endif

            <div style="background-color: #dbeafe; border-radius: 6px; padding: 15px; margin: 15px 0;">
                <p style="margin: 0; color: #1e40af;"><strong>⚠️ Important:</strong> {{ $meeting['join_instructions'] }}. If you experience technical difficulties, please call us at {{ $meeting['technical_support'] }}.</p>
            </div>
        </div>

    @elseif($meeting['is_phone'])
        <div style="background-color: #f0fdf4; border-left: 4px solid #10b981; padding: 15px; margin: 20px 0;">
            <h4 style="margin-top: 0; color: #059669;">📞 Phone Consultation Details</h4>
            <p><strong>We will call you at:</strong> {{ $meeting['phone_number'] }}</p>

            @if($meeting['dial_in_number'])
                <p><strong>Backup number:</strong> {{ $meeting['dial_in_number'] }}</p>
            @endif

            <div style="background-color: #dcfce7; border-radius: 6px; padding: 15px; margin: 15px 0;">
                <p style="margin: 0; color: #16a34a;"><strong>📱 Please ensure:</strong> Your phone is available and charged at the scheduled time.</p>
            </div>
        </div>

    @elseif($meeting['is_in_person'])
        <div style="background-color: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; margin: 20px 0;">
            <h4 style="margin-top: 0; color: #d97706;">📍 {{ $meeting['is_site_visit'] ? 'Site Visit' : 'In-Person Meeting' }} Details</h4>
            <p><strong>Location:</strong></p>
            <p style="margin: 10px 0;">{{ $meeting['location'] ?: 'To be confirmed' }}</p>

            @if($meeting['instructions'])
                <div style="margin-top: 15px;">
                    <strong>Instructions:</strong>
                    @if(is_array($meeting['instructions']))
                        <ul style="margin: 10px 0; padding-left: 20px;">
                            @foreach($meeting['instructions'] as $key => $instruction)
                                <li><strong>{{ ucfirst($key) }}:</strong>
                                    @if(is_array($instruction))
                                        {{ implode(', ', $instruction) }}
                                    @else
                                        {{ $instruction }}
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p style="margin: 10px 0;">{{ $meeting['instructions'] }}</p>
                    @endif
                </div>
            @endif

            @if($meeting['is_site_visit'])
                <div style="background-color: #fbbf24; color: #92400e; border-radius: 6px; padding: 15px; margin: 15px 0;">
                    <p style="margin: 0;"><strong>🏗️ Site Visit Note:</strong> {{ $meeting['site_requirements'] }}. {{ $meeting['additional_time'] }}.</p>
                </div>
            @endif
        </div>
    @endif

    <!-- Preparation Instructions -->
    @if($preparation['instructions'])
        <div style="background-color: #fff7ed; border-left: 4px solid #ea580c; padding: 15px; margin: 20px 0;">
            <h4 style="margin-top: 0; color: #ea580c;">📋 How to Prepare</h4>
            <div style="white-space: pre-line; color: #9a3412;">{{ $preparation['instructions'] }}</div>
        </div>
    @endif

    <!-- Items to Bring -->
    @if($preparation['items_to_bring'] && count($preparation['items_to_bring']) > 0)
        <div class="order-summary">
            <h3 style="margin-top: 0; color: #1f2937;">🎒 Items to Bring</h3>
            <ul style="margin: 15px 0; padding-left: 20px; color: #374151;">
                @foreach($preparation['items_to_bring'] as $item)
                    <li style="margin-bottom: 8px;">{{ $item }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- What to Expect -->
    @if($preparation['what_to_expect'] && count($preparation['what_to_expect']) > 0)
        <div style="background-color: #f0f9ff; border-left: 4px solid #0ea5e9; padding: 15px; margin: 20px 0;">
            <h4 style="margin-top: 0; color: #0284c7;">💭 What to Expect</h4>
            <ul style="margin: 15px 0; padding-left: 20px; color: #0c4a6e;">
                @foreach($preparation['what_to_expect'] as $expectation)
                    <li style="margin-bottom: 8px;">{{ $expectation }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Consultation Questions -->
    @if($preparation['questions'] && count($preparation['questions']) > 0)
        <div style="background-color: #f0fdf4; border-left: 4px solid #16a34a; padding: 15px; margin: 20px 0;">
            <h4 style="margin-top: 0; color: #15803d;">🗣️ Questions We'll Discuss</h4>
            <p style="color: #166534;">To make the most of our time together, we'll cover these key topics:</p>
            <ul style="margin: 15px 0; padding-left: 20px; color: #14532d;">
                @foreach($preparation['questions'] as $question)
                    <li style="margin-bottom: 8px;">{{ $question }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Related Booking Information -->
    @if($booking)
        <div class="order-summary">
            <h3 style="margin-top: 0; color: #1f2937;">🎯 About Your Booking</h3>

            <div class="order-item">
                <div><strong>Booking Reference</strong></div>
                <div>#{{ $booking['reference'] }}</div>
            </div>

            <div class="order-item">
                <div><strong>Service Date</strong></div>
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

            <p style="color: #374151; margin: 15px 0;">This consultation will help us finalize all details and ensure everything is perfect for your special day!</p>
        </div>
    @endif

    <!-- Location Information -->
    @if($location)
        <div class="highlight-box">
            <h4 style="margin-top: 0; color: #1e40af;">📍 Service Location</h4>
            <p style="margin: 5px 0;"><strong>{{ $location['name'] }}</strong></p>
            <p style="margin: 5px 0;">{{ $location['full_address'] }}</p>
            @if($location['phone'])
                <p style="margin: 5px 0;"><strong>Phone:</strong> {{ $location['phone'] }}</p>
            @endif
        </div>
    @endif

    <!-- Consultation Notes -->
    @if($consultation['consultation_notes'])
        <div style="background-color: #f9fafb; border-radius: 6px; padding: 20px; margin: 20px 0;">
            <h4 style="margin-top: 0; color: #374151;">📝 Consultation Notes:</h4>
            <p style="color: #4b5563; margin: 0; white-space: pre-line;">{{ $consultation['consultation_notes'] }}</p>
        </div>
    @endif

    <!-- Contact Information -->
    <div style="background-color: #f8fafc; border-radius: 6px; padding: 20px; margin: 20px 0;">
        <h4 style="margin-top: 0; color: #374151;">📞 Need to Make Changes?</h4>
        <p style="color: #4b5563;">If you need to reschedule or have any questions, please contact us:</p>
        <div style="margin: 15px 0;">
            <p style="margin: 5px 0; color: #4b5563;"><strong>Email:</strong> {{ $company['email'] }}</p>
            @if($company['phone'])
                <p style="margin: 5px 0; color: #4b5563;"><strong>Phone:</strong> {{ $company['phone'] }}</p>
            @endif
            <p style="margin: 5px 0; color: #4b5563;"><strong>Business Hours:</strong> Monday - Friday, 9 AM - 6 PM</p>
        </div>
        <p style="color: #6b7280; font-size: 14px; margin: 10px 0 0;"><small>Please provide your consultation reference <strong>{{ $consultation['reference'] }}</strong> when contacting us.</small></p>
    </div>

    <!-- Action Buttons -->
    <div style="text-align: center; margin: 30px 0;">
        @if($meeting['is_video'] && $meeting['meeting_link'])
            <a href="{{ $meeting['meeting_link'] }}" class="btn">🎥 Join Video Meeting</a>
        @endif
        <a href="mailto:{{ $company['email'] }}?subject=Consultation Inquiry - {{ $consultation['reference'] }}" class="btn btn-secondary">📧 Contact Us</a>
        @if($company['phone'])
            <a href="tel:{{ $company['phone'] }}" class="btn btn-secondary">📞 Call Us</a>
        @endif
    </div>

    <!-- Closing Message -->
    <div style="background-color: #f0f9ff; border-radius: 6px; padding: 20px; margin: 20px 0; text-align: center;">
        <h4 style="color: #1e40af; margin-top: 0;">We're Excited to Meet You!</h4>
        <p style="color: #1e3a8a;">We're looking forward to discussing your vision and helping you create something truly special. Our team is committed to making your event memorable and beautiful.</p>
        <p style="margin-bottom: 0; color: #1e3a8a;"><strong>See you at the consultation!</strong></p>
    </div>

    <p>Best regards,<br>The {{ $company['name'] }} Team</p>
@endsection
