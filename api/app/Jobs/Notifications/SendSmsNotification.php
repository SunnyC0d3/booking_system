<?php

namespace App\Jobs\Notifications;

use App\Constants\NotificationStatuses;
use App\Models\User;
use App\Models\Booking;
use App\Models\ConsultationBooking;
use App\Services\V1\Notifications\SmsNotificationService;
use App\Services\V1\Notifications\NotificationTemplateService;
use Carbon\Carbon;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendSmsNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $phoneNumber;
    public string $message;
    public string $notificationType;
    public array $metadata;
    public array $options;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 30;

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public function backoff(): array
    {
        return [30, 120, 300]; // 30 seconds, 2 minutes, 5 minutes
    }

    /**
     * Determine the time at which the job should timeout.
     */
    public function retryUntil(): Carbon
    {
        return now()->addHours(1); // Stop retrying after 1 hour
    }

    /**
     * Create a new job instance.
     */
    public function __construct(
        string $phoneNumber,
        string $message,
        string $notificationType,
        array $metadata = [],
        array $options = []
    ) {
        $this->phoneNumber = $phoneNumber;
        $this->message = $message;
        $this->notificationType = $notificationType;
        $this->metadata = $metadata;
        $this->options = $options;

        // Set queue based on urgency
        $this->onQueue($this->determineQueueName());
    }

    /**
     * Execute the job.
     */
    public function handle(SmsNotificationService $smsService): void
    {
        try {
            // Validate that SMS is enabled
            if (!$this->isSmsEnabled()) {
                $this->markAsSkipped('SMS notifications are disabled');
                return;
            }

            // Validate phone number
            if (!$this->isValidPhoneNumber()) {
                $this->markAsFailed('Invalid phone number format');
                return;
            }

            // Check rate limiting
            if (!$this->checkRateLimit()) {
                $this->markAsSkipped('Rate limit exceeded');
                return;
            }

            // Check user preferences if user is specified
            if (!$this->checkUserPreferences()) {
                $this->markAsSkipped('User has opted out of SMS notifications');
                return;
            }

            // Send the SMS
            $success = $this->sendSmsMessage($smsService);

            if ($success) {
                $this->markAsSuccessful();
                Log::info('SMS notification sent successfully', [
                    'phone' => $this->maskPhoneNumber($this->phoneNumber),
                    'type' => $this->notificationType,
                    'message_length' => strlen($this->message),
                    'metadata' => $this->metadata,
                    'attempt' => $this->attempts(),
                ]);
            } else {
                throw new Exception('SMS service returned false');
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
        Log::error('SMS notification job failed permanently', [
            'phone' => $this->maskPhoneNumber($this->phoneNumber),
            'type' => $this->notificationType,
            'message_length' => strlen($this->message),
            'metadata' => $this->metadata,
            'attempts' => $this->attempts(),
            'error' => $exception->getMessage(),
        ]);

        // Update related records if needed
        $this->updateRelatedRecords(NotificationStatuses::SMS_FAILED, $exception->getMessage());

        // If this is an urgent notification, escalate
        if ($this->isUrgentNotification()) {
            $this->escalateFailedUrgentSms($exception);
        }
    }

    /**
     * Send SMS using the service
     */
    private function sendSmsMessage(SmsNotificationService $smsService): bool
    {
        try {
            // Update status to sending
            $this->updateRelatedRecords(NotificationStatuses::SENDING);

            // Send based on notification type
            switch ($this->notificationType) {
                case 'booking_confirmation':
                    return $this->sendBookingConfirmationSms($smsService);

                case 'booking_reminder':
                    return $this->sendBookingReminderSms($smsService);

                case 'booking_cancelled':
                    return $this->sendBookingCancellationSms($smsService);

                case 'booking_rescheduled':
                    return $this->sendBookingRescheduledSms($smsService);

                case 'payment_reminder':
                    return $this->sendPaymentReminderSms($smsService);

                case 'consultation_reminder':
                    return $this->sendConsultationReminderSms($smsService);

                case 'consultation_starting_soon':
                    return $this->sendConsultationStartingSoonSms($smsService);

                case 'custom':
                    return $this->sendCustomSms($smsService);

                default:
                    Log::warning('Unknown SMS notification type', [
                        'type' => $this->notificationType,
                        'metadata' => $this->metadata,
                    ]);
                    return false;
            }

        } catch (Exception $e) {
            Log::error('Failed to send SMS message', [
                'type' => $this->notificationType,
                'error' => $e->getMessage(),
                'metadata' => $this->metadata,
            ]);
            return false;
        }
    }

    /**
     * Send booking confirmation SMS
     */
    private function sendBookingConfirmationSms(SmsNotificationService $smsService): bool
    {
        $booking = $this->getBookingFromMetadata();
        if (!$booking) {
            return false;
        }

        return $smsService->sendBookingConfirmation($booking, $this->metadata['variables'] ?? []);
    }

    /**
     * Send booking reminder SMS
     */
    private function sendBookingReminderSms(SmsNotificationService $smsService): bool
    {
        $booking = $this->getBookingFromMetadata();
        if (!$booking) {
            return false;
        }

        return $smsService->sendBookingReminder($booking, $this->metadata['variables'] ?? []);
    }

    /**
     * Send booking cancellation SMS
     */
    private function sendBookingCancellationSms(SmsNotificationService $smsService): bool
    {
        $booking = $this->getBookingFromMetadata();
        if (!$booking) {
            return false;
        }

        return $smsService->sendBookingCancellation($booking, $this->metadata['variables'] ?? []);
    }

    /**
     * Send booking rescheduled SMS
     */
    private function sendBookingRescheduledSms(SmsNotificationService $smsService): bool
    {
        $booking = $this->getBookingFromMetadata();
        if (!$booking) {
            return false;
        }

        return $smsService->sendBookingRescheduled($booking, $this->metadata['variables'] ?? []);
    }

    /**
     * Send payment reminder SMS
     */
    private function sendPaymentReminderSms(SmsNotificationService $smsService): bool
    {
        $booking = $this->getBookingFromMetadata();
        if (!$booking) {
            return false;
        }

        return $smsService->sendPaymentReminder($booking, $this->metadata['variables'] ?? []);
    }

    /**
     * Send consultation reminder SMS
     */
    private function sendConsultationReminderSms(SmsNotificationService $smsService): bool
    {
        $consultation = $this->getConsultationFromMetadata();
        if (!$consultation) {
            return false;
        }

        return $smsService->sendConsultationReminder($consultation, $this->metadata['variables'] ?? []);
    }

    /**
     * Send consultation starting soon SMS
     */
    private function sendConsultationStartingSoonSms(SmsNotificationService $smsService): bool
    {
        $consultation = $this->getConsultationFromMetadata();
        if (!$consultation) {
            return false;
        }

        return $smsService->sendConsultationStartingSoon($consultation, $this->metadata['variables'] ?? []);
    }

    /**
     * Send custom SMS message
     */
    private function sendCustomSms(SmsNotificationService $smsService): bool
    {
        // For custom messages, send the message directly
        return $smsService->sendRawSms($this->phoneNumber, $this->message, $this->metadata);
    }

    /**
     * Get booking from metadata
     */
    private function getBookingFromMetadata(): ?Booking
    {
        $bookingId = $this->metadata['booking_id'] ?? null;
        if (!$bookingId) {
            Log::error('No booking ID provided in SMS job metadata', [
                'type' => $this->notificationType,
                'metadata' => $this->metadata,
            ]);
            return null;
        }

        $booking = Booking::find($bookingId);
        if (!$booking) {
            Log::error('Booking not found for SMS notification', [
                'booking_id' => $bookingId,
                'type' => $this->notificationType,
            ]);
        }

        return $booking;
    }

    /**
     * Get consultation from metadata
     */
    private function getConsultationFromMetadata(): ?ConsultationBooking
    {
        $consultationId = $this->metadata['consultation_id'] ?? null;
        if (!$consultationId) {
            Log::error('No consultation ID provided in SMS job metadata', [
                'type' => $this->notificationType,
                'metadata' => $this->metadata,
            ]);
            return null;
        }

        $consultation = ConsultationBooking::find($consultationId);
        if (!$consultation) {
            Log::error('Consultation not found for SMS notification', [
                'consultation_id' => $consultationId,
                'type' => $this->notificationType,
            ]);
        }

        return $consultation;
    }

    /**
     * Check if SMS is enabled
     */
    private function isSmsEnabled(): bool
    {
        return config('notifications.channels.sms.enabled', false);
    }

    /**
     * Validate phone number format
     */
    private function isValidPhoneNumber(): bool
    {
        // Basic validation for international format
        return preg_match('/^\+[1-9]\d{10,14}$/', $this->phoneNumber);
    }

    /**
     * Check rate limiting
     */
    private function checkRateLimit(): bool
    {
        if (!config('notifications.rate_limiting.enabled', true)) {
            return true;
        }

        $userId = $this->metadata['user_id'] ?? null;
        if (!$userId) {
            return true; // Can't rate limit without user ID
        }

        // Check SMS-specific rate limits
        $smsLimits = config('notifications.rate_limiting.limits.sms', []);

        // For now, return true - implement actual rate limiting logic here
        return true;
    }

    /**
     * Check user SMS preferences
     */
    private function checkUserPreferences(): bool
    {
        $userId = $this->metadata['user_id'] ?? null;
        if (!$userId) {
            return true; // No user specified, allow sending
        }

        $user = User::find($userId);
        if (!$user) {
            return false;
        }

        // Check if user has opted out of SMS
        $preferences = $user->notification_preferences ?? [];
        $smsPreferences = $preferences['sms'] ?? true;

        if (!$smsPreferences) {
            return false;
        }

        // Check specific notification type preferences
        $notificationTypeKey = str_replace('_', '', $this->notificationType);
        $typePreferences = $preferences[$notificationTypeKey] ?? ['mail', 'database'];

        return in_array('sms', $typePreferences);
    }

    /**
     * Determine appropriate queue name
     */
    private function determineQueueName(): string
    {
        if ($this->isUrgentNotification()) {
            return config('notifications.queues.urgent', 'sms-urgent');
        }

        if ($this->isHighPriorityNotification()) {
            return config('notifications.queues.high', 'sms-high');
        }

        return config('notifications.channels.sms.queue', 'sms');
    }

    /**
     * Check if this is an urgent notification
     */
    private function isUrgentNotification(): bool
    {
        return in_array($this->notificationType, [
                'consultation_starting_soon',
                'emergency_notification',
            ]) || ($this->options['urgent'] ?? false);
    }

    /**
     * Check if this is a high priority notification
     */
    private function isHighPriorityNotification(): bool
    {
        return in_array($this->notificationType, [
            'booking_confirmation',
            'booking_cancelled',
            'booking_rescheduled',
        ]);
    }

    /**
     * Mask phone number for logging
     */
    private function maskPhoneNumber(string $phone): string
    {
        if (strlen($phone) <= 4) {
            return str_repeat('*', strlen($phone));
        }

        return substr($phone, 0, -4) . '****';
    }

    /**
     * Update related notification records
     */
    private function updateRelatedRecords(string $status, ?string $failureReason = null): void
    {
        // Update notification record if ID is provided
        $notificationId = $this->metadata['notification_id'] ?? null;
        if ($notificationId) {
            try {
                $updateData = [
                    'status' => $status,
                    'attempts' => $this->attempts(),
                    'last_attempted_at' => now(),
                ];

                if ($status === NotificationStatuses::SMS_SENT) {
                    $updateData['sent_at'] = now();
                } elseif (in_array($status, [NotificationStatuses::SMS_FAILED, NotificationStatuses::FAILED])) {
                    $updateData['failure_reason'] = $failureReason;
                }

                // Update the notification record
                // This would typically update a BookingNotification or similar model
                Log::info('Updated notification record status', [
                    'notification_id' => $notificationId,
                    'status' => $status,
                ]);

            } catch (Exception $e) {
                Log::error('Failed to update notification record', [
                    'notification_id' => $notificationId,
                    'status' => $status,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Mark notification as successful
     */
    private function markAsSuccessful(): void
    {
        $this->updateRelatedRecords(NotificationStatuses::SMS_SENT);
    }

    /**
     * Mark notification as failed
     */
    private function markAsFailed(string $reason): void
    {
        $this->updateRelatedRecords(NotificationStatuses::SMS_FAILED, $reason);

        Log::warning('SMS notification marked as failed', [
            'phone' => $this->maskPhoneNumber($this->phoneNumber),
            'type' => $this->notificationType,
            'reason' => $reason,
            'metadata' => $this->metadata,
        ]);
    }

    /**
     * Mark notification as skipped
     */
    private function markAsSkipped(string $reason): void
    {
        $this->updateRelatedRecords(NotificationStatuses::SKIPPED, $reason);

        Log::info('SMS notification skipped', [
            'phone' => $this->maskPhoneNumber($this->phoneNumber),
            'type' => $this->notificationType,
            'reason' => $reason,
            'metadata' => $this->metadata,
        ]);
    }

    /**
     * Handle job failure during execution
     */
    private function handleFailure(Exception $exception): void
    {
        Log::warning('SMS notification job attempt failed', [
            'phone' => $this->maskPhoneNumber($this->phoneNumber),
            'type' => $this->notificationType,
            'attempt' => $this->attempts(),
            'max_attempts' => $this->tries,
            'error' => $exception->getMessage(),
        ]);

        // Update status for retry if not final attempt
        if ($this->attempts() < $this->tries) {
            $this->updateRelatedRecords(
                NotificationStatuses::PENDING,
                "Attempt {$this->attempts()} failed: {$exception->getMessage()}"
            );
        }
    }

    /**
     * Escalate failed urgent SMS to manual intervention
     */
    private function escalateFailedUrgentSms(Exception $exception): void
    {
        try {
            Log::critical('Urgent SMS notification failed - manual intervention required', [
                'phone' => $this->maskPhoneNumber($this->phoneNumber),
                'type' => $this->notificationType,
                'message' => substr($this->message, 0, 100) . '...',
                'metadata' => $this->metadata,
                'error' => $exception->getMessage(),
            ]);

            // TODO: Implement admin notification system
            // This could trigger an admin email, Slack notification, etc.

        } catch (Exception $e) {
            Log::error('Failed to escalate urgent SMS failure', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get unique identifier for this job type
     */
    public function uniqueId(): string
    {
        return "sms_{$this->notificationType}_" . md5($this->phoneNumber . $this->message);
    }

    /**
     * Determine if job should be unique
     */
    public function uniqueFor(): int
    {
        return 60; // 1 minute - prevent duplicate SMS to same number
    }

    /**
     * Get job display name for monitoring
     */
    public function displayName(): string
    {
        return "Send SMS Notification ({$this->notificationType}, {$this->maskPhoneNumber($this->phoneNumber)})";
    }

    /**
     * Get job tags for monitoring and filtering
     */
    public function tags(): array
    {
        return [
            'sms_notification',
            "type:{$this->notificationType}",
            "user:" . ($this->metadata['user_id'] ?? 'unknown'),
            "booking:" . ($this->metadata['booking_id'] ?? 'none'),
        ];
    }

    /**
     * Determine if the job should be retried after a failure
     */
    public function shouldRetry(Exception $exception): bool
    {
        // Don't retry certain types of failures
        $nonRetryableErrors = [
            'Invalid phone number',
            'User has opted out',
            'Phone number blocked',
            'Message too long',
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
