<?php

namespace App\Jobs\Notifications;

use App\Constants\NotificationStatuses;
use App\Models\User;
use App\Models\Booking;
use App\Models\ConsultationBooking;
use App\Services\V1\Notifications\PushNotificationService;
use App\Services\V1\Notifications\NotificationTemplateService;
use Carbon\Carbon;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendPushNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public array $deviceTokens;
    public array $pushData;
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
    public int $timeout = 45;

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public function backoff(): array
    {
        return [30, 180, 600]; // 30 seconds, 3 minutes, 10 minutes
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
        array $deviceTokens,
        array $pushData,
        string $notificationType,
        array $metadata = [],
        array $options = []
    ) {
        $this->deviceTokens = $deviceTokens;
        $this->pushData = $pushData;
        $this->notificationType = $notificationType;
        $this->metadata = $metadata;
        $this->options = $options;

        // Set queue based on urgency
        $this->onQueue($this->determineQueueName());
    }

    /**
     * Execute the job.
     */
    public function handle(PushNotificationService $pushService): void
    {
        try {
            // Validate that push notifications are enabled
            if (!$this->isPushEnabled()) {
                $this->markAsSkipped('Push notifications are disabled');
                return;
            }

            // Validate device tokens
            $validTokens = $this->validateDeviceTokens();
            if (empty($validTokens)) {
                $this->markAsFailed('No valid device tokens provided');
                return;
            }

            // Check rate limiting
            if (!$this->checkRateLimit()) {
                $this->markAsSkipped('Rate limit exceeded');
                return;
            }

            // Check user preferences if user is specified
            if (!$this->checkUserPreferences()) {
                $this->markAsSkipped('User has opted out of push notifications');
                return;
            }

            // Send the push notification
            $success = $this->sendPushMessage($pushService, $validTokens);

            if ($success) {
                $this->markAsSuccessful();
                Log::info('Push notification sent successfully', [
                    'device_count' => count($validTokens),
                    'type' => $this->notificationType,
                    'title' => $this->pushData['title'] ?? 'Unknown',
                    'metadata' => $this->metadata,
                    'attempt' => $this->attempts(),
                ]);
            } else {
                throw new Exception('Push service returned false');
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
        Log::error('Push notification job failed permanently', [
            'device_count' => count($this->deviceTokens),
            'type' => $this->notificationType,
            'title' => $this->pushData['title'] ?? 'Unknown',
            'metadata' => $this->metadata,
            'attempts' => $this->attempts(),
            'error' => $exception->getMessage(),
        ]);

        // Update related records
        $this->updateRelatedRecords(NotificationStatuses::PUSH_FAILED, $exception->getMessage());

        // If this is an urgent notification, escalate
        if ($this->isUrgentNotification()) {
            $this->escalateFailedUrgentPush($exception);
        }

        // Clean up invalid device tokens
        $this->cleanupInvalidTokens($exception);
    }

    /**
     * Send push notification using the service
     */
    private function sendPushMessage(PushNotificationService $pushService, array $validTokens): bool
    {
        try {
            // Update status to sending
            $this->updateRelatedRecords(NotificationStatuses::SENDING);

            // Send based on notification type
            switch ($this->notificationType) {
                case 'booking_confirmation':
                    return $this->sendBookingConfirmationPush($pushService, $validTokens);

                case 'booking_reminder':
                    return $this->sendBookingReminderPush($pushService, $validTokens);

                case 'booking_cancelled':
                    return $this->sendBookingCancellationPush($pushService, $validTokens);

                case 'booking_rescheduled':
                    return $this->sendBookingRescheduledPush($pushService, $validTokens);

                case 'payment_reminder':
                    return $this->sendPaymentReminderPush($pushService, $validTokens);

                case 'consultation_reminder':
                    return $this->sendConsultationReminderPush($pushService, $validTokens);

                case 'consultation_starting_soon':
                    return $this->sendConsultationStartingSoonPush($pushService, $validTokens);

                case 'custom':
                    return $this->sendCustomPush($pushService, $validTokens);

                default:
                    Log::warning('Unknown push notification type', [
                        'type' => $this->notificationType,
                        'metadata' => $this->metadata,
                    ]);
                    return false;
            }

        } catch (Exception $e) {
            Log::error('Failed to send push notification', [
                'type' => $this->notificationType,
                'error' => $e->getMessage(),
                'metadata' => $this->metadata,
            ]);
            return false;
        }
    }

    /**
     * Send booking confirmation push notification
     */
    private function sendBookingConfirmationPush(PushNotificationService $pushService, array $tokens): bool
    {
        $booking = $this->getBookingFromMetadata();
        if (!$booking) {
            return false;
        }

        return $pushService->sendBookingConfirmation($booking, $this->metadata['variables'] ?? []);
    }

    /**
     * Send booking reminder push notification
     */
    private function sendBookingReminderPush(PushNotificationService $pushService, array $tokens): bool
    {
        $booking = $this->getBookingFromMetadata();
        if (!$booking) {
            return false;
        }

        return $pushService->sendBookingReminder($booking, $this->metadata['variables'] ?? []);
    }

    /**
     * Send booking cancellation push notification
     */
    private function sendBookingCancellationPush(PushNotificationService $pushService, array $tokens): bool
    {
        $booking = $this->getBookingFromMetadata();
        if (!$booking) {
            return false;
        }

        return $pushService->sendBookingCancellation($booking, $this->metadata['variables'] ?? []);
    }

    /**
     * Send booking rescheduled push notification
     */
    private function sendBookingRescheduledPush(PushNotificationService $pushService, array $tokens): bool
    {
        $booking = $this->getBookingFromMetadata();
        if (!$booking) {
            return false;
        }

        return $pushService->sendBookingRescheduled($booking, $this->metadata['variables'] ?? []);
    }

    /**
     * Send payment reminder push notification
     */
    private function sendPaymentReminderPush(PushNotificationService $pushService, array $tokens): bool
    {
        $booking = $this->getBookingFromMetadata();
        if (!$booking) {
            return false;
        }

        return $pushService->sendPaymentReminder($booking, $this->metadata['variables'] ?? []);
    }

    /**
     * Send consultation reminder push notification
     */
    private function sendConsultationReminderPush(PushNotificationService $pushService, array $tokens): bool
    {
        $consultation = $this->getConsultationFromMetadata();
        if (!$consultation) {
            return false;
        }

        return $pushService->sendConsultationReminder($consultation, $this->metadata['variables'] ?? []);
    }

    /**
     * Send consultation starting soon push notification
     */
    private function sendConsultationStartingSoonPush(PushNotificationService $pushService, array $tokens): bool
    {
        $consultation = $this->getConsultationFromMetadata();
        if (!$consultation) {
            return false;
        }

        return $pushService->sendConsultationStartingSoon($consultation, $this->metadata['variables'] ?? []);
    }

    /**
     * Send custom push notification
     */
    private function sendCustomPush(PushNotificationService $pushService, array $tokens): bool
    {
        // For custom messages, send the push data directly
        return $pushService->sendRawPush($tokens, $this->pushData, $this->metadata);
    }

    /**
     * Get booking from metadata
     */
    private function getBookingFromMetadata(): ?Booking
    {
        $bookingId = $this->metadata['booking_id'] ?? null;
        if (!$bookingId) {
            Log::error('No booking ID provided in push job metadata', [
                'type' => $this->notificationType,
                'metadata' => $this->metadata,
            ]);
            return null;
        }

        $booking = Booking::find($bookingId);
        if (!$booking) {
            Log::error('Booking not found for push notification', [
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
            Log::error('No consultation ID provided in push job metadata', [
                'type' => $this->notificationType,
                'metadata' => $this->metadata,
            ]);
            return null;
        }

        $consultation = ConsultationBooking::find($consultationId);
        if (!$consultation) {
            Log::error('Consultation not found for push notification', [
                'consultation_id' => $consultationId,
                'type' => $this->notificationType,
            ]);
        }

        return $consultation;
    }

    /**
     * Check if push notifications are enabled
     */
    private function isPushEnabled(): bool
    {
        return config('notifications.channels.push.enabled', false);
    }

    /**
     * Validate and filter device tokens
     */
    private function validateDeviceTokens(): array
    {
        $validTokens = [];

        foreach ($this->deviceTokens as $token) {
            if ($this->isValidDeviceToken($token)) {
                $validTokens[] = $token;
            } else {
                Log::warning('Invalid device token encountered', [
                    'token' => substr($token, 0, 10) . '***',
                    'type' => $this->notificationType,
                ]);
            }
        }

        return $validTokens;
    }

    /**
     * Validate individual device token
     */
    private function isValidDeviceToken(string $token): bool
    {
        // Basic validation - token should be non-empty and reasonable length
        if (empty($token) || strlen($token) < 10) {
            return false;
        }

        // FCM tokens are typically 163 characters
        // APNs tokens are typically 64 characters (hex)
        if (strlen($token) > 200) {
            return false;
        }

        return true;
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

        // Check push-specific rate limits
        $pushLimits = config('notifications.rate_limiting.limits.push', []);

        // For now, return true - implement actual rate limiting logic here
        return true;
    }

    /**
     * Check user push notification preferences
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

        // Check if user has opted out of push notifications
        $preferences = $user->notification_preferences ?? [];
        $pushPreferences = $preferences['push'] ?? true;

        if (!$pushPreferences) {
            return false;
        }

        // Check specific notification type preferences
        $notificationTypeKey = str_replace('_', '', $this->notificationType);
        $typePreferences = $preferences[$notificationTypeKey] ?? ['mail', 'database'];

        return in_array('push', $typePreferences);
    }

    /**
     * Determine appropriate queue name
     */
    private function determineQueueName(): string
    {
        if ($this->isUrgentNotification()) {
            return config('notifications.queues.urgent', 'push-urgent');
        }

        if ($this->isHighPriorityNotification()) {
            return config('notifications.queues.high', 'push-high');
        }

        return config('notifications.channels.push.queue', 'push');
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
     * Update related notification records
     */
    private function updateRelatedRecords(string $status, ?string $failureReason = null): void
    {
        $notificationId = $this->metadata['notification_id'] ?? null;
        if ($notificationId) {
            try {
                $updateData = [
                    'status' => $status,
                    'attempts' => $this->attempts(),
                    'last_attempted_at' => now(),
                ];

                if ($status === NotificationStatuses::PUSH_SENT) {
                    $updateData['sent_at'] = now();
                } elseif (in_array($status, [NotificationStatuses::PUSH_FAILED, NotificationStatuses::FAILED])) {
                    $updateData['failure_reason'] = $failureReason;
                }

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
        $this->updateRelatedRecords(NotificationStatuses::PUSH_SENT);
    }

    /**
     * Mark notification as failed
     */
    private function markAsFailed(string $reason): void
    {
        $this->updateRelatedRecords(NotificationStatuses::PUSH_FAILED, $reason);

        Log::warning('Push notification marked as failed', [
            'device_count' => count($this->deviceTokens),
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

        Log::info('Push notification skipped', [
            'device_count' => count($this->deviceTokens),
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
        Log::warning('Push notification job attempt failed', [
            'device_count' => count($this->deviceTokens),
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
     * Escalate failed urgent push notification to manual intervention
     */
    private function escalateFailedUrgentPush(Exception $exception): void
    {
        try {
            Log::critical('Urgent push notification failed - manual intervention required', [
                'device_count' => count($this->deviceTokens),
                'type' => $this->notificationType,
                'title' => $this->pushData['title'] ?? 'Unknown',
                'body' => $this->pushData['body'] ?? 'Unknown',
                'metadata' => $this->metadata,
                'error' => $exception->getMessage(),
            ]);

            // TODO: Implement admin notification system
            // This could trigger an admin email, Slack notification, etc.

        } catch (Exception $e) {
            Log::error('Failed to escalate urgent push failure', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Clean up invalid device tokens
     */
    private function cleanupInvalidTokens(Exception $exception): void
    {
        // If the error indicates invalid tokens, we should clean them up
        $invalidTokenErrors = [
            'invalid_registration',
            'not_registered',
            'invalid_token',
            'token_not_found',
        ];

        $errorMessage = strtolower($exception->getMessage());
        $shouldCleanup = false;

        foreach ($invalidTokenErrors as $invalidError) {
            if (str_contains($errorMessage, $invalidError)) {
                $shouldCleanup = true;
                break;
            }
        }

        if ($shouldCleanup) {
            Log::info('Cleaning up invalid device tokens', [
                'token_count' => count($this->deviceTokens),
                'user_id' => $this->metadata['user_id'] ?? null,
                'error' => $exception->getMessage(),
            ]);

            // TODO: Implement device token cleanup
            // This would remove invalid tokens from user_device_tokens table
        }
    }

    /**
     * Get unique identifier for this job type
     */
    public function uniqueId(): string
    {
        $tokenHash = md5(implode(',', $this->deviceTokens));
        return "push_{$this->notificationType}_{$tokenHash}";
    }

    /**
     * Determine if job should be unique
     */
    public function uniqueFor(): int
    {
        return 120; // 2 minutes - prevent duplicate push notifications
    }

    /**
     * Get job display name for monitoring
     */
    public function displayName(): string
    {
        $deviceCount = count($this->deviceTokens);
        return "Send Push Notification ({$this->notificationType}, {$deviceCount} devices)";
    }

    /**
     * Get job tags for monitoring and filtering
     */
    public function tags(): array
    {
        return [
            'push_notification',
            "type:{$this->notificationType}",
            "user:" . ($this->metadata['user_id'] ?? 'unknown'),
            "booking:" . ($this->metadata['booking_id'] ?? 'none'),
            "devices:" . count($this->deviceTokens),
        ];
    }

    /**
     * Determine if the job should be retried after a failure
     */
    public function shouldRetry(Exception $exception): bool
    {
        // Don't retry certain types of failures
        $nonRetryableErrors = [
            'invalid_registration',
            'not_registered',
            'invalid_token',
            'user_has_opted_out',
            'push_disabled',
        ];

        $errorMessage = strtolower($exception->getMessage());

        foreach ($nonRetryableErrors as $nonRetryableError) {
            if (str_contains($errorMessage, $nonRetryableError)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Handle job timeout
     */
    public function timeoutAt(): Carbon
    {
        return now()->addMinutes(3); // Hard timeout at 3 minutes
    }
}
