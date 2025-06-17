<?php

namespace App\Models;

use App\Constants\RefundStatuses;
use App\Constants\ReturnStatuses;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class OrderReturn extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_item_id',
        'reason',
        'order_return_status_id',
    ];

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function orderRefund(): BelongsTo
    {
        return $this->belongsTo(OrderRefund::class, 'id', 'order_return_id')
            ->latest();
    }

    public function orderRefunds(): HasMany
    {
        return $this->hasMany(OrderRefund::class, 'order_return_id');
    }

    public function hasRefunds(): bool
    {
        return $this->orderRefunds()->exists();
    }

    public function getTotalRefundedAmount(): int
    {
        return $this->orderRefunds()
            ->whereHas('status', function ($query) {
                $query->where('name', RefundStatuses::REFUNDED);
            })
            ->sum('amount');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(OrderReturnStatus::class, 'order_return_status_id');
    }

    public function isApproved(): bool
    {
        $approvedStatusId = OrderReturnStatus::where('name', ReturnStatuses::APPROVED)->value('id');
        return $this->order_return_status_id === $approvedStatusId;
    }

    public function isCompleted(): bool
    {
        $completedStatusId = OrderReturnStatus::where('name', ReturnStatuses::COMPLETED)->value('id');
        return $this->order_return_status_id === $completedStatusId;
    }
}
