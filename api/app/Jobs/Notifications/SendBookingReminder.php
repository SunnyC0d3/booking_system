<?php

namespace App\Jobs\Notifications;

use App\Models\Booking;
use App\Models\BookingNotification;
use App\Services\V1\Notifications\BookingNotificationService;
use App\Constants\NotificationStatuses;
use Carbon\Carbon;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendBookingReminder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public Booking $booking;
    public int $hoursUntil;
    public string $reminderType;
    public ?int $notificationId;
    public array $options;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 60;

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public function backoff(): array
    {
        return [30, 300, 900]; // 30 seconds, 5 minutes, 15 minutes
    }

    /**
     * Determine the time at which the job should timeout.
     */
    public function retryUntil(): Carbon
    {
        return now()->addHours(2); // Stop retrying after 2 hours
    }

    /**
     * Create a new job instance.
     */
    public function __construct(
        Booking $booking,
        int $hoursUntil,
        string $reminderType = 'standard',
        ?int $notificationId = null,
        array $options = []
    ) {
        $this->booking = $booking;
        $this->hoursUntil = $hoursUntil;
        $this->reminderType = $reminderType;
        $this->notificationId = $notificationId;
        $this->options = $options;

        // Set queue based on urgency
        $this->onQueue($this->determineQueueName());
    }

    /**
     * Execute the job.
     */
    public function handle(BookingNotificationService $notificationService): void
    {
        try {
            // Load necessary relationships
            $this->booking->load(['user', 'service', 'serviceLocation']);

            // Check if booking still exists and is valid
            if (!$this->isBookingValid()) {
                $this->markNotificationAsSkipped('Booking is no longer valid for reminder');
                return;
            }

            // Check if reminder is still relevant (timing)
            if (!$this->isReminderRelevant()) {
                $this->markNotificationAsSkipped('Reminder is no longer relevant due to timing');
                return;
            }

            // Check user notification preferences
            if (!$this->shouldSendReminder()) {
                $this->markNotificationAsSkipped('User preferences prevent reminder');
                return;
            }

            // Mark notification as being processed
            $this->updateNotificationStatus(NotificationStatuses::SENDING);

            // Send the reminder
            $success = $notificationService->sendBookingReminder(
                $this->booking,
                $this->hoursUntil,
                $this->reminderType
            );

            if ($success) {
                $this->markNotificationAsSuccessful();
                Log::info('Booking reminder sent successfully', [
                    'booking_id' => $this->booking->id,
                    'booking_reference' => $this->booking->booking_reference,
                    'hours_until' => $this->hoursUntil,
                    'reminder_type' => $this->reminderType,
                    'notification_id' => $this->notificationId,
                    'attempt' => $this->attempts(),
                ]);
            } else {
                throw new Exception('Notification service returned false');
            }

        } catch (Exception $e) {
            $this->handleFailure($e);
            throw $e; // Re-throw to trigger retry mechanism
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Exception $exception): void
    {
        Log::error('Booking reminder job failed permanently', [
            'booking_id' => $this->booking->id,
            'booking_reference' => $this->booking->booking_reference,
            'hours_until' => $this->hoursUntil,
            'reminder_type' => $this->reminderType,
            'notification_id' => $this->notificationId,
            'attempts' => $this->attempts(),
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        // Mark notification as permanently failed
        $this->updateNotificationStatus(
            NotificationStatuses::FAILED,
            $exception->getMessage()
        );

        // If this is an urgent reminder, escalate to manual intervention
        if ($this->isUrgentReminder()) {
            $this->escalateFailedUrgentReminder($exception);
        }
    }

    /**
     * Check if booking is still valid for reminder
     */
    private function isBookingValid(): bool
    {
        // Check if booking is cancelled or completed
        if (in_array($this->booking->status, ['cancelled', 'completed', 'no_show'])) {
            Log::info('Skipping reminder for booking with status', [
                'booking_id' => $this->booking->id,
                'status' => $this->booking->status,
            ]);
            return false;
        }

        // Check if booking is in the past
        if ($this->booking->scheduled_at->isPast()) {
            Log::info('Skipping reminder for past booking', [
                'booking_id' => $this->booking->id,
                'scheduled_at' => $this->booking->scheduled_at->toISOString(),
            ]);
            return false;
        }

        return true;
    }

    /**
     * Check if reminder timing is still relevant
     */
    private function isReminderRelevant(): bool
    {
        $actualHoursUntil = now()->diffInHours($this->booking->scheduled_at, false);

        // If the actual time until booking is significantly different from expected,
        // the reminder might be stale (e.g., booking was rescheduled)
        $tolerance = 1; // 1 hour tolerance

        if (abs($actualHoursUntil - $this->hoursUntil) > $tolerance) {
            Log::info('Reminder timing no longer relevant', [
                'booking_id' => $this->booking->id,
                'expected_hours_until' => $this->hoursUntil,
                'actual_hours_until' => $actualHoursUntil,
                'tolerance' => $tolerance,
            ]);
            return false;
        }

        // Don't send reminders for events that are too far in the future or past
        if ($actualHoursUntil < 0 || $actualHoursUntil > 168) { // Not more than 1 week
            return false;
        }

        return true;
    }

    /**
     * Check if reminder should be sent based on user preferences
     */
    private function shouldSendReminder(): bool
    {
        $user = $this->booking->user;

        // Check if user has opted out of reminders
        $preferences = $user->notification_preferences ?? [];
        $reminderPrefs = $preferences['booking_reminders'] ?? ['mail', 'database'];

        if (empty($reminderPrefs)) {
            Log::info('User has opted out of booking reminders', [
                'booking_id' => $this->booking->id,
                'user_id' => $user->id,
            ]);
            return false;
        }

        // Check if user is active (hasn't been suspended, etc.)
        if (!$user->is_active ?? true) {
            Log::info('User is not active, skipping reminder', [
                'booking_id' => $this->booking->id,
                'user_id' => $user->id,
            ]);
            return false;
        }

        return true;
    }

    /**
     * Update notification record status
     */
    private function updateNotificationStatus(string $status, ?string $failureReason = null): void
    {
        if (!$this->notificationId) {
            return;
        }

        try {
            $updateData = [
                'status' => $status,
                'attempts' => $this->attempts(),
                'last_attempted_at' => now(),
            ];

            if ($status === NotificationStatuses::SENT) {
                $updateData['sent_at'] = now();
            } elseif ($status === NotificationStatuses::FAILED) {
                $updateData['failure_reason'] = $failureReason;
            } elseif ($status === NotificationStatuses::SKIPPED) {
                $updateData['failure_reason'] = $failureReason;
            }

            BookingNotification::where('id', $this->notificationId)->update($updateData);

        } catch (Exception $e) {
            Log::error('Failed to update notification status', [
                'notification_id' => $this->notificationId,
                'status' => $status,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Mark notification as successful
     */
    private function markNotificationAsSuccessful(): void
    {
        $this->updateNotificationStatus(NotificationStatuses::SENT);
    }

    /**
     * Mark notification as skipped
     */
    private function markNotificationAsSkipped(string $reason): void
    {
        $this->updateNotificationStatus(NotificationStatuses::SKIPPED, $reason);

        Log::info('Booking reminder skipped', [
            'booking_id' => $this->booking->id,
            'notification_id' => $this->notificationId,
            'reason' => $reason,
        ]);
    }

    /**
     * Handle job failure during execution
     */
    private function handleFailure(Exception $exception): void
    {
        Log::warning('Booking reminder job attempt failed', [
            'booking_id' => $this->booking->id,
            'notification_id' => $this->notificationId,
            'attempt' => $this->attempts(),
            'max_attempts' => $this->tries,
            'error' => $exception->getMessage(),
        ]);

        // Update notification with failure info (but not final failure)
        if ($this->attempts() < $this->tries) {
            $this->updateNotificationStatus(
                NotificationStatuses::PENDING,
                "Attempt {$this->attempts()} failed: {$exception->getMessage()}"
            );
        }
    }

    /**
     * Determine appropriate queue name based on urgency
     */
    private function determineQueueName(): string
    {
        if ($this->isUrgentReminder()) {
            return config('notifications.queues.urgent', 'notifications-urgent');
        }

        if ($this->hoursUntil <= 24) {
            return config('notifications.queues.high', 'notifications-high');
        }

        return config('notifications.queues.normal', 'notifications');
    }

    /**
     * Check if this is an urgent reminder
     */
    private function isUrgentReminder(): bool
    {
        return $this->hoursUntil <= 2 || $this->reminderType === 'urgent';
    }

    /**
     * Escalate failed urgent reminder to manual intervention
     */
    private function escalateFailedUrgentReminder(Exception $exception): void
    {
        try {
            // Could send admin notification, create support ticket, etc.
            Log::critical('Urgent booking reminder failed - manual intervention required', [
                'booking_id' => $this->booking->id,
                'booking_reference' => $this->booking->booking_reference,
                'client_name' => $this->booking->client_name,
                'client_email' => $this->booking->client_email,
                'client_phone' => $this->booking->client_phone,
                'scheduled_at' => $this->booking->scheduled_at->toISOString(),
                'hours_until' => $this->hoursUntil,
                'error' => $exception->getMessage(),
            ]);

            // TODO: Implement admin notification system
            // This could trigger an admin email, Slack notification, etc.

        } catch (Exception $e) {
            Log::error('Failed to escalate urgent reminder failure', [
                'booking_id' => $this->booking->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get unique identifier for this job type
     */
    public function uniqueId(): string
    {
        return "booking_reminder_{$this->booking->id}_{$this->hoursUntil}h";
    }

    /**
     * Determine if job should be unique
     */
    public function uniqueFor(): int
    {
        return 300; // 5 minutes - prevent duplicate reminders
    }

    /**
     * Get job display name for monitoring
     */
    public function displayName(): string
    {
        return "Send Booking Reminder (Booking: {$this->booking->booking_reference}, {$this->hoursUntil}h before)";
    }

    /**
     * Get job tags for monitoring and filtering
     */
    public function tags(): array
    {
        return [
            'booking_reminder',
            "booking:{$this->booking->id}",
            "user:{$this->booking->user_id}",
            "hours_until:{$this->hoursUntil}",
            "type:{$this->reminderType}",
        ];
    }

    /**
     * Handle job timeout
     */
    public function timeoutAt(): Carbon
    {
        return now()->addMinutes(2); // Hard timeout at 2 minutes
    }

    /**
     * Determine if the job should be retried after a failure
     */
    public function shouldRetry(Exception $exception): bool
    {
        // Don't retry certain types of failures
        $nonRetryableErrors = [
            'Booking not found',
            'User not found',
            'Invalid email address',
            'User has opted out',
        ];

        $errorMessage = $exception->getMessage();

        foreach ($nonRetryableErrors as $nonRetryableError) {
            if (str_contains($errorMessage, $nonRetryableError)) {
                return false;
            }
        }

        return true;
    }
}
