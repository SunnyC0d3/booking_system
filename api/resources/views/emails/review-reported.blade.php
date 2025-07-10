@extends('emails.layout')

@section('title', 'Review Reported - Action Required')

@section('content')
    <h2 style="color: #1f2937; margin-top: 0;">Review Reported - Moderation Required ⚠️</h2>

    <p>Hi Admin Team,</p>

    <p>A review has been reported and requires moderation attention.</p>

    <div class="order-summary">
        <h3 style="margin-top: 0; color: #1f2937;">Report Details</h3>

        <div class="order-item">
            <div><strong>Report ID</strong></div>
            <div>#{{ $report['id'] }}</div>
        </div>

        <div class="order-item">
            <div><strong>Reason</strong></div>
            <div>
                <span class="status-badge status-rejected">{{ $report['reason_label'] }}</span>
            </div>
        </div>

        <div class="order-item">
            <div><strong>Reported By</strong></div>
            <div>{{ $report['reported_by']['name'] }} ({{ $report['reported_by']['email'] }})</div>
        </div>

        <div class="order-item">
            <div><strong>Reported At</strong></div>
            <div>{{ $report['created_at'] }}</div>
        </div>
    </div>

    @if($report['details'])
        <div style="background-color: #fee2e2; border-left: 4px solid #ef4444; padding: 15px; margin: 20px 0;">
            <strong>Additional Details:</strong><br>
            {{ $report['details'] }}
        </div>
    @endif

    <div class="order-summary">
        <h3 style="margin-top: 0; color: #1f2937;">Reported Review</h3>

        <div class="order-item">
            <div><strong>Review ID</strong></div>
            <div>#{{ $review['id'] }}</div>
        </div>

        <div class="order-item">
            <div><strong>Product</strong></div>
            <div>{{ $review['product']['name'] }}</div>
        </div>

        <div class="order-item">
            <div><strong>Reviewer</strong></div>
            <div>{{ $review['user']['name'] }} ({{ $review['user']['email'] }})</div>
        </div>

        <div class="order-item">
            <div><strong>Rating</strong></div>
            <div>{{ str_repeat('⭐', $review['rating']) }} ({{ $review['rating'] }}/5)</div>
        </div>

        @if($review['title'])
            <div class="order-item">
                <div><strong>Title</strong></div>
                <div>"{{ $review['title'] }}"</div>
            </div>
        @endif
    </div>

    <div style="background-color: #f9fafb; border-radius: 6px; padding: 20px; margin: 20px 0;">
        <h4 style="margin-top: 0; color: #374151;">Review Content:</h4>
        <div style="color: #4b5563; line-height: 1.6;">
            "{{ $review['content'] }}"
        </div>
    </div>

    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ config('app.url') }}/admin/reviews/reports" class="btn">Review Reports Dashboard</a>
        <a href="{{ config('app.url') }}/admin/reviews/{{ $review['id'] }}" class="btn btn-secondary">View Full Review</a>
    </div>

    <p><strong>Action Required:</strong> Please review this report and take appropriate action (approve, reject, or moderate the review).</p>

    <p>Best regards,<br>The {{ config('app.name') }} System</p>
@endsection
