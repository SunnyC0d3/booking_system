<?php

namespace App\Models;

use App\Constants\DropshipStatuses;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DropshipOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'dropship_order_id',
        'order_item_id',
        'supplier_product_id',
        'supplier_sku',
        'quantity',
        'supplier_price',
        'retail_price',
        'profit_per_item',
        'product_details',
        'supplier_item_data',
        'status',
        'notes',
    ];

    protected $casts = [
        'supplier_price' => 'integer',
        'retail_price' => 'integer',
        'profit_per_item' => 'integer',
        'product_details' => 'array',
        'supplier_item_data' => 'array',
    ];

    public function dropshipOrder(): BelongsTo
    {
        return $this->belongsTo(DropshipOrder::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function supplierProduct(): BelongsTo
    {
        return $this->belongsTo(SupplierProduct::class);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopePending($query)
    {
        return $query->where('status', DropshipStatuses::PENDING);
    }

    public function scopeBySupplier($query, int $supplierId)
    {
        return $query->whereHas('dropshipOrder', function ($q) use ($supplierId) {
            $q->where('supplier_id', $supplierId);
        });
    }

    public function getSupplierPriceInPounds(): float
    {
        return $this->supplier_price / 100;
    }

    public function getSupplierPriceFormatted(): string
    {
        return '£' . number_format($this->getSupplierPriceInPounds(), 2);
    }

    public function getRetailPriceInPounds(): float
    {
        return $this->retail_price / 100;
    }

    public function getRetailPriceFormatted(): string
    {
        return '£' . number_format($this->getRetailPriceInPounds(), 2);
    }

    public function getProfitPerItemInPounds(): float
    {
        return $this->profit_per_item / 100;
    }

    public function getProfitPerItemFormatted(): string
    {
        return '£' . number_format($this->getProfitPerItemInPounds(), 2);
    }

    public function getTotalSupplierCost(): int
    {
        return $this->supplier_price * $this->quantity;
    }

    public function getTotalSupplierCostInPounds(): float
    {
        return $this->getTotalSupplierCost() / 100;
    }

    public function getTotalSupplierCostFormatted(): string
    {
        return '£' . number_format($this->getTotalSupplierCostInPounds(), 2);
    }

    public function getTotalRetailValue(): int
    {
        return $this->retail_price * $this->quantity;
    }

    public function getTotalRetailValueInPounds(): float
    {
        return $this->getTotalRetailValue() / 100;
    }

    public function getTotalRetailValueFormatted(): string
    {
        return '£' . number_format($this->getTotalRetailValueInPounds(), 2);
    }

    public function getTotalProfit(): int
    {
        return $this->profit_per_item * $this->quantity;
    }

    public function getTotalProfitInPounds(): float
    {
        return $this->getTotalProfit() / 100;
    }

    public function getTotalProfitFormatted(): string
    {
        return '£' . number_format($this->getTotalProfitInPounds(), 2);
    }

    public function getProfitMarginPercentage(): float
    {
        if ($this->retail_price === 0) {
            return 0;
        }

        return round(($this->profit_per_item / $this->retail_price) * 100, 2);
    }

    public function getProfitMarginPercentageFormatted(): string
    {
        return number_format($this->getProfitMarginPercentage(), 2) . '%';
    }

    public function isPending(): bool
    {
        return $this->status === DropshipStatuses::PENDING;
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

    public function updateStatus(string $status): void
    {
        $this->update(['status' => $status]);
    }

    public function getStatusLabel(): string
    {
        return DropshipStatuses::labels()[$this->status] ?? 'Unknown';
    }

    public function getProductName(): string
    {
        return $this->product_details['name'] ?? $this->supplierProduct->name ?? 'Unknown Product';
    }

    public function getProductDescription(): string
    {
        return $this->product_details['description'] ?? $this->supplierProduct->description ?? '';
    }

    public function hasProductImage(): bool
    {
        return !empty($this->product_details['image']) ||
            !empty($this->supplierProduct->images);
    }

    public function getProductImage(): ?string
    {
        return $this->product_details['image'] ??
            ($this->supplierProduct->images[0] ?? null);
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, [
            DropshipStatuses::PENDING,
            DropshipStatuses::SENT_TO_SUPPLIER
        ]);
    }

    public function getWeight(): float
    {
        return $this->product_details['weight'] ?? $this->supplierProduct->weight ?? 0;
    }

    public function getTotalWeight(): float
    {
        return $this->getWeight() * $this->quantity;
    }

    public function getDimensions(): array
    {
        return [
            'length' => $this->product_details['length'] ?? $this->supplierProduct->length ?? 0,
            'width' => $this->product_details['width'] ?? $this->supplierProduct->width ?? 0,
            'height' => $this->product_details['height'] ?? $this->supplierProduct->height ?? 0,
        ];
    }

    public function getSupplierData(): array
    {
        return [
            'sku' => $this->supplier_sku,
            'product_id' => $this->supplierProduct->supplier_product_id,
            'name' => $this->getProductName(),
            'quantity' => $this->quantity,
            'unit_price' => $this->getSupplierPriceInPounds(),
            'total_price' => $this->getTotalSupplierCostInPounds(),
            'weight' => $this->getWeight(),
            'dimensions' => $this->getDimensions(),
            'attributes' => $this->supplierProduct->attributes ?? [],
        ];
    }

    public function syncWithSupplierProduct(): void
    {
        if (!$this->supplierProduct) {
            return;
        }

        $this->update([
            'product_details' => [
                'name' => $this->supplierProduct->name,
                'description' => $this->supplierProduct->description,
                'weight' => $this->supplierProduct->weight,
                'length' => $this->supplierProduct->length,
                'width' => $this->supplierProduct->width,
                'height' => $this->supplierProduct->height,
                'images' => $this->supplierProduct->images,
                'attributes' => $this->supplierProduct->attributes,
            ]
        ]);
    }
}
