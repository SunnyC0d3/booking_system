<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;

class ConsultationNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'consultation_booking_id',
        'created_by_user_id',
        'note_type',
        'title',
        'content',
        'structured_data',
        'is_private',
        'is_action_item',
        'action_due_date',
        'action_completed',
    ];

    protected $casts = [
        'structured_data' => 'array',
        'is_private' => 'boolean',
        'is_action_item' => 'boolean',
        'action_due_date' => 'datetime',
        'action_completed' => 'boolean',
    ];

    // Relationships
    public function consultationBooking(): BelongsTo
    {
        return $this->belongsTo(ConsultationBooking::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    // Scopes
    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_private', false);
    }

    public function scopePrivate(Builder $query): Builder
    {
        return $query->where('is_private', true);
    }

    public function scopeActionItems(Builder $query): Builder
    {
        return $query->where('is_action_item', true);
    }

    public function scopePendingActions(Builder $query): Builder
    {
        return $query->where('is_action_item', true)
            ->where('action_completed', false);
    }

    public function scopeOverdueActions(Builder $query): Builder
    {
        return $query->where('is_action_item', true)
            ->where('action_completed', false)
            ->where('action_due_date', '<', now());
    }

    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('note_type', $type);
    }

    // Helper methods
    public function isActionItem(): bool
    {
        return $this->is_action_item;
    }

    public function isCompleted(): bool
    {
        return $this->action_completed;
    }

    public function isOverdue(): bool
    {
        return $this->is_action_item &&
            !$this->action_completed &&
            $this->action_due_date &&
            $this->action_due_date->isPast();
    }

    public function markCompleted(): bool
    {
        if (!$this->is_action_item) {
            return false;
        }

        return $this->update(['action_completed' => true]);
    }

    public function getTypeDisplayAttribute(): string
    {
        return match($this->note_type) {
            'preparation' => 'Preparation',
            'discussion' => 'Discussion Point',
            'decision' => 'Decision Made',
            'action_item' => 'Action Item',
            'follow_up' => 'Follow-up',
            default => ucfirst(str_replace('_', ' ', $this->note_type))
        };
    }

    public function getFormattedContentAttribute(): string
    {
        if (strlen($this->content) > 200) {
            return substr($this->content, 0, 200) . '...';
        }
        return $this->content;
    }
}
