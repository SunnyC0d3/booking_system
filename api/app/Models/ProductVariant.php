<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'product_attribute_id',
        'value',
        'additional_price',
        'quantity',
        'low_stock_threshold',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productAttribute(): BelongsTo
    {
        return $this->belongsTo(ProductAttribute::class);
    }

    public function getAdditionalPriceFormattedAttribute(): ?string
    {
        if (!$this->additional_price || $this->additional_price <= 0) {
            return null;
        }

        return '£' . number_format($this->additional_price / 100, 2);
    }

    public function isInStock(int $quantity = 1): bool
    {
        return $this->quantity >= $quantity;
    }

    public function getTotalPrice(): int
    {
        return $this->product->price + ($this->additional_price ?? 0);
    }

    public function getTotalPriceFormattedAttribute(): string
    {
        return '£' . number_format($this->getTotalPrice() / 100, 2);
    }

    public function isLowStock(): bool
    {
        return $this->quantity <= $this->low_stock_threshold && $this->quantity > 0;
    }

    public function isOutOfStock(): bool
    {
        return $this->quantity <= 0;
    }
}
