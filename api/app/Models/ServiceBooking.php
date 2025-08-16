<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class ServiceBooking extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'service_id',
        'scheduled_at',
        'ends_at',
        'duration_minutes',
        'price',
        'order',
        'status',
        'is_optional',
        'notes',
        'completed_at',
        'metadata',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'ends_at' => 'datetime',
        'duration_minutes' => 'integer',
        'price' => 'integer',
        'order' => 'integer',
        'is_optional' => 'boolean',
        'completed_at' => 'datetime',
        'metadata' => 'array',
    ];

    // Relationships
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    // Scopes
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeInProgress(Builder $query): Builder
    {
        return $query->where('status', 'in_progress');
    }

    public function scopeRequired(Builder $query): Builder
    {
        return $query->where('is_optional', false);
    }

    public function scopeOptional(Builder $query): Builder
    {
        return $query->where('is_optional', true);
    }

    public function scopeForDate(Builder $query, Carbon $date): Builder
    {
        return $query->whereDate('scheduled_at', $date);
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('scheduled_at', '>', now());
    }

    public function scopeOrderedBySchedule(Builder $query): Builder
    {
        return $query->orderBy('order')->orderBy('scheduled_at');
    }

    // Accessors & Mutators
    public function getFormattedPriceAttribute(): string
    {
        return '£' . number_format($this->price / 100, 2);
    }

    public function getFormattedDurationAttribute(): string
    {
        $hours = floor($this->duration_minutes / 60);
        $minutes = $this->duration_minutes % 60;

        if ($hours > 0 && $minutes > 0) {
            return "{$hours}h {$minutes}m";
        } elseif ($hours > 0) {
            return "{$hours}h";
        } else {
            return "{$minutes}m";
        }
    }

    public function getStatusDisplayAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'Pending',
            'confirmed' => 'Confirmed',
            'in_progress' => 'In Progress',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            'skipped' => 'Skipped',
            default => ucfirst($this->status ?? 'pending')
        };
    }

    public function getIsCompletedAttribute(): bool
    {
        return $this->status === 'completed';
    }

    public function getIsPendingAttribute(): bool
    {
        return $this->status === 'pending' || $this->status === null;
    }

    public function getIsInProgressAttribute(): bool
    {
        return $this->status === 'in_progress';
    }

    // Helper methods
    public function markAsCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    public function markAsInProgress(): void
    {
        $this->update(['status' => 'in_progress']);
    }

    public function markAsCancelled(): void
    {
        $this->update(['status' => 'cancelled']);
    }

    public function markAsSkipped(): void
    {
        $this->update(['status' => 'skipped']);
    }

    public function canBeCompleted(): bool
    {
        return in_array($this->status, ['pending', 'confirmed', 'in_progress']);
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, ['pending', 'confirmed']);
    }

    public function isPast(): bool
    {
        return $this->scheduled_at->isPast();
    }

    public function isToday(): bool
    {
        return $this->scheduled_at->isToday();
    }

    public function isUpcoming(): bool
    {
        return $this->scheduled_at->isFuture();
    }

    // Static helper methods
    public static function getValidationRules(): array
    {
        return [
            'booking_id' => 'required|exists:bookings,id',
            'service_id' => 'required|exists:services,id',
            'scheduled_at' => 'required|date',
            'ends_at' => 'required|date|after:scheduled_at',
            'duration_minutes' => 'required|integer|min:1',
            'price' => 'required|integer|min:0',
            'order' => 'integer|min:0',
            'status' => 'nullable|in:pending,confirmed,in_progress,completed,cancelled,skipped',
            'is_optional' => 'boolean',
            'notes' => 'nullable|string|max:1000',
        ];
    }
}
