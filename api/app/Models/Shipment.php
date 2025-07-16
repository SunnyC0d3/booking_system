<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Shipment extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'shipping_method_id',
        'tracking_number',
        'carrier',
        'service_name',
        'status',
        'shipping_cost',
        'label_url',
        'tracking_url',
        'shipped_at',
        'delivered_at',
        'estimated_delivery',
        'notes',
        'carrier_data',
    ];

    protected $casts = [
        'shipping_cost' => 'integer',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
        'estimated_delivery' => 'datetime',
        'carrier_data' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function shippingMethod(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByCarrier($query, string $carrier)
    {
        return $query->where('carrier', $carrier);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeShipped($query)
    {
        return $query->where('status', 'shipped');
    }

    public function scopeDelivered($query)
    {
        return $query->where('status', 'delivered');
    }

    public function scopeInTransit($query)
    {
        return $query->whereIn('status', ['shipped', 'in_transit', 'out_for_delivery']);
    }

    public function getShippingCostInPennies(): int
    {
        return (int) $this->shipping_cost;
    }

    public function getShippingCostInPounds(): float
    {
        return $this->shipping_cost / 100;
    }

    public function getShippingCostFormatted(): string
    {
        return '£' . number_format($this->shipping_cost / 100, 2);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isShipped(): bool
    {
        return in_array($this->status, ['shipped', 'in_transit', 'out_for_delivery', 'delivered']);
    }

    public function isDelivered(): bool
    {
        return $this->status === 'delivered';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function hasTrackingNumber(): bool
    {
        return !empty($this->tracking_number);
    }

    public function hasLabel(): bool
    {
        return !empty($this->label_url);
    }

    public function getTrackingUrl(): ?string
    {
        if ($this->tracking_url) {
            return $this->tracking_url;
        }

        if (!$this->hasTrackingNumber()) {
            return null;
        }

        return $this->generateTrackingUrl();
    }

    public function getStatusLabel(): string
    {
        return match($this->status) {
            'pending' => 'Pending',
            'processing' => 'Processing',
            'shipped' => 'Shipped',
            'in_transit' => 'In Transit',
            'out_for_delivery' => 'Out for Delivery',
            'delivered' => 'Delivered',
            'failed' => 'Failed',
            'cancelled' => 'Cancelled',
            'returned' => 'Returned',
            default => ucfirst($this->status),
        };
    }

    public function getStatusColor(): string
    {
        return match($this->status) {
            'pending' => 'yellow',
            'processing' => 'blue',
            'shipped', 'in_transit' => 'indigo',
            'out_for_delivery' => 'purple',
            'delivered' => 'green',
            'failed', 'cancelled' => 'red',
            'returned' => 'orange',
            default => 'gray',
        };
    }

    public function getDaysInTransit(): ?int
    {
        if (!$this->shipped_at) {
            return null;
        }

        $endDate = $this->delivered_at ?? now();
        return $this->shipped_at->diffInDays($endDate);
    }

    public function isOverdue(): bool
    {
        if ($this->isDelivered() || !$this->estimated_delivery) {
            return false;
        }

        return now()->isAfter($this->estimated_delivery);
    }

    public function markAsShipped(string $trackingNumber = null, string $labelUrl = null): void
    {
        $this->update([
            'status' => 'shipped',
            'shipped_at' => now(),
            'tracking_number' => $trackingNumber ?? $this->tracking_number,
            'label_url' => $labelUrl ?? $this->label_url,
        ]);

        $this->order->update([
            'fulfillment_status' => 'fulfilled',
            'shipped_at' => now(),
            'tracking_number' => $trackingNumber ?? $this->tracking_number,
        ]);
    }

    public function markAsDelivered(): void
    {
        $this->update([
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);
    }

    public function updateStatus(string $status, array $additionalData = []): void
    {
        $updateData = array_merge(['status' => $status], $additionalData);

        if ($status === 'delivered' && !$this->delivered_at) {
            $updateData['delivered_at'] = now();
        }

        $this->update($updateData);
    }

    protected function generateTrackingUrl(): ?string
    {
        $trackingUrls = [
            'royal-mail' => 'https://www.royalmail.com/track-your-item#/tracking-results/',
            'dpd' => 'https://www.dpd.co.uk/apps/tracking/?reference=',
            'ups' => 'https://www.ups.com/track?loc=en_GB&tracknum=',
            'fedex' => 'https://www.fedex.com/fedextrack/?trknbr=',
            'hermes' => 'https://www.myhermes.co.uk/track#/parcel/',
        ];

        $carrierKey = strtolower(str_replace(' ', '-', $this->carrier));

        if (isset($trackingUrls[$carrierKey])) {
            return $trackingUrls[$carrierKey] . $this->tracking_number;
        }

        return null;
    }
}
