<?php

namespace App\Models;

use App\Constants\ProductStatuses;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'cart_id',
        'product_id',
        'product_variant_id',
        'quantity',
        'price_snapshot',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'price_snapshot' => 'integer',
    ];

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function getLineTotalInPennies(): int
    {
        return $this->price_snapshot * $this->quantity;
    }

    public function getLineTotalFormatted(): string
    {
        return '£' . number_format($this->getLineTotalInPennies() / 100, 2);
    }

    public function getPriceFormatted(): string
    {
        return '£' . number_format($this->price_snapshot / 100, 2);
    }

    public function getCurrentProductPrice(): int
    {
        $basePrice = $this->product->price;

        if ($this->productVariant && $this->productVariant->additional_price) {
            $basePrice += $this->productVariant->additional_price;
        }

        return $basePrice;
    }

    public function hasPriceChanged(): bool
    {
        return $this->price_snapshot !== $this->getCurrentProductPrice();
    }

    public function getPriceChange(): int
    {
        return $this->getCurrentProductPrice() - $this->price_snapshot;
    }

    public function isProductAvailable(): bool
    {
        if (!$this->product) {
            return false;
        }

        if ($this->product->productStatus->name !== ProductStatuses::ACTIVE) {
            return false;
        }

        if ($this->productVariant) {
            return $this->productVariant->quantity >= $this->quantity;
        }

        return $this->product->quantity >= $this->quantity;
    }

    public function getAvailableStock(): int
    {
        if ($this->productVariant) {
            return $this->productVariant->quantity;
        }

        return $this->product->quantity;
    }

    public function updatePriceSnapshot(): void
    {
        $this->update(['price_snapshot' => $this->getCurrentProductPrice()]);
    }
}
