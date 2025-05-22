<?php

namespace App\Models;

use App\Constants\ReturnStatuses;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    public function status(): BelongsTo
    {
        return $this->belongsTo(OrderReturnStatus::class, 'order_return_status_id');
    }

    public function isApproved(): bool
    {
        $approvedStatusId = OrderReturnStatus::where('name', ReturnStatuses::APPROVED)->value('id');
        return $this->order_return_status_id === $approvedStatusId;
    }
}
