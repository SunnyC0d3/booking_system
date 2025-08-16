<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class ConsultationBooking extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'service_id',
        'main_booking_id',
        'consultation_reference',
        'scheduled_at',
        'ends_at',
        'duration_minutes',
        'status',
        'type',
        'format',
        'client_name',
        'client_email',
        'client_phone',
        'consultation_notes',
        'preparation_instructions',
        'consultation_questions',
        'meeting_link',
        'meeting_location',
        'meeting_instructions',
        'started_at',
        'completed_at',
        'completion_notes',
        'outcome_summary',
        'requires_follow_up',
        'follow_up_scheduled_at',
        'follow_up_notes',
        'consultation_fee',
        'fee_waived_if_booking',
        'payment_status',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'ends_at' => 'datetime',
        'duration_minutes' => 'integer',
        'consultation_questions' => 'array',
        'meeting_instructions' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'outcome_summary' => 'array',
        'requires_follow_up' => 'boolean',
        'follow_up_scheduled_at' => 'datetime',
        'consultation_fee' => 'integer',
        'fee_waived_if_booking' => 'boolean',
    ];

    protected static function booted()
    {
        static::creating(function ($consultation) {
            if (empty($consultation->consultation_reference)) {
                $consultation->consultation_reference = self::generateConsultationReference();
            }
        });
    }

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function mainBooking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'main_booking_id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(ConsultationNote::class);
    }

    public function outcome(): HasOne
    {
        return $this->hasOne(ConsultationOutcome::class);
    }

    // Scopes
    public function scopeScheduled(Builder $query): Builder
    {
        return $query->where('status', 'scheduled');
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    public function scopeCancelled(Builder $query): Builder
    {
        return $query->where('status', 'cancelled');
    }

    public function scopeInProgress(Builder $query): Builder
    {
        return $query->where('status', 'in_progress');
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('scheduled_at', '>', now())
            ->whereIn('status', ['scheduled']);
    }

    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('scheduled_at', today());
    }

    public function scopeByFormat(Builder $query, string $format): Builder
    {
        return $query->where('format', $format);
    }

    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeRequiringFollowUp(Builder $query): Builder
    {
        return $query->where('requires_follow_up', true)
            ->whereNull('follow_up_scheduled_at');
    }

    public function scopeFreeConsultations(Builder $query): Builder
    {
        return $query->where('consultation_fee', 0)
            ->orWhere('payment_status', 'free');
    }

    public function scopePaidConsultations(Builder $query): Builder
    {
        return $query->where('consultation_fee', '>', 0)
            ->where('payment_status', '!=', 'free');
    }

    // Accessors & Mutators
    public function getFormattedConsultationFeeAttribute(): string
    {
        return '£' . number_format($this->consultation_fee / 100, 2);
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
        return match($this->status) {
            'scheduled' => 'Scheduled',
            'in_progress' => 'In Progress',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            'no_show' => 'No Show',
            default => ucfirst($this->status)
        };
    }

    public function getTypeDisplayAttribute(): string
    {
        return match($this->type) {
            'pre_booking' => 'Pre-Booking Consultation',
            'design' => 'Design Consultation',
            'planning' => 'Planning Session',
            'technical' => 'Technical Consultation',
            'follow_up' => 'Follow-Up Meeting',
            default => ucfirst(str_replace('_', ' ', $this->type))
        };
    }

    public function getFormatDisplayAttribute(): string
    {
        return match($this->format) {
            'phone' => 'Phone Call',
            'video' => 'Video Call',
            'in_person' => 'In-Person Meeting',
            'site_visit' => 'Site Visit',
            default => ucfirst(str_replace('_', ' ', $this->format))
        };
    }

    public function getPaymentStatusDisplayAttribute(): string
    {
        return match($this->payment_status) {
            'free' => 'Free Consultation',
            'unpaid' => 'Payment Required',
            'paid' => 'Paid',
            'refunded' => 'Refunded',
            'waived' => 'Fee Waived',
            default => ucfirst($this->payment_status)
        };
    }

    // Helper Methods
    public function isScheduled(): bool
    {
        return $this->status === 'scheduled';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }

    public function isUpcoming(): bool
    {
        return $this->scheduled_at->isFuture() && $this->isScheduled();
    }

    public function isOverdue(): bool
    {
        return $this->scheduled_at->isPast() && $this->isScheduled();
    }

    public function canStart(): bool
    {
        return $this->isScheduled() &&
            $this->scheduled_at->subMinutes(15)->isPast(); // Can start 15 mins early
    }

    public function canComplete(): bool
    {
        return $this->isInProgress() || $this->isScheduled();
    }

    public function canCancel(): bool
    {
        return in_array($this->status, ['scheduled', 'in_progress']);
    }

    public function canReschedule(): bool
    {
        return $this->isScheduled();
    }

    public function isFreeConsultation(): bool
    {
        return $this->consultation_fee === 0 || $this->payment_status === 'free';
    }

    public function isVirtualMeeting(): bool
    {
        return in_array($this->format, ['phone', 'video']);
    }

    public function isInPersonMeeting(): bool
    {
        return in_array($this->format, ['in_person', 'site_visit']);
    }

    public function requiresLocation(): bool
    {
        return $this->isInPersonMeeting();
    }

    public function requiresMeetingLink(): bool
    {
        return $this->format === 'video';
    }

    public function needsFollowUp(): bool
    {
        return $this->requires_follow_up && !$this->follow_up_scheduled_at;
    }

    public function hasOutcome(): bool
    {
        return $this->outcome()->exists();
    }

    public function hasNotes(): bool
    {
        return $this->notes()->exists();
    }

    public function getDurationUntil(): ?string
    {
        if (!$this->isUpcoming()) {
            return null;
        }

        return $this->scheduled_at->diffForHumans();
    }

    public function getTimeUntilStart(): ?int
    {
        if (!$this->isUpcoming()) {
            return null;
        }

        return $this->scheduled_at->diffInMinutes(now());
    }

    public function shouldSendReminder(): bool
    {
        if (!$this->isScheduled()) {
            return false;
        }

        $minutesUntil = $this->getTimeUntilStart();

        // Send reminders at 24h, 2h, and 30min before
        return in_array($minutesUntil, [1440, 120, 30]);
    }

    public function markAsStarted(): bool
    {
        if (!$this->canStart()) {
            return false;
        }

        return $this->update([
            'status' => 'in_progress',
            'started_at' => now(),
        ]);
    }

    public function markAsCompleted(array $completionData = []): bool
    {
        if (!$this->canComplete()) {
            return false;
        }

        $updateData = array_merge([
            'status' => 'completed',
            'completed_at' => now(),
        ], $completionData);

        return $this->update($updateData);
    }

    public function markAsCancelled(string $reason = null): bool
    {
        if (!$this->canCancel()) {
            return false;
        }

        return $this->update([
            'status' => 'cancelled',
            'completion_notes' => $reason,
        ]);
    }

    public function reschedule(Carbon $newDateTime): bool
    {
        if (!$this->canReschedule()) {
            return false;
        }

        $endTime = $newDateTime->clone()->addMinutes($this->duration_minutes);

        return $this->update([
            'scheduled_at' => $newDateTime,
            'ends_at' => $endTime,
        ]);
    }

    public function scheduleFollowUp(Carbon $followUpTime, string $notes = null): bool
    {
        return $this->update([
            'requires_follow_up' => true,
            'follow_up_scheduled_at' => $followUpTime,
            'follow_up_notes' => $notes,
        ]);
    }

    public static function generateConsultationReference(): string
    {
        do {
            $reference = 'CONS' . strtoupper(substr(uniqid(), -6));
        } while (self::where('consultation_reference', $reference)->exists());

        return $reference;
    }

    public function getNextAvailableSlot(): ?Carbon
    {
        // Find the next available consultation slot for this service
        // This would integrate with the service's availability windows
        return $this->service->getNextAvailableDate();
    }

    public function createMainBooking(array $bookingData = []): ?Booking
    {
        if ($this->main_booking_id || !$this->isCompleted()) {
            return null;
        }

        $booking = Booking::create(array_merge([
            'user_id' => $this->user_id,
            'service_id' => $this->service_id,
            'consultation_booking_id' => $this->id,
            'requires_consultation' => true,
            'consultation_completed' => true,
            'consultation_completed_at' => $this->completed_at,
            'consultation_summary' => $this->completion_notes,
            'client_name' => $this->client_name,
            'client_email' => $this->client_email,
            'client_phone' => $this->client_phone,
        ], $bookingData));

        // Link the consultation to the main booking
        $this->update(['main_booking_id' => $booking->id]);

        return $booking;
    }
}
