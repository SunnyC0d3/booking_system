@extends('emails.layout')

@section('title', 'New Review Received')

@section('content')
    <h2 style="color: #1f2937; margin-top: 0;">New Review for Your Product! 📝</h2>

    <p>Hi {{ $vendor['user_name'] }},</p>

    <p>You've received a new review for <strong>{{ $review['product']['name'] }}</strong>!</p>

    <div class="order-summary">
        <h3 style="margin-top: 0; color: #1f2937;">Review Summary</h3>

        <div class="order-item">
            <div><strong>Rating</strong></div>
            <div>{{ str_repeat('⭐', $review['rating']) }} ({{ $review['rating'] }}/5)</div>
        </div>

        <div class="order-item">
            <div><strong>Customer</strong></div>
            <div>{{ $review['user']['name'] }}</div>
        </div>

        <div class="order-item">
            <div><strong>Verified Purchase</strong></div>
            <div>
                @if($review['is_verified_purchase'])
                    <span class="status-badge status-approved">Yes</span>
                @else
                    <span class="status-badge">No</span>
                @endif
            </div>
        </div>

        @if($review['title'])
            <div class="order-item">
                <div><strong>Title</strong></div>
                <div>"{{ $review['title'] }}"</div>
            </div>
        @endif
    </div>

    <div style="background-color: #f9fafb; border-radius: 6px; padding: 20px; margin: 20px 0;">
        <h4 style="margin-top: 0; color: #374151;">Customer Feedback:</h4>
        <div style="font-style: italic; color: #4b5563; line-height: 1.6;">
            "{{ $review['content'] }}"
        </div>
    </div>

    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ config('app.url') }}/vendor/reviews" class="btn">View All Reviews</a>
        <a href="{{ config('app.url') }}/vendor/reviews/{{ $review['id'] }}/respond" class="btn btn-secondary">Respond to Review</a>
    </div>

    <p><strong>💡 Tip:</strong> Responding to reviews shows customers you care about their experience and can help build trust with potential buyers.</p>

    <p>Best regards,<br>The {{ config('app.name') }} Team</p>
@endsection
