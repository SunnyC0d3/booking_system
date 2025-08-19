<?php

namespace App\Models;

use App\Models\Booking;
use App\Models\ConsultationBooking;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsultationOutcome extends Model
{
    use HasFactory;

    protected $fillable = [
        'consultation_booking_id',
        'outcome_type',
        'outcome_description',
        'service_requirements',
        'timeline_requirements',
        'budget_information',
        'logistical_details',
        'action_items',
        'follow_up_by_date',
        'priority',
        'quote_requested',
        'quote_due_date',
        'estimated_quote_amount',
    ];

    protected $casts = [
        'service_requirements' => 'array',
        'timeline_requirements' => 'array',
        'budget_information' => 'array',
        'logistical_details' => 'array',
        'action_items' => 'array',
        'follow_up_by_date' => 'datetime',
        'quote_requested' => 'boolean',
        'quote_due_date' => 'datetime',
        'estimated_quote_amount' => 'integer',
    ];

    // Relationships
    public function consultationBooking(): BelongsTo
    {
        return $this->belongsTo(ConsultationBooking::class);
    }

    // Scopes
    public function scopeNeedsFollowUp(Builder $query): Builder
    {
        return $query->whereNotNull('follow_up_by_date')
            ->where('follow_up_by_date', '>=', now());
    }

    public function scopeOverdueFollowUp(Builder $query): Builder
    {
        return $query->whereNotNull('follow_up_by_date')
            ->where('follow_up_by_date', '<', now());
    }

    public function scopeQuoteRequested(Builder $query): Builder
    {
        return $query->where('quote_requested', true);
    }

    public function scopeByOutcome(Builder $query, string $outcome): Builder
    {
        return $query->where('outcome_type', $outcome);
    }

    public function scopeByPriority(Builder $query, string $priority): Builder
    {
        return $query->where('priority', $priority);
    }

    // Helper methods
    public function needsFollowUp(): bool
    {
        return $this->follow_up_by_date && $this->follow_up_by_date->isFuture();
    }

    public function isOverdueForFollowUp(): bool
    {
        return $this->follow_up_by_date && $this->follow_up_by_date->isPast();
    }

    public function hasQuoteRequest(): bool
    {
        return $this->quote_requested;
    }

    public function isQuoteOverdue(): bool
    {
        return $this->quote_requested &&
            $this->quote_due_date &&
            $this->quote_due_date->isPast();
    }

    public function getOutcomeDisplayAttribute(): string
    {
        return match($this->outcome_type) {
            'booking_confirmed' => 'Booking Confirmed',
            'quote_requested' => 'Quote Requested',
            'follow_up_needed' => 'Follow-up Needed',
            'not_interested' => 'Not Interested',
            'needs_more_info' => 'Needs More Information',
            default => ucfirst(str_replace('_', ' ', $this->outcome_type))
        };
    }

    public function getPriorityDisplayAttribute(): string
    {
        return match($this->priority) {
            'low' => 'Low Priority',
            'medium' => 'Medium Priority',
            'high' => 'High Priority',
            'urgent' => 'Urgent',
            default => ucfirst($this->priority)
        };
    }

    public function getPriorityColorAttribute(): string
    {
        return match($this->priority) {
            'low' => 'green',
            'medium' => 'blue',
            'high' => 'orange',
            'urgent' => 'red',
            default => 'gray'
        };
    }

    public function getFormattedEstimatedQuoteAttribute(): ?string
    {
        if (!$this->estimated_quote_amount) {
            return null;
        }
        return '£' . number_format($this->estimated_quote_amount / 100, 2);
    }

    public function hasServiceRequirements(): bool
    {
        return !empty($this->service_requirements);
    }

    public function hasBudgetInformation(): bool
    {
        return !empty($this->budget_information);
    }

    public function hasTimelineRequirements(): bool
    {
        return !empty($this->timeline_requirements);
    }

    public function hasLogisticalDetails(): bool
    {
        return !empty($this->logistical_details);
    }

    public function getActionItemsCount(): int
    {
        return count($this->action_items ?? []);
    }

    public function createMainBooking(): ?Booking
    {
        if ($this->outcome_type !== 'booking_confirmed') {
            return null;
        }

        $consultation = $this->consultationBooking;
        if (!$consultation) {
            return null;
        }

        return $consultation->createMainBooking([
            'notes' => $this->outcome_description,
            'special_requirements' => $this->getFormattedRequirements(),
            'venue_requirements' => $this->logistical_details,
        ]);
    }

    private function getFormattedRequirements(): ?string
    {
        $requirements = [];

        if ($this->hasServiceRequirements()) {
            $requirements[] = 'Service: ' . implode(', ', array_values($this->service_requirements));
        }

        if ($this->hasTimelineRequirements()) {
            $requirements[] = 'Timeline: ' . implode(', ', array_values($this->timeline_requirements));
        }

        if ($this->hasBudgetInformation()) {
            $requirements[] = 'Budget: ' . implode(', ', array_values($this->budget_information));
        }

        return empty($requirements) ? null : implode("\n", $requirements);
    }
}
