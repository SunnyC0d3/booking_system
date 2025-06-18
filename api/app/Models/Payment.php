<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'user_id',
        'payment_method_id',
        'amount',
        'method',
        'transaction_reference',
        'status',
        'processed_at',
        'response_payload'
    ];

    public function getAmountInPennies(): int
    {
        return (int) $this->amount;
    }

    public function getAmountInPounds(): float
    {
        return $this->amount / 100;
    }

    public function setAmountFromPounds(float $pounds): void
    {
        $this->amount = (int) round($pounds * 100);
    }

    public function setAmountFromPennies(int $pennies): void
    {
        $this->amount = $pennies;
    }

    public function matchesStripeAmount(int $stripeAmountInPennies): bool
    {
        return $this->getAmountInPennies() === $stripeAmountInPennies;
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'processed_at' => 'datetime',
        ];
    }
}
