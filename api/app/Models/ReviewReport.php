<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;

class ReviewReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'review_id',
        'reported_by',
        'reason',
        'details',
        'status',
        'reviewed_by',
        'reviewed_at',
        'admin_notes',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeResolved(Builder $query): Builder
    {
        return $query->where('status', 'resolved');
    }

    public function scopeDismissed(Builder $query): Builder
    {
        return $query->where('status', 'dismissed');
    }

    public function markAsReviewed(User $admin, string $status, ?string $notes = null): void
    {
        $this->update([
            'status' => $status,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
            'admin_notes' => $notes,
        ]);
    }

    public function getReasonLabelAttribute(): string
    {
        return match($this->reason) {
            'spam' => 'Spam',
            'inappropriate_language' => 'Inappropriate Language',
            'fake_review' => 'Fake Review',
            'off_topic' => 'Off Topic',
            'personal_information' => 'Personal Information',
            'other' => 'Other',
            default => 'Unknown',
        };
    }
}
