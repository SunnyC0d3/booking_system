<?php

namespace App\Models;

use App\Constants\ShippingStatuses;
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
        return $query->where('status', ShippingStatuses::PENDING);
    }

    public function scopeShipped($query)
    {
        return $query->where('status', ShippingStatuses::SHIPPED);
    }

    public function scopeDelivered($query)
    {
        return $query->where('status', ShippingStatuses::DELIVERED);
    }

    public function scopeInTransit($query)
    {
        return $query->whereIn('status', [
            ShippingStatuses::SHIPPED,
            ShippingStatuses::IN_TRANSIT,
            ShippingStatuses::OUT_FOR_DELIVERY
        ]);
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
        return $this->status === ShippingStatuses::PENDING;
    }

    public function isShipped(): bool
    {
        return in_array($this->status, [
            ShippingStatuses::SHIPPED,
            ShippingStatuses::IN_TRANSIT,
            ShippingStatuses::OUT_FOR_DELIVERY,
            ShippingStatuses::DELIVERED
        ]);
    }

    public function isDelivered(): bool
    {
        return $this->status === ShippingStatuses::DELIVERED;
    }

    public function isCancelled(): bool
    {
        return $this->status === ShippingStatuses::CANCELLED;
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
        return ShippingStatuses::getStatusLabel($this->status);
    }

    public function getStatusColor(): string
    {
        return ShippingStatuses::getStatusColor($this->status);
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
            'status' => ShippingStatuses::SHIPPED,
            'shipped_at' => now(),
            'tracking_number' => $trackingNumber ?? $this->tracking_number,
            'label_url' => $labelUrl ?? $this->label_url,
        ]);

        $this->order->update([
            'fulfillment_status' => FulfillmentStatuses::FULFILLED,
            'shipped_at' => now(),
            'tracking_number' => $trackingNumber ?? $this->tracking_number,
        ]);
    }

    public function markAsDelivered(): void
    {
        $this->update([
            'status' => ShippingStatuses::DELIVERED,
            'delivered_at' => now(),
        ]);
    }

    public function updateStatus(string $status, array $additionalData = []): void
    {
        $updateData = array_merge(['status' => $status], $additionalData);

        if ($status === ShippingStatuses::DELIVERED && !$this->delivered_at) {
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
