@extends('emails.layout')

@section('title', 'Low Stock Alert')

@section('content')
    <h2 style="color: #f59e0b; margin-top: 0;">📦 Inventory Alert: Low Stock Items</h2>

    <p>Hello Administrator,</p>

    <p>Our system has detected <strong>{{ $total_items }}</strong> items that are running low on stock and need your immediate attention.</p>

    @if($urgent_items > 0)
        <div style="background-color: #fef2f2; border-left: 4px solid #dc2626; padding: 15px; margin: 20px 0;">
            <strong>🚨 URGENT: {{ $urgent_items }} items are completely out of stock!</strong>
        </div>
    @endif

    <div class="order-summary">
        <h3 style="margin-top: 0; color: #1f2937;">Low Stock Items</h3>

        @foreach($items as $item)
            <div class="order-item" style="border-bottom: 1px solid #e5e7eb; padding-bottom: 10px; margin-bottom: 10px;">
                <div>
                    <strong>{{ $item['name'] }}</strong><br>
                    <span style="color: #6b7280; font-size: 14px;">{{ $item['vendor'] ?? 'No vendor' }}</span>
                </div>
                <div>
                    @if($item['current_stock'] <= 0)
                        <span style="color: #dc2626; font-weight: bold;">OUT OF STOCK</span>
                    @else
                        <span style="color: #f59e0b; font-weight: bold;">{{ $item['current_stock'] }} remaining</span>
                    @endif
                    <br>
                    <span style="color: #6b7280; font-size: 14px;">Threshold: {{ $item['threshold'] }}</span>
                </div>
            </div>
        @endforeach
    </div>

    <div style="background-color: #dbeafe; border-left: 4px solid #2563eb; padding: 15px; margin: 20px 0;">
        <h4 style="margin-top: 0; color: #1e40af;">📋 Recommended Actions</h4>
        <ul style="margin: 0; color: #1e40af;">
            <li>Review stock levels and reorder from suppliers</li>
            <li>Update product availability on your website</li>
            <li>Consider adjusting stock thresholds if needed</li>
            <li>Check for any pending supplier orders</li>
            @if($urgent_items > 0)
                <li><strong>Prioritize out-of-stock items immediately</strong></li>
            @endif
        </ul>
    </div>

    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ config('app.url') }}/admin/inventory" class="btn">Manage Inventory</a>
        <a href="{{ config('app.url') }}/admin/suppliers" class="btn btn-secondary">View Suppliers</a>
    </div>

    <div style="font-size: 14px; color: #6b7280; margin-top: 30px;">
        <p><strong>Alert generated:</strong> {{ $generated_at }}</p>
        <p><strong>Next check:</strong> In 1 hour (automated)</p>
    </div>

    <p>Best regards,<br>{{ config('app.name') }} Inventory System</p>
@endsection
