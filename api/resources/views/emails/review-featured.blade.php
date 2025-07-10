@extends('emails.layout')

@section('title', 'Review Featured')

@section('content')
    <h2 style="color: #1f2937; margin-top: 0;">Your Review Has Been Featured! 🌟</h2>

    <p>Hi {{ $review['user']['name'] }},</p>

    <p>Congratulations! Your review of <strong>{{ $review['product']['name'] }}</strong> has been selected as a featured review.</p>

    <div style="background-color: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; margin: 20px 0;">
        🏆 <strong>Featured Review</strong><br>
        Your helpful and detailed review is now highlighted to help other customers make informed decisions!
    </div>

    <div class="order-summary">
        <h3 style="margin-top: 0; color: #1f2937;">Your Featured Review</h3>

        <div style="background-color: #fffbeb; border: 2px solid #f59e0b; border-radius: 8px; padding: 20px;">
            <div style="display: flex; align-items: center; margin-bottom: 10px;">
                <span style="font-size: 20px; margin-right: 10px;">{{ str_repeat('⭐', $review['rating']) }}</span>
                <span class="status-badge" style="background-color: #fbbf24; color: #92400e;">⭐ FEATURED</span>
            </div>

            @if($review['title'])
                <div style="font-weight: 600; margin-bottom: 8px; color: #92400e;">
                    "{{ $review['title'] }}"
                </div>
            @endif

            <div style="color: #374151; line-height: 1.6; margin-bottom: 10px;">
                "{{ Str::limit($review['content'], 200) }}"
            </div>

            @if($review['is_verified_purchase'])
                <span class="status-badge status-approved">Verified Purchase</span>
            @endif
        </div>
    </div>

    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ config('app.url') }}/products/{{ $review['product']['id'] }}#reviews" class="btn">View Featured Review</a>
    </div>

    <p><strong>Why was your review featured?</strong></p>
    <ul style="color: #4b5563;">
        <li>Provides helpful, detailed information</li>
        <li>Offers valuable insights for other customers</li>
        <li>Demonstrates genuine product experience</li>
        <li>Follows our community guidelines excellently</li>
    </ul>

    <p>Thank you for contributing such valuable feedback to our community. Featured reviews like yours help create trust and provide genuine insights for fellow shoppers!</p>

    <p>Best regards,<br>The {{ config('app.name') }} Team</p>
@endsection
