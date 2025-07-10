@extends('emails.layout')

@section('title', 'Review Update')

@section('content')
    <h2 style="color: #1f2937; margin-top: 0;">Review Update</h2>

    <p>Hi {{ $review['user']['name'] }},</p>

    <p>Thank you for taking the time to review <strong>{{ $review['product']['name'] }}</strong>. After careful consideration, we're unable to publish your review as submitted.</p>

    <div class="highlight-box">
        <strong>Review Details</strong><br>
        Product: {{ $review['product']['name'] }}<br>
        Submitted: {{ $review['created_at'] }}<br>
        Rating: {{ str_repeat('⭐', $review['rating']) }} ({{ $review['rating'] }}/5)
    </div>

    @if(isset($rejection_reason))
        <div style="background-color: #fee2e2; border-left: 4px solid #ef4444; padding: 15px; margin: 20px 0;">
            <strong>Reason:</strong> {{ $rejection_reason }}
        </div>
    @endif

    <p>Our review guidelines help ensure all feedback is helpful, relevant, and appropriate for our community. Common reasons for review rejection include:</p>

    <ul style="color: #4b5563;">
        <li>Content that violates our community guidelines</li>
        <li>Reviews that appear to be spam or promotional</li>
        <li>Content containing inappropriate language</li>
        <li>Reviews not related to the product experience</li>
    </ul>

    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ config('app.url') }}/products/{{ $review['product']['id'] }}" class="btn">Submit New Review</a>
        <a href="{{ config('app.url') }}/support" class="btn btn-secondary">Contact Support</a>
    </div>

    <p>We appreciate your understanding and welcome you to submit a new review that follows our guidelines.</p>

    <p>Best regards,<br>The {{ config('app.name') }} Team</p>
@endsection
