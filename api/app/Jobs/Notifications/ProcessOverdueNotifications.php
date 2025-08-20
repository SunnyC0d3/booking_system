<?php

namespace App\Jobs\Notifications;

use App\Constants\NotificationStatuses;
use App\Models\Booking;
use App\Models\BookingNotification;
use App\Services\V1\Notifications\BookingNotificationService;
use App\Services\V1\Notifications\NotificationSchedulerService;
use Carbon\Carbon;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ProcessOverdueNotifications implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public ?int $batchSize;
    public ?string $notificationType;
    public array $options;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 300; // 5 minutes

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public function backoff(): array
    {
        return [60, 300, 900]; // 1 minute, 5 minutes, 15 minutes
    }

    /**
     * Create a new job instance.
     */
    public function __construct(
        ?int $batchSize = null,
        ?string $notificationType = null,
        array $options = []
    ) {
        $this->batchSize = $batchSize ?? 100;
        $this->notificationType = $notificationType;
        $this->options = $options;

        // Use low priority queue for batch processing
        $this->onQueue(config('notifications.queues.low', 'notifications-low'));
    }

    /**
     * Execute the job.
     */
    public function handle(
        BookingNotificationService $notificationService,
        NotificationSchedulerService $schedulerService
    ): void {
        try {
            Log::info('Starting overdue notification processing', [
                'batch_size' => $this->batchSize,
                'notification_type' => $this->notificationType,
                'options' => $this->options,
            ]);

            $results = [
                'pending_processed' => 0,
                'overdue_payments' => 0,
                'failed_retries' => 0,
                'expired_cancelled' => 0,
                'total_processed' => 0,
            ];

            // Process pending notifications that are due
            $results['pending_processed'] = $this->processPendingNotifications($notificationService);

            // Process overdue payment notifications
            $results['overdue_payments'] = $this->processOverduePayments($schedulerService);

            // Retry failed notifications that are eligible
            $results['failed_retries'] = $this->retryFailedNotifications($notificationService);

            // Cancel expired notifications
            $results['expired_cancelled'] = $this->cancelExpiredNotifications();

            $results['total_processed'] = array_sum($results);

            Log::info('Completed overdue notification processing', [
                'results' => $results,
                'execution_time' => now()->diffInSeconds($this->getStartTime()),
            ]);

            // Schedule next processing if needed
            $this->scheduleNextProcessing($results);

        } catch (Exception $e) {
            Log::error('Overdue notification processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Process notifications that are due to be sent
     */
    private function processPendingNotifications(BookingNotificationService $notificationService): int
    {
        try {
            $query = BookingNotification::where('status', NotificationStatuses::PENDING)
                ->where('scheduled_at', '<=', now())
                ->where('attempts', '<', config('notifications.retry.max_attempts', 3))
                ->orderBy('priority')
                ->orderBy('scheduled_at');

            // Filter by notification type if specified
            if ($this->notificationType) {
                $query->where('type', $this->notificationType);
            }

            $notifications = $query->limit($this->batchSize)->get();

            if ($notifications->isEmpty()) {
                return 0;
            }

            $processed = 0;
            $failed = 0;

            foreach ($notifications as $notification) {
                try {
                    $success = $this->processIndividualNotification($notification, $notificationService);
                    if ($success) {
                        $processed++;
                    } else {
                        $failed++;
                    }
                } catch (Exception $e) {
                    $failed++;
                    Log::warning('Failed to process individual notification', [
                        'notification_id' => $notification->id,
                        'error' => $e->getMessage(),
                    ]);
                }

                // Add small delay to prevent overwhelming external services
                if (($processed + $failed) % 10 === 0) {
                    usleep(100000); // 0.1 seconds
                }
            }

            Log::info('Processed pending notifications', [
                'total_found' => $notifications->count(),
                'processed' => $processed,
                'failed' => $failed,
            ]);

            return $processed;

        } catch (Exception $e) {
            Log::error('Failed to process pending notifications', [
                'error' => $e->getMessage(),
            ]);
            return 0;
        }
    }

    /**
     * Process individual notification
     */
    private function processIndividualNotification(
        BookingNotification $notification,
        BookingNotificationService $notificationService
    ): bool {
        try {
            // Update notification status to indicate processing
            $notification->update([
                'status' => NotificationStatuses::SENDING,
                'attempts' => $notification->attempts + 1,
                'last_attempted_at' => now(),
            ]);

            $booking = $notification->booking;
            if (!$booking) {
                $notification->update([
                    'status' => NotificationStatuses::FAILED,
                    'failure_reason' => 'Associated booking not found',
                ]);
                return false;
            }

            // Check if notification is still relevant
            if (!$this->isNotificationStillRelevant($notification, $booking)) {
                $notification->update([
                    'status' => NotificationStatuses::SKIPPED,
                    'failure_reason' => 'Notification no longer relevant',
                ]);
                return true; // Consider as successful processing
            }

            $data = json_decode($notification->data, true) ?? [];
            $success = false;

            // Process based on notification type
            switch ($notification->type) {
                case 'booking_confirmation':
                    $success = $notificationService->sendBookingConfirmation($booking);
                    break;

                case 'booking_reminder':
                    $hoursUntil = $data['hours_before'] ?? $this->calculateHoursUntil($booking);
                    $reminderType = $data['reminder_type'] ?? 'standard';
                    $success = $notificationService->sendBookingReminder($booking, $hoursUntil, $reminderType);
                    break;

                case 'payment_reminder':
                    $success = $notificationService->sendPaymentReminder($booking, $data);
                    break;

                case 'booking_cancelled':
                    $success = $notificationService->sendBookingCancellation($booking, $data['cancellation_reason'] ?? '');
                    break;

                case 'booking_rescheduled':
                    $oldDateTime = isset($data['old_datetime']) ? Carbon::parse($data['old_datetime']) : now();
                    $success = $notificationService->sendBookingRescheduled($booking, $oldDateTime);
                    break;

                default:
                    Log::warning('Unknown notification type encountered', [
                        'notification_id' => $notification->id,
                        'type' => $notification->type,
                    ]);
                    $success = false;
                    break;
            }

            // Update notification status based on result
            $notification->update([
                'status' => $success ? NotificationStatuses::SENT : NotificationStatuses::FAILED,
                'sent_at' => $success ? now() : null,
                'failure_reason' => $success ? null : 'Processing failed during execution',
            ]);

            return $success;

        } catch (Exception $e) {
            $notification->update([
                'status' => NotificationStatuses::FAILED,
                'failure_reason' => $e->getMessage(),
            ]);

            Log::error('Failed to process individual notification', [
                'notification_id' => $notification->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Process overdue payment notifications
     */
    private function processOverduePayments(NotificationSchedulerService $schedulerService): int
    {
        try {
            // Find bookings with pending payments that are overdue
            $overdueBookings = Booking::where('payment_status', 'pending')
                ->where('remaining_amount', '>', 0)
                ->where('scheduled_at', '>', now()) // Only future bookings
                ->whereHas('user', function ($query) {
                    $query->where('is_active', true);
                })
                ->get();

            $processed = 0;

            foreach ($overdueBookings as $booking) {
                try {
                    // Check if payment is actually overdue
                    if ($this->isPaymentOverdue($booking)) {
                        // Check if we haven't sent an overdue notice recently
                        $recentOverdueNotice = BookingNotification::where('booking_id', $booking->id)
                            ->where('type', 'payment_overdue')
                            ->where('created_at', '>=', now()->subHours(24))
                            ->exists();

                        if (!$recentOverdueNotice) {
                            $schedulerService->scheduleOverduePaymentNotification($booking);
                            $processed++;
                        }
                    }
                } catch (Exception $e) {
                    Log::warning('Failed to process overdue payment for booking', [
                        'booking_id' => $booking->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            if ($processed > 0) {
                Log::info('Processed overdue payments', [
                    'total_overdue_bookings' => $overdueBookings->count(),
                    'new_notifications_scheduled' => $processed,
                ]);
            }

            return $processed;

        } catch (Exception $e) {
            Log::error('Failed to process overdue payments', [
                'error' => $e->getMessage(),
            ]);
            return 0;
        }
    }

    /**
     * Retry failed notifications that are eligible for retry
     */
    private function retryFailedNotifications(BookingNotificationService $notificationService): int
    {
        try {
            $retryableNotifications = BookingNotification::where('status', NotificationStatuses::FAILED)
                ->where('attempts', '<', config('notifications.retry.max_attempts', 3))
                ->where('last_attempted_at', '<=', now()->subMinutes(30)) // Wait at least 30 minutes
                ->whereNotIn('failure_reason', [
                    'User has opted out',
                    'Invalid email address',
                    'Booking not found',
                    'User not found',
                ])
                ->limit($this->batchSize)
                ->get();

            $retried = 0;

            foreach ($retryableNotifications as $notification) {
                try {
                    // Reset status to pending for retry
                    $notification->update([
                        'status' => NotificationStatuses::PENDING,
                        'scheduled_at' => now(),
                    ]);

                    $success = $this->processIndividualNotification($notification, $notificationService);
                    if ($success) {
                        $retried++;
                    }
                } catch (Exception $e) {
                    Log::warning('Failed to retry notification', [
                        'notification_id' => $notification->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            if ($retried > 0) {
                Log::info('Retried failed notifications', [
                    'total_eligible' => $retryableNotifications->count(),
                    'successfully_retried' => $retried,
                ]);
            }

            return $retried;

        } catch (Exception $e) {
            Log::error('Failed to retry failed notifications', [
                'error' => $e->getMessage(),
            ]);
            return 0;
        }
    }

    /**
     * Cancel notifications that have expired
     */
    private function cancelExpiredNotifications(): int
    {
        try {
            // Cancel notifications for past events
            $expiredCount = BookingNotification::whereIn('status', [
                NotificationStatuses::PENDING,
                NotificationStatuses::QUEUED
            ])
                ->whereHas('booking', function ($query) {
                    $query->where('scheduled_at', '<', now()->subHours(1));
                })
                ->update([
                    'status' => NotificationStatuses::EXPIRED,
                    'failure_reason' => 'Notification expired - event has passed',
                    'cancelled_at' => now(),
                ]);

            if ($expiredCount > 0) {
                Log::info('Cancelled expired notifications', [
                    'cancelled_count' => $expiredCount,
                ]);
            }

            return $expiredCount;

        } catch (Exception $e) {
            Log::error('Failed to cancel expired notifications', [
                'error' => $e->getMessage(),
            ]);
            return 0;
        }
    }

    /**
     * Check if notification is still relevant
     */
    private function isNotificationStillRelevant(BookingNotification $notification, Booking $booking): bool
    {
        // Check if booking is cancelled or completed
        if (in_array($booking->status, ['cancelled', 'completed', 'no_show'])) {
            return false;
        }

        // Check if booking is in the past (with some tolerance)
        if ($booking->scheduled_at->lt(now()->subHour())) {
            return false;
        }

        // Check specific notification type relevance
        switch ($notification->type) {
            case 'payment_reminder':
                return $booking->payment_status === 'pending' && $booking->remaining_amount > 0;

            case 'booking_reminder':
                // Don't send reminders too close to the event
                return $booking->scheduled_at->gt(now()->addMinutes(15));

            default:
                return true;
        }
    }

    /**
     * Check if payment is overdue
     */
    private function isPaymentOverdue(Booking $booking): bool
    {
        // Consider payment overdue if booking is within 48 hours and payment is still pending
        $paymentDueDate = $booking->scheduled_at->subHours(48);
        return now()->gt($paymentDueDate) && $booking->payment_status === 'pending';
    }

    /**
     * Calculate hours until booking
     */
    private function calculateHoursUntil(Booking $booking): int
    {
        return max(0, now()->diffInHours($booking->scheduled_at, false));
    }

    /**
     * Schedule next processing job if needed
     */
    private function scheduleNextProcessing(array $results): void
    {
        // If we processed a significant number of items, schedule another run soon
        if ($results['total_processed'] >= $this->batchSize * 0.8) {
            ProcessOverdueNotifications::dispatch($this->batchSize, $this->notificationType, $this->options)
                ->delay(now()->addMinutes(5));

            Log::info('Scheduled next overdue notification processing', [
                'delay_minutes' => 5,
                'reason' => 'High processing volume detected',
            ]);
        }
    }

    /**
     * Get job start time for performance tracking
     */
    private function getStartTime(): Carbon
    {
        return $this->options['start_time'] ?? now();
    }

    /**
     * Handle a job failure.
     */
    public function failed(Exception $exception): void
    {
        Log::error('Overdue notification processing job failed permanently', [
            'batch_size' => $this->batchSize,
            'notification_type' => $this->notificationType,
            'attempts' => $this->attempts(),
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        // Could trigger admin notification here
    }

    /**
     * Get unique identifier for this job type
     */
    public function uniqueId(): string
    {
        $type = $this->notificationType ?? 'all';
        return "process_overdue_notifications_{$type}_" . now()->format('Y-m-d-H');
    }

    /**
     * Determine if job should be unique
     */
    public function uniqueFor(): int
    {
        return 3600; // 1 hour - prevent multiple overdue processing jobs
    }

    /**
     * Get job display name for monitoring
     */
    public function displayName(): string
    {
        $type = $this->notificationType ?? 'all types';
        return "Process Overdue Notifications ({$type}, batch: {$this->batchSize})";
    }

    /**
     * Get job tags for monitoring and filtering
     */
    public function tags(): array
    {
        return [
            'notification_processing',
            'overdue_notifications',
            "batch_size:{$this->batchSize}",
            "type:" . ($this->notificationType ?? 'all'),
        ];
    }
}
