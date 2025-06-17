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

    public function isSuccessful(): bool
    {
        $refundedStatusId = OrderReturnStatus::where('name', RefundStatuses::REFUNDED)->value('id');
        return $this->orderRefundStatus->id === $refundedStatusId;
    }

    public function isFailed(): bool
    {
        $failedStatusId = OrderReturnStatus::where('name', RefundStatuses::FAILED)->value('id');
        return $this->orderRefundStatus->id === $failedStatusId;
    }

    public function isCancelled(): bool
    {
        $cancelledStatusId = OrderReturnStatus::where('name', RefundStatuses::CANCELLED)->value('id');
        return $this->orderRefundStatus->id === $cancelledStatusId;
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(OrderRefundStatus::class, 'order_refund_status_id');
    }
}
