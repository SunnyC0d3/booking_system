@extends('emails.layout')

@section('title', 'Tracking Update - #' . $order['id'])

@section('content')
    <h2 style="color: #1f2937; margin-top: 0;">Tracking Update for Your Order 📦</h2>

    <p>Hi {{ $customer['name'] }},</p>

    <p>We have a new update on your package shipment.</p>

    <div class="highlight-box">
        <strong>Current Status</strong><br>
        <span class="status-badge status-{{ strtolower($tracking['status']) }}">{{ $tracking['status_label'] }}</span><br>
        Tracking Number: {{ $shipment['tracking_number'] }}<br>
        Carrier: {{ $shipment['carrier'] }}<br>
        Updated: {{ $tracking['updated_at'] }}
    </div>

    @if($tracking['location'])
        <div style="background-color: #eff6ff; border-left: 4px solid #2563eb; padding: 15px; margin: 20px 0;">
            📍 <strong>Current Location:</strong> {{ $tracking['location'] }}
        </div>
    @endif

    @if($tracking['description'])
        <div style="margin: 20px 0;">
            <strong>Status Details:</strong><br>
            {{ $tracking['description'] }}
        </div>
    @endif

    @if($tracking['estimated_delivery'])
        <div style="background-color: #d1fae5; border-left: 4px solid #10b981; padding: 15px; margin: 20px 0;">
            🚚 <strong>Estimated Delivery:</strong> {{ $tracking['estimated_delivery'] }}
        </div>
    @endif

    @if($tracking['events'] && count($tracking['events']) > 0)
        <div class="order-summary">
            <h3 style="margin-top: 0; color: #1f2937;">Recent Tracking Events</h3>

            @foreach(array_slice($tracking['events'], 0, 3) as $event)
                <div style="padding: 10px 0; border-bottom: 1px solid #e5e7eb;">
                    <div style="display: flex; justify-content: space-between; align-items: start;">
                        <div>
                            <div style="font-weight: 600; color: #1f2937;">{{ $event['description'] }}</div>
                            @if($event['location'])
                                <div style="color: #6b7280; font-size: 14px;">{{ $event['location'] }}</div>
                            @endif
                        </div>
                        <div style="font-size: 14px; color: #6b7280; text-align: right;">
                            {{ $event['timestamp'] }}
                        </div>
                    </div>
                </div>
            @endforeach

            @if(count($tracking['events']) > 3)
                <div style="text-align: center; padding: 15px 0;">
                    <small style="color: #6b7280;">+ {{ count($tracking['events']) - 3 }} more events</small>
                </div>
            @endif
        </div>
    @endif

    <div class="order-summary">
        <h3 style="margin-top: 0; color: #1f2937;">Order Summary</h3>

        @foreach($order['items'] as $item)
            <div class="order-item">
                <div>
                    <strong>{{ $item['product_name'] }}</strong><br>
                    <span style="color: #6b7280;">Quantity: {{ $item['quantity'] }}</span>
                </div>
                <div style="text-align: right;">
                    <strong>{{ $item['total_formatted'] }}</strong>
                </div>
            </div>
        @endforeach

        <div class="order-item">
            <div><strong>Total</strong></div>
            <div><strong>{{ $order['total_formatted'] }}</strong></div>
        </div>
    </div>

    @if($shipment['tracking_url'])
        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ $shipment['tracking_url'] }}" class="btn">View Full Tracking Details</a>
        </div>
    @endif

    @if($tracking['status'] === 'in_transit')
        <p>📱 <strong>Tip:</strong> Your package is on its way! You can track its progress in real-time using the link above.</p>
    @elseif($tracking['status'] === 'out_for_delivery')
        <p>🚛 <strong>Great news!</strong> Your package is out for delivery and should arrive today!</p>
    @elseif($tracking['status'] === 'exception')
        <p>⚠️ <strong>Delivery Exception:</strong> There was an issue with delivery. Our support team can help if you need assistance.</p>
    @endif

    <p>We'll continue to keep you updated on your shipment's progress. If you have any questions, please don't hesitate to contact us.</p>

    <p>Best regards,<br>The {{ config('app.name') }} Team</p>
@endsection
