<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServicePackageItem extends Model
{
    protected $fillable = [
        'service_package_id',
        'service_id',
        'quantity',
        'order',
        'is_optional',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'order' => 'integer',
        'is_optional' => 'boolean',
    ];

    public function package(): BelongsTo
    {
        return $this->belongsTo(ServicePackage::class, 'service_package_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function getTotalPriceAttribute(): int
    {
        return $this->service->base_price * $this->quantity;
    }

    public function getFormattedTotalPriceAttribute(): string
    {
        return '£' . number_format($this->total_price / 100, 2);
    }

    public function getTotalDurationMinutesAttribute(): int
    {
        return $this->service->duration_minutes * $this->quantity;
    }
}
