<?php

namespace App\Models;

use App\Constants\FulfillmentStatuses;
use App\Constants\OrderStatuses;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'status_id',
        'shipping_method_id',
        'shipping_address_id',
        'total_amount',
        'shipping_cost',
        'tracking_number',
        'shipped_at',
        'fulfillment_status',
        'shipping_notes',
    ];

    protected $casts = [
        'total_amount' => 'integer',
        'shipping_cost' => 'integer',
        'shipped_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $with = ['status'];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(OrderStatus::class, 'status_id');
    }

    public function shippingMethod(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class);
    }

    public function shippingAddress(): BelongsTo
    {
        return $this->belongsTo(ShippingAddress::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    // Shipping-related methods
    public function requiresShipping(): bool
    {
        return $this->orderItems()
            ->whereHas('product', function ($query) {
                $query->where('requires_shipping', true)
                    ->where('is_virtual', false);
            })
            ->exists();
    }

    public function canShip(): bool
    {
        if (!$this->requiresShipping()) {
            return false;
        }

        if (!$this->shippingAddress || !$this->shippingMethod) {
            return false;
        }

        // Check if order status allows shipping
        if (!in_array($this->status->name, ['confirmed', 'processing', 'ready_to_ship'])) {
            return false;
        }

        // Check if order has been paid (if required)
        if (!$this->isPaid() && $this->requiresPayment()) {
            return false;
        }

        // Check if all items are in stock
        if (!$this->hasStockForAllItems()) {
            return false;
        }

        return true;
    }

    public function isPaid(): bool
    {
        return $this->payments()
            ->whereIn('status', ['paid', 'partially_refunded'])
            ->exists();
    }

    public function requiresPayment(): bool
    {
        return $this->total_amount > 0;
    }

    public function hasStockForAllItems(): bool
    {
        return $this->orderItems->every(function ($item) {
            return $item->product->hasStock($item->quantity);
        });
    }

    public function getShippingWeight(): float
    {
        return $this->orderItems()
            ->with('product')
            ->get()
            ->sum(function ($item) {
                if (!$item->product->requiresShipping()) {
                    return 0;
                }
                return $item->product->getWeightInKg() * $item->quantity;
            });
    }

    public function getShippingDimensions(): array
    {
        $maxLength = 0;
        $maxWidth = 0;
        $totalHeight = 0;

        foreach ($this->orderItems as $item) {
            if (!$item->product->requiresShipping()) {
                continue;
            }

            $product = $item->product;
            $quantity = $item->quantity;

            $maxLength = max($maxLength, $product->length ?? 0);
            $maxWidth = max($maxWidth, $product->width ?? 0);
            $totalHeight += ($product->height ?? 0) * $quantity;
        }

        return [
            'length' => max($maxLength, 10), // Min 10cm
            'width' => max($maxWidth, 10),   // Min 10cm
            'height' => max($totalHeight, 5), // Min 5cm
        ];
    }

    public function getTotalAmountInPennies(): int
    {
        return $this->total_amount;
    }

    public function getTotalAmountInPounds(): float
    {
        return $this->total_amount / 100;
    }

    public function getTotalAmountFormatted(): string
    {
        return '£' . number_format($this->getTotalAmountInPounds(), 2);
    }

    public function getShippingCostInPennies(): int
    {
        return $this->shipping_cost;
    }

    public function getShippingCostInPounds(): float
    {
        return $this->shipping_cost / 100;
    }

    public function getShippingCostFormatted(): string
    {
        return '£' . number_format($this->getShippingCostInPounds(), 2);
    }

    public function setTotalAmountFromPennies(int $amountInPennies): void
    {
        $this->total_amount = $amountInPennies;
    }

    public function setShippingCostFromPennies(int $costInPennies): void
    {
        $this->shipping_cost = $costInPennies;
    }

    public function createShipment(array $data = []): Shipment
    {
        $defaultData = [
            'shipping_method_id' => $this->shipping_method_id,
            'carrier' => $this->shippingMethod->carrier ?? 'Unknown',
            'service_name' => $this->shippingMethod->name ?? null,
            'shipping_cost' => $this->shipping_cost,
            'estimated_delivery' => $this->calculateEstimatedDelivery(),
        ];

        return $this->shipments()->create(array_merge($defaultData, $data));
    }

    public function hasActiveShipment(): bool
    {
        return $this->shipments()
            ->whereNotIn('status', ['cancelled', 'failed'])
            ->exists();
    }

    public function getActiveShipment(): ?Shipment
    {
        return $this->shipments()
            ->whereNotIn('status', ['cancelled', 'failed'])
            ->latest()
            ->first();
    }

    public function updateFulfillmentStatus(): void
    {
        if (!$this->requiresShipping()) {
            $this->update(['fulfillment_status' => FulfillmentStatuses::FULFILLED]);
            return;
        }

        $shipments = $this->shipments()->get();

        if ($shipments->isEmpty()) {
            $this->update(['fulfillment_status' => FulfillmentStatuses::UNFULFILLED]);
            return;
        }

        $deliveredCount = $shipments->where('status', 'delivered')->count();
        $shippedCount = $shipments->where('status', 'shipped')->count();
        $totalShipments = $shipments->count();

        if ($deliveredCount === $totalShipments) {
            $this->update(['fulfillment_status' => FulfillmentStatuses::DELIVERED]);
        } elseif ($deliveredCount > 0) {
            $this->update(['fulfillment_status' => FulfillmentStatuses::PARTIALLY_DELIVERED]);
        } elseif ($shippedCount === $totalShipments) {
            $this->update(['fulfillment_status' => FulfillmentStatuses::SHIPPED]);
        } elseif ($shippedCount > 0) {
            $this->update(['fulfillment_status' => FulfillmentStatuses::PARTIALLY_SHIPPED]);
        } else {
            $this->update(['fulfillment_status' => FulfillmentStatuses::FULFILLED]);
        }
    }

    public function isShipped(): bool
    {
        return in_array($this->fulfillment_status, [
            FulfillmentStatuses::SHIPPED,
            FulfillmentStatuses::PARTIALLY_SHIPPED,
            FulfillmentStatuses::DELIVERED,
            FulfillmentStatuses::PARTIALLY_DELIVERED,
        ]);
    }

    public function isDelivered(): bool
    {
        return in_array($this->fulfillment_status, [
            FulfillmentStatuses::DELIVERED,
            FulfillmentStatuses::PARTIALLY_DELIVERED,
        ]);
    }

    private function calculateEstimatedDelivery(): ?\Carbon\Carbon
    {
        if (!$this->shippingMethod) {
            return null;
        }

        $days = $this->shippingMethod->estimated_days_max ?? 3;
        return now()->addDays($days)->setTime(17, 0); // 5 PM delivery
    }

    // Scopes for filtering
    public function scopeRequiresShipping($query)
    {
        return $query->whereHas('orderItems.product', function ($q) {
            $q->where('requires_shipping', true)
                ->where('is_virtual', false);
        });
    }

    public function scopeWithFulfillmentStatus($query, string $status)
    {
        return $query->where('fulfillment_status', $status);
    }

    public function scopeUnfulfilled($query)
    {
        return $query->where('fulfillment_status', FulfillmentStatuses::UNFULFILLED);
    }

    public function scopeShipped($query)
    {
        return $query->whereIn('fulfillment_status', [
            FulfillmentStatuses::SHIPPED,
            FulfillmentStatuses::PARTIALLY_SHIPPED,
        ]);
    }

    public function scopeDelivered($query)
    {
        return $query->whereIn('fulfillment_status', [
            FulfillmentStatuses::DELIVERED,
            FulfillmentStatuses::PARTIALLY_DELIVERED,
        ]);
    }
}
