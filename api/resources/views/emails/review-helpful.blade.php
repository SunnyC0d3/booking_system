@extends('emails.layout')

@section('title', 'Review Milestone Reached')

@section('content')
    <h2 style="color: #1f2937; margin-top: 0;">Your Review Reached {{ $review['helpful_votes'] }} Helpful Votes! 👍</h2>

    <p>Hi {{ $review['user']['name'] }},</p>

    <p>Great news! Your review of <strong>{{ $review['product']['name'] }}</strong> has reached <strong>{{ $review['helpful_votes'] }} helpful votes</strong> from other customers.</p>

    <div class="highlight-box">
        <strong>Review Performance</strong><br>
        Helpful Votes: <span style="color: #059669; font-weight: 600;">{{ $review['helpful_votes'] }}</span><br>
        Total Votes: {{ $review['total_votes'] }}<br>
        Helpfulness Rate: <span style="color: #059669; font-weight: 600;">{{ round(($review['helpful_votes'] / max($review['total_votes'], 1)) * 100) }}%</span>
    </div>

    <div class="order-summary">
        <h3 style="margin-top: 0; color: #1f2937;">Your Popular Review</h3>

        <div style="background-color: #f0fdf4; border-left: 4px solid #10b981; padding: 20px;">
            <div style="margin-bottom: 10px;">
                <span style="font-size: 18px;">{{ str_repeat('⭐', $review['rating']) }}</span>
                <span style="margin-left: 10px; color: #6b7280;">({{ $review['rating'] }}/5)</span>
            </div>

            @if($review['title'])
                <div style="font-weight: 600; margin-bottom: 8px; color: #065f46;">
                    "{{ $review['title'] }}"
                </div>
            @endif

            <div style="color: #374151; line-height: 1.6;">
                "{{ Str::limit($review['content'], 150) }}"
            </div>
        </div>
    </div>

    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ config('app.url') }}/products/{{ $review['product']['id'] }}#reviews" class="btn">View Your Review</a>
    </div>

    <p><strong>Why is this important?</strong></p>
    <ul style="color: #4b5563;">
        <li>Your review is helping other customers make informed decisions</li>
        <li>High helpful vote counts increase your review's visibility</li>
        <li>You're building a reputation as a trusted reviewer</li>
        <li>Your feedback directly impacts product improvements</li>
    </ul>

    <p>Thank you for taking the time to share your honest experience. Reviews like yours make {{ config('app.name') }} a better place to shop!</p>

    <p>Best regards,<br>The {{ config('app.name') }} Team</p>
@endsection
