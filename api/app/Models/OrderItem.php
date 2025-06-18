<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'product_variant_id',
        'quantity',
        'price',
    ];

    public function refundAmount(): int
    {
        return (int) ($this->price * $this->quantity);
    }

    public function getPriceInPennies(): int
    {
        return (int) $this->price;
    }

    public function getPriceInPounds(): float
    {
        return $this->price / 100;
    }

    public function getLineTotalInPennies(): int
    {
        return $this->refundAmount();
    }

    public function getLineTotalInPounds(): float
    {
        return $this->getLineTotalInPennies() / 100;
    }

    public function setPriceFromPounds(float $pounds): void
    {
        $this->price = (int) round($pounds * 100);
    }

    public function setPriceFromPennies(int $pennies): void
    {
        $this->price = $pennies;
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderReturn(): HasOne
    {
        return $this->hasOne(OrderReturn::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function scopeWithRefundAmount($query)
    {
        return $query->selectRaw('*, (price * quantity) as refund_amount_pennies');
    }
}
