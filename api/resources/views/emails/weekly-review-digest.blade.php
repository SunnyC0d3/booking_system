@extends('emails.layout')

@section('title', 'Weekly Review Digest')

@section('content')
    <h2 style="color: #1f2937; margin-top: 0;">Weekly Review Digest for {{ $vendor['name'] }} 📊</h2>

    <p>Hi {{ $vendor['user_name'] }},</p>

    <p>Here's your weekly review summary for {{ $period['start'] }} to {{ $period['end'] }}.</p>

    <div class="order-summary">
        <h3 style="margin-top: 0; color: #1f2937;">This Week's Overview</h3>

        <div class="order-item">
            <div><strong>New Reviews</strong></div>
            <div style="font-size: 24px; color: #2563eb; font-weight: 600;">{{ $stats['total_reviews'] }}</div>
        </div>

        <div class="order-item">
            <div><strong>Average Rating</strong></div>
            <div style="font-size: 20px; color: #059669;">
                {{ str_repeat('⭐', floor($stats['average_rating'])) }} ({{ $stats['average_rating'] }}/5)
            </div>
        </div>

        <div class="order-item">
            <div><strong>Verified Purchases</strong></div>
            <div>{{ $stats['verified_count'] }} of {{ $stats['total_reviews'] }}</div>
        </div>

        @if($stats['response_needed'] > 0)
            <div class="order-item">
                <div><strong>⏰ Awaiting Response</strong></div>
                <div style="color: #dc2626; font-weight: 600;">{{ $stats['response_needed'] }} reviews</div>
            </div>
        @endif
    </div>

    <h3 style="color: #1f2937;">Recent Reviews</h3>

    @foreach($reviews as $review)
        <div style="border: 1px solid #e5e7eb; border-radius: 8px; padding: 15px; margin: 15px 0; background-color: #fafafa;">
            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 10px;">
                <div>
                    <div style="font-weight: 600; color: #1f2937;">{{ $review['product_name'] }}</div>
                    <div style="margin: 5px 0;">
                        {{ str_repeat('⭐', $review['rating']) }}
                        <span style="color: #6b7280;">by {{ $review['user_name'] }}</span>
                    </div>
                    @if($review['is_verified_purchase'])
                        <span class="status-badge status-approved">Verified Purchase</span>
                    @endif
                </div>
                <div style="font-size: 14px; color: #6b7280;">{{ $review['created_at'] }}</div>
            </div>

            <div style="color: #4b5563; font-style: italic;">
                "{{ $review['content'] }}"
            </div>
        </div>
    @endforeach

    @if($stats['response_needed'] > 0)
        <div style="background-color: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; margin: 30px 0;">
            💡 <strong>Response Opportunity:</strong> You have {{ $stats['response_needed'] }} review(s) waiting for your response.
            Engaging with customers shows you care about their experience!
        </div>
    @endif

    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ config('app.url') }}/vendor/reviews" class="btn">View All Reviews</a>
        @if($stats['response_needed'] > 0)
            <a href="{{ config('app.url') }}/vendor/reviews/unanswered" class="btn btn-secondary">Respond to Reviews</a>
        @endif
    </div>

    <p><strong>📈 Pro Tips:</strong></p>
    <ul style="color: #4b5563;">
        <li>Respond to reviews within 24-48 hours for best customer engagement</li>
        <li>Thank customers for positive reviews and address concerns in negative ones</li>
        <li>Use feedback to improve your products and service</li>
        <li>Encourage satisfied customers to leave reviews</li>
    </ul>

    <p>Best regards,<br>The {{ config('app.name') }} Team</p>
@endsection
