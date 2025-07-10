@extends('emails.layout')

@section('title', 'Vendor Response to Your Review')

@section('content')
    <h2 style="color: #1f2937; margin-top: 0;">{{ $response['vendor']['name'] }} Responded to Your Review 💬</h2>

    <p>Hi {{ $review['user']['name'] }},</p>

    <p><strong>{{ $response['vendor']['name'] }}</strong> has responded to your review of <strong>{{ $review['product']['name'] }}</strong>.</p>

    <div class="highlight-box">
        <strong>Your Original Review</strong><br>
        Rating: {{ str_repeat('⭐', $review['rating']) }} ({{ $review['rating'] }}/5)<br>
        @if($review['title'])
            "{{ $review['title'] }}"<br>
        @endif
        Posted: {{ $review['created_at'] }}
    </div>

    <div class="order-summary">
        <h3 style="margin-top: 0; color: #1f2937;">Vendor Response</h3>

        <div style="background-color: #eff6ff; border-left: 4px solid #2563eb; padding: 15px; margin: 15px 0;">
            <div style="font-weight: 600; color: #1e40af; margin-bottom: 8px;">
                {{ $response['vendor']['name'] }}
            </div>
            <div style="color: #374151; line-height: 1.6;">
                {{ $response['content'] }}
            </div>
            <div style="font-size: 14px; color: #6b7280; margin-top: 10px;">
                Responded on {{ $response['created_at'] }}
            </div>
        </div>
    </div>

    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ config('app.url') }}/products/{{ $review['product']['id'] }}#reviews" class="btn">View Full Conversation</a>
    </div>

    <p>We're glad to see vendors engaging with customer feedback. This helps create a better shopping experience for everyone!</p>

    <p>Best regards,<br>The {{ config('app.name') }} Team</p>
@endsection
