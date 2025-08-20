<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class BookingNotification extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'type',
        'channel',
        'recipient',
        'status',
        'scheduled_at',
        'sent_at',
        'content',
        'metadata',
        'retry_count',
        'error_message',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'metadata' => 'array',
        'retry_count' => 'integer',
    ];

    // Relationships
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    // Scopes
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeSent(Builder $query): Builder
    {
        return $query->where('status', 'sent');
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', 'failed');
    }

    public function scopeDelivered(Builder $query): Builder
    {
        return $query->where('status', 'delivered');
    }

    public function scopeDue(Builder $query): Builder
    {
        return $query->where('scheduled_at', '<=', now());
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->pending()
            ->where('scheduled_at', '<', now()->subMinutes(5));
    }

    public function scopeForType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeForChannel(Builder $query, string $channel): Builder
    {
        return $query->where('channel', $channel);
    }

    public function scopeForBooking(Builder $query, int $bookingId): Builder
    {
        return $query->where('booking_id', $bookingId);
    }

    public function scopeRetryable(Builder $query): Builder
    {
        return $query->failed()
            ->where('retry_count', '<', 3)
            ->where('updated_at', '<', now()->subMinutes(30));
    }

    // Accessors & Mutators
    public function getTypeDisplayAttribute(): string
    {
        return match ($this->type) {
            'booking_created' => 'Booking Created',
            'booking_confirmed' => 'Booking Confirmed',
            'booking_cancelled' => 'Booking Cancelled',
            'booking_rescheduled' => 'Booking Rescheduled',
            'consultation_reminder' => 'Consultation Reminder',
            'booking_reminder' => 'Booking Reminder',
            'payment_reminder' => 'Payment Reminder',
            'follow_up' => 'Follow Up',
            default => ucfirst(str_replace('_', ' ', $this->type))
        };
    }

    public function getChannelDisplayAttribute(): string
    {
        return match ($this->channel) {
            'email' => 'Email',
            'sms' => 'SMS',
            'push' => 'Push Notification',
            'in_app' => 'In-App Notification',
            default => ucfirst($this->channel)
        };
    }

    public function getStatusDisplayAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'Pending',
            'sent' => 'Sent',
            'failed' => 'Failed',
            'delivered' => 'Delivered',
            default => ucfirst($this->status)
        };
    }

    public function getFormattedScheduledAtAttribute(): string
    {
        return $this->scheduled_at->format('M j, Y g:i A');
    }

    public function getFormattedSentAtAttribute(): ?string
    {
        return $this->sent_at?->format('M j, Y g:i A');
    }

    public function getTimeSinceScheduledAttribute(): string
    {
        return $this->scheduled_at->diffForHumans();
    }

    public function getTimeSinceSentAttribute(): ?string
    {
        return $this->sent_at?->diffForHumans();
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->status === 'pending' && $this->scheduled_at->isPast();
    }

    public function getCanRetryAttribute(): bool
    {
        return $this->status === 'failed' &&
            $this->retry_count < 3 &&
            $this->updated_at->lt(now()->subMinutes(30));
    }

    // Helper methods
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isSent(): bool
    {
        return $this->status === 'sent';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isDelivered(): bool
    {
        return $this->status === 'delivered';
    }

    public function isDue(): bool
    {
        return $this->scheduled_at->lte(now());
    }

    public function isOverdue(): bool
    {
        return $this->is_overdue;
    }

    public function canRetry(): bool
    {
        return $this->can_retry;
    }

    public function markAsSent(): void
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    public function markAsDelivered(): void
    {
        $this->update([
            'status' => 'delivered',
            'sent_at' => $this->sent_at ?: now(),
        ]);
    }

    public function markAsFailed(string $errorMessage = null): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
            'retry_count' => $this->retry_count + 1,
        ]);
    }

    public function reschedule(Carbon $newScheduledAt): void
    {
        $this->update([
            'scheduled_at' => $newScheduledAt,
            'status' => 'pending',
            'sent_at' => null,
            'error_message' => null,
        ]);
    }

    public function retry(): void
    {
        if (!$this->canRetry()) {
            return;
        }

        $this->update([
            'status' => 'pending',
            'error_message' => null,
        ]);
    }

    public function cancel(): void
    {
        if ($this->isPending()) {
            $this->delete();
        }
    }

    public function getTemplateData(): array
    {
        $booking = $this->booking;

        return [
            'booking' => [
                'reference' => $booking->booking_reference,
                'service_name' => $booking->service->name,
                'client_name' => $booking->client_name,
                'scheduled_at' => $booking->scheduled_at->format('M j, Y g:i A'),
                'duration' => $booking->duration_minutes . ' minutes',
                'total_amount' => '£' . number_format($booking->total_amount / 100, 2),
                'status' => ucfirst($booking->status),
                'location' => $booking->serviceLocation?->name,
                'notes' => $booking->notes,
            ],
            'service' => [
                'name' => $booking->service->name,
                'description' => $booking->service->description,
            ],
            'customer' => [
                'name' => $booking->client_name,
                'email' => $booking->client_email,
                'phone' => $booking->client_phone,
            ],
            'metadata' => $this->metadata ?? [],
        ];
    }

    // Static helper methods
    public static function createBookingNotification(
        int $bookingId,
        string $type,
        string $channel,
        string $recipient,
        Carbon $scheduledAt,
        ?string $content = null,
        ?array $metadata = null
    ): self {
        return self::create([
            'booking_id' => $bookingId,
            'type' => $type,
            'channel' => $channel,
            'recipient' => $recipient,
            'scheduled_at' => $scheduledAt,
            'status' => 'pending',
            'content' => $content,
            'metadata' => $metadata,
            'retry_count' => 0,
        ]);
    }

    public static function scheduleBookingReminder(
        int $bookingId,
        int $minutesBefore = 60
    ): self {
        $booking = Booking::findOrFail($bookingId);
        $reminderTime = $booking->scheduled_at->clone()->subMinutes($minutesBefore);

        return self::createBookingNotification(
            $bookingId,
            'booking_reminder',
            'email',
            $booking->client_email,
            $reminderTime,
            null,
            ['minutes_before' => $minutesBefore]
        );
    }

    public static function scheduleConsultationReminder(
        int $bookingId,
        int $minutesBefore = 24 * 60 // 24 hours
    ): self {
        $booking = Booking::findOrFail($bookingId);

        if (!$booking->requires_consultation) {
            throw new \InvalidArgumentException('Booking does not require consultation');
        }

        $reminderTime = $booking->scheduled_at->clone()->subMinutes($minutesBefore);

        return self::createBookingNotification(
            $bookingId,
            'consultation_reminder',
            'email',
            $booking->client_email,
            $reminderTime,
            null,
            ['minutes_before' => $minutesBefore]
        );
    }

    public static function schedulePaymentReminder(
        int $bookingId,
        int $hoursAfter = 24
    ): self {
        $booking = Booking::findOrFail($bookingId);
        $reminderTime = $booking->created_at->clone()->addHours($hoursAfter);

        return self::createBookingNotification(
            $bookingId,
            'payment_reminder',
            'email',
            $booking->client_email,
            $reminderTime,
            null,
            ['hours_after_booking' => $hoursAfter]
        );
    }

    public static function scheduleFollowUp(
        int $bookingId,
        int $hoursAfter = 24
    ): self {
        $booking = Booking::findOrFail($bookingId);
        $followUpTime = $booking->ends_at->clone()->addHours($hoursAfter);

        return self::createBookingNotification(
            $bookingId,
            'follow_up',
            'email',
            $booking->client_email,
            $followUpTime,
            null,
            ['hours_after_service' => $hoursAfter]
        );
    }

    public static function createImmediateNotification(
        int $bookingId,
        string $type,
        string $channel,
        string $recipient,
        ?string $content = null,
        ?array $metadata = null
    ): self {
        return self::createBookingNotification(
            $bookingId,
            $type,
            $channel,
            $recipient,
            now(),
            $content,
            $metadata
        );
    }

    public static function getNotificationTypesForBooking(Booking $booking): array
    {
        $types = [
            'booking_created' => 'Immediate confirmation',
            'booking_confirmed' => 'When admin confirms',
            'booking_reminder' => '1 hour before service',
        ];

        if ($booking->requires_consultation) {
            $types['consultation_reminder'] = '24 hours before service';
        }

        if ($booking->payment_status === 'pending') {
            $types['payment_reminder'] = '24 hours after booking';
        }

        $types['follow_up'] = '24 hours after service';

        return $types;
    }

    public static function getDefaultSchedule(): array
    {
        return [
            'booking_created' => ['immediate' => true],
            'booking_confirmed' => ['immediate' => true],
            'booking_cancelled' => ['immediate' => true],
            'booking_rescheduled' => ['immediate' => true],
            'consultation_reminder' => ['hours_before' => 24],
            'booking_reminder' => ['minutes_before' => 60],
            'payment_reminder' => ['hours_after_booking' => 24],
            'follow_up' => ['hours_after_service' => 24],
        ];
    }

    public static function cancelAllForBooking(int $bookingId): int
    {
        return self::where('booking_id', $bookingId)
            ->pending()
            ->delete();
    }

    public static function getOverdueNotifications(): \Illuminate\Database\Eloquent\Collection
    {
        return self::overdue()
            ->with('booking')
            ->orderBy('scheduled_at')
            ->get();
    }

    public static function getFailedNotifications(): \Illuminate\Database\Eloquent\Collection
    {
        return self::retryable()
            ->with('booking')
            ->orderBy('updated_at')
            ->get();
    }

    public static function cleanupOldNotifications(int $daysOld = 30): int
    {
        return self::whereIn('status', ['sent', 'delivered', 'failed'])
            ->where('created_at', '<', now()->subDays($daysOld))
            ->delete();
    }

    public static function getNotificationStats(Carbon $startDate = null, Carbon $endDate = null): array
    {
        $query = self::query();

        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }

        $total = $query->count();
        $pending = (clone $query)->pending()->count();
        $sent = (clone $query)->sent()->count();
        $failed = (clone $query)->failed()->count();
        $delivered = (clone $query)->delivered()->count();

        return [
            'total' => $total,
            'pending' => $pending,
            'sent' => $sent,
            'failed' => $failed,
            'delivered' => $delivered,
            'success_rate' => $total > 0 ? round((($sent + $delivered) / $total) * 100, 1) : 0,
            'failure_rate' => $total > 0 ? round(($failed / $total) * 100, 1) : 0,
        ];
    }

    // Validation rules
    public static function getValidationRules(): array
    {
        return [
            'booking_id' => 'required|exists:bookings,id',
            'type' => 'required|in:booking_created,booking_confirmed,booking_cancelled,booking_rescheduled,consultation_reminder,booking_reminder,payment_reminder,follow_up',
            'channel' => 'required|in:email,sms,push,in_app',
            'recipient' => 'required|string|max:255',
            'scheduled_at' => 'required|date|after_or_equal:now',
            'content' => 'nullable|string',
            'metadata' => 'nullable|array',
        ];
    }

    // Boot method for model events
    protected static function booted(): void
    {
        // Automatically validate recipient format based on channel
        static::saving(function (self $notification) {
            if ($notification->channel === 'email' && !filter_var($notification->recipient, FILTER_VALIDATE_EMAIL)) {
                throw new \InvalidArgumentException('Invalid email address for email notification');
            }

            if ($notification->channel === 'sms' && !preg_match('/^\+?[1-9]\d{1,14}$/', $notification->recipient)) {
                throw new \InvalidArgumentException('Invalid phone number for SMS notification');
            }
        });
    }
}
