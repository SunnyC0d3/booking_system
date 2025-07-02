<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cart extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'session_id',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function getTotalAmountInPennies(): int
    {
        return $this->cartItems->sum(function ($item) {
            return $item->price_snapshot * $item->quantity;
        });
    }

    public function getTotalAmountFormatted(): string
    {
        return '£' . number_format($this->getTotalAmountInPennies() / 100, 2);
    }

    public function getTotalItemsCount(): int
    {
        return $this->cartItems->sum('quantity');
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isEmpty(): bool
    {
        return $this->cartItems->isEmpty();
    }

    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
    }

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }

    public function extendExpiry(int $hours = 24): void
    {
        $this->update(['expires_at' => now()->addHours($hours)]);
    }

    public function clear(): void
    {
        $this->cartItems()->delete();
    }
}
