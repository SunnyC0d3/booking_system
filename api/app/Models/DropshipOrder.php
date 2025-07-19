<?php

namespace App\Models;

use App\Constants\DropshipStatuses;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DropshipOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'supplier_id',
        'supplier_order_id',
        'status',
        'total_cost',
        'total_retail',
        'profit_margin',
        'shipping_address',
        'tracking_number',
        'carrier',
        'sent_to_supplier_at',
        'confirmed_by_supplier_at',
        'shipped_by_supplier_at',
        'delivered_at',
        'estimated_delivery',
        'supplier_response',
        'notes',
        'supplier_notes',
        'retry_count',
        'last_retry_at',
        'auto_retry_enabled',
        'webhook_data',
    ];

    protected $casts = [
        'total_cost' => 'integer',
        'total_retail' => 'integer',
        'profit_margin' => 'integer',
        'shipping_address' => 'array',
        'supplier_response' => 'array',
        'webhook_data' => 'array',
        'auto_retry_enabled' => 'boolean',
        'sent_to_supplier_at' => 'datetime',
        'confirmed_by_supplier_at' => 'datetime',
        'shipped_by_supplier_at' => 'datetime',
        'delivered_at' => 'datetime',
        'estimated_delivery' => 'datetime',
        'last_retry_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function dropshipOrderItems(): HasMany
    {
        return $this->hasMany(DropshipOrderItem::class);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopePending($query)
    {
        return $query->where('status', DropshipStatuses::PENDING);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', DropshipStatuses::getActiveStatuses());
    }

    public function scopeCompleted($query)
    {
        return $query->whereIn('status', DropshipStatuses::getCompletedStatuses());
    }

    public function scopeOverdue($query)
    {
        return $query->where('estimated_delivery', '<', now())
            ->whereNotIn('status', [DropshipStatuses::DELIVERED, DropshipStatuses::CANCELLED]);
    }

    public function scopeNeedsRetry($query)
    {
        return $query->whereIn('status', [DropshipStatuses::PENDING, DropshipStatuses::REJECTED_BY_SUPPLIER])
            ->where('auto_retry_enabled', true)
            ->where('retry_count', '<', 3);
    }

    public function getTotalCostInPounds(): float
    {
        return $this->total_cost / 100;
    }

    public function getTotalCostFormatted(): string
    {
        return '£' . number_format($this->getTotalCostInPounds(), 2);
    }

    public function getTotalRetailInPounds(): float
    {
        return $this->total_retail / 100;
    }

    public function getTotalRetailFormatted(): string
    {
        return '£' . number_format($this->getTotalRetailInPounds(), 2);
    }

    public function getProfitMarginInPounds(): float
    {
        return $this->profit_margin / 100;
    }

    public function getProfitMarginFormatted(): string
    {
        return '£' . number_format($this->getProfitMarginInPounds(), 2);
    }

    public function getProfitMarginPercentage(): float
    {
        if ($this->total_retail === 0) {
            return 0;
        }

        return round(($this->profit_margin / $this->total_retail) * 100, 2);
    }

    public function getProfitMarginPercentageFormatted(): string
    {
        return number_format($this->getProfitMarginPercentage(), 2) . '%';
    }

    public function isPending(): bool
    {
        return $this->status === DropshipStatuses::PENDING;
    }

    public function isSentToSupplier(): bool
    {
        return $this->status === DropshipStatuses::SENT_TO_SUPPLIER;
    }

    public function isConfirmed(): bool
    {
        return $this->status === DropshipStatuses::CONFIRMED_BY_SUPPLIER;
    }

    public function isShipped(): bool
    {
        return in_array($this->status, [
            DropshipStatuses::SHIPPED_BY_SUPPLIER,
            DropshipStatuses::DELIVERED
        ]);
    }

    public function isDelivered(): bool
    {
        return $this->status === DropshipStatuses::DELIVERED;
    }

    public function isCancelled(): bool
    {
        return $this->status === DropshipStatuses::CANCELLED;
    }

    public function isRejected(): bool
    {
        return $this->status === DropshipStatuses::REJECTED_BY_SUPPLIER;
    }

    public function isCompleted(): bool
    {
        return in_array($this->status, DropshipStatuses::getCompletedStatuses());
    }

    public function canRetry(): bool
    {
        return $this->auto_retry_enabled &&
            $this->retry_count < 3 &&
            in_array($this->status, [DropshipStatuses::PENDING, DropshipStatuses::REJECTED_BY_SUPPLIER]);
    }

    public function isOverdue(): bool
    {
        return $this->estimated_delivery &&
            $this->estimated_delivery->isPast() &&
            !$this->isDelivered() &&
            !$this->isCancelled();
    }

    public function updateStatus(string $status, array $additionalData = []): void
    {
        $updateData = array_merge(['status' => $status], $additionalData);

        switch ($status) {
            case DropshipStatuses::SENT_TO_SUPPLIER:
                $updateData['sent_to_supplier_at'] = now();
                break;
            case DropshipStatuses::CONFIRMED_BY_SUPPLIER:
                $updateData['confirmed_by_supplier_at'] = now();
                break;
            case DropshipStatuses::SHIPPED_BY_SUPPLIER:
                $updateData['shipped_by_supplier_at'] = now();
                break;
            case DropshipStatuses::DELIVERED:
                $updateData['delivered_at'] = now();
                break;
        }

        $this->update($updateData);
    }

    public function markAsSentToSupplier(array $supplierResponse = []): void
    {
        $this->updateStatus(DropshipStatuses::SENT_TO_SUPPLIER, [
            'supplier_response' => $supplierResponse
        ]);
    }

    public function markAsConfirmed(string $supplierOrderId, array $supplierResponse = []): void
    {
        $this->updateStatus(DropshipStatuses::CONFIRMED_BY_SUPPLIER, [
            'supplier_order_id' => $supplierOrderId,
            'supplier_response' => $supplierResponse
        ]);
    }

    public function markAsShipped(string $trackingNumber, string $carrier = null, ?\Carbon\Carbon $estimatedDelivery = null): void
    {
        $this->updateStatus(DropshipStatuses::SHIPPED_BY_SUPPLIER, [
            'tracking_number' => $trackingNumber,
            'carrier' => $carrier,
            'estimated_delivery' => $estimatedDelivery
        ]);
    }

    public function markAsDelivered(): void
    {
        $this->updateStatus(DropshipStatuses::DELIVERED);
    }

    public function markAsCancelled(string $reason = null): void
    {
        $this->updateStatus(DropshipStatuses::CANCELLED, [
            'notes' => $this->notes . ($reason ? "\nCancelled: {$reason}" : '')
        ]);
    }

    public function markAsRejected(string $reason = null): void
    {
        $this->updateStatus(DropshipStatuses::REJECTED_BY_SUPPLIER, [
            'supplier_notes' => $reason
        ]);
    }

    public function incrementRetryCount(): void
    {
        $this->update([
            'retry_count' => $this->retry_count + 1,
            'last_retry_at' => now()
        ]);
    }

    public function getProcessingTime(): ?int
    {
        if (!$this->sent_to_supplier_at || !$this->shipped_by_supplier_at) {
            return null;
        }

        return $this->sent_to_supplier_at->diffInHours($this->shipped_by_supplier_at);
    }

    public function getProcessingTimeFormatted(): string
    {
        $hours = $this->getProcessingTime();

        if ($hours === null) {
            return 'N/A';
        }

        if ($hours < 24) {
            return $hours . ' hours';
        }

        $days = round($hours / 24, 1);
        return $days . ' days';
    }

    public function getStatusLabel(): string
    {
        return DropshipStatuses::labels()[$this->status] ?? 'Unknown';
    }

    public function getShippingAddressFormatted(): string
    {
        $address = $this->shipping_address;

        if (!$address) {
            return 'No address';
        }

        $parts = [
            $address['name'] ?? '',
            $address['line1'] ?? '',
            $address['line2'] ?? '',
            $address['city'] ?? '',
            $address['county'] ?? '',
            $address['postcode'] ?? '',
            $address['country'] ?? ''
        ];

        return implode(', ', array_filter($parts));
    }

    public function getEstimatedDeliveryFormatted(): string
    {
        if (!$this->estimated_delivery) {
            return 'Not set';
        }

        return $this->estimated_delivery->format('d/m/Y');
    }

    public function getDaysUntilDelivery(): ?int
    {
        if (!$this->estimated_delivery) {
            return null;
        }

        return now()->diffInDays($this->estimated_delivery, false);
    }
}
