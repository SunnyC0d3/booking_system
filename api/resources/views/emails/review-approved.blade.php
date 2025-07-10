@extends('emails.layout')

@section('title', 'Review Approved')

@section('content')
    <h2 style="color: #1f2937; margin-top: 0;">Your Review Has Been Approved! ✅</h2>

    <p>Hi {{ $review['user']['name'] }},</p>

    <p>Great news! Your review for <strong>{{ $review['product']['name'] }}</strong> has been approved and is now live on our website.</p>

    <div class="highlight-box">
        <strong>Your Review</strong><br>
        Rating: {{ str_repeat('⭐', $review['rating']) }} ({{ $review['rating'] }}/5)<br>
        @if($review['title'])
            Title: "{{ $review['title'] }}"<br>
        @endif
        Posted: {{ $review['created_at'] }}
    </div>

    <div class="order-summary">
        <h3 style="margin-top: 0; color: #1f2937;">Review Details</h3>

        <div style="padding: 15px; background-color: #f9fafb; border-radius: 6px;">
            <div style="font-weight: 600; margin-bottom: 8px;">{{ $review['product']['name'] }}</div>
            @if($review['is_verified_purchase'])
                <span class="status-badge status-approved">Verified Purchase</span><br><br>
            @endif
            <div style="font-style: italic; color: #4b5563;">
                "{{ Str::limit($review['content'], 150) }}"
            </div>
        </div>
    </div>

    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ config('app.url') }}/products/{{ $review['product']['id'] }}#reviews" class="btn">View Your Review</a>
    </div>

    <p>Thank you for sharing your experience with other customers. Your feedback helps our community make informed purchasing decisions!</p>

    <p>Best regards,<br>The {{ config('app.name') }} Team</p>
@endsection
