<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Order extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'status_id',
        'total_amount',
    ];

    public function setTotalAmountAttribute(int|float $value)
    {
        $this->attributes['total_amount'] = round($value, 2) * 100;
    }

    public function getTotalAmountAttribute(int|float $value)
    {
        return round($value, 2) * 100;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(OrderStatus::class);
    }


    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
