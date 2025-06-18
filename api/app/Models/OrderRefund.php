<?php

namespace App\Models;

use App\Constants\RefundStatuses;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class OrderRefund extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_return_id',
        'amount',
        'order_refund_status_id',
        'processed_at',
        'notes'
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

    protected function casts(): array
    {
        return [
            'processed_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderReturn(): BelongsTo
    {
        return $this->belongsTo(OrderReturn::class);
    }

    public function orderRefundStatus(): BelongsTo
    {
        return $this->belongsTo(OrderRefundStatus::class, 'order_refund_status_id');
    }

    public function isSuccessful(): bool
    {
        $refundedStatusId = OrderRefundStatus::where('name', RefundStatuses::REFUNDED)->value('id');
        return $this->order_refund_status_id === $refundedStatusId;
    }

    public function isFailed(): bool
    {
        $failedStatusId = OrderRefundStatus::where('name', RefundStatuses::FAILED)->value('id');
        return $this->order_refund_status_id === $failedStatusId;
    }

    public function isCancelled(): bool
    {
        $cancelledStatusId = OrderRefundStatus::where('name', RefundStatuses::CANCELLED)->value('id');
        return $this->order_refund_status_id === $cancelledStatusId;
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(OrderRefundStatus::class, 'order_refund_status_id');
    }
}
