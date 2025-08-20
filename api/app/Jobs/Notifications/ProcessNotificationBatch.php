<?php

namespace App\Jobs\Notifications;

use App\Constants\NotificationStatuses;
use App\Models\BookingNotification;
use App\Services\V1\Notifications\BookingNotificationService;
use App\Services\V1\Notifications\SmsNotificationService;
use App\Services\V1\Notifications\PushNotificationService;
use Carbon\Carbon;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class ProcessNotificationBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public array $notificationIds;
    public int $batchSize;
    public string $batchType;
    public array $options;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 2;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 600; // 10 minutes

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public function backoff(): array
    {
        return [300, 900]; // 5 minutes, 15 minutes
    }

    /**
     * Create a new job instance.
     */
    public function __construct(
        array $notificationIds,
        string $batchType = 'mixed',
        array $options = []
    ) {
        $this->notificationIds = $notificationIds;
        $this->batchSize = count($notificationIds);
        $this->batchType = $batchType;
        $this->options = $options;

        // Use batch processing queue
        $this->onQueue(config('notifications.queues.low', 'notifications-batch'));
    }

    /**
     * Execute the job.
     */
    public function handle(
        BookingNotificationService $notificationService,
        SmsNotificationService $smsService,
        PushNotificationService $pushService
    ): void {
        try {
            Log::info('Starting notification batch processing', [
                'batch_size' => $this->batchSize,
                'batch_type' => $this->batchType,
                'notification_ids' => $this->notificationIds,
                'options' => $this->options,
            ]);

            $startTime = now();
            $results = [
                'processed' => 0,
                'failed' => 0,
                'skipped' => 0,
                'by_type' => [],
                'by_channel' => [],
                'execution_time' => 0,
            ];

            // Load notifications with relationships
            $notifications = $this->loadNotifications();

            if ($notifications->isEmpty()) {
                Log::warning('No notifications found for batch processing', [
                    'notification_ids' => $this->notificationIds,
                ]);
                return;
            }

            // Group notifications by type and channel for efficient processing
            $groupedNotifications = $this->groupNotifications($notifications);

            // Process each group
            foreach ($groupedNotifications as $group => $groupNotifications) {
                $groupResults = $this->processNotificationGroup(
                    $groupNotifications,
                    $group,
                    $notificationService,
                    $smsService,
                    $pushService
                );

                // Merge results
                $results['processed'] += $groupResults['processed'];
                $results['failed'] += $groupResults['failed'];
                $results['skipped'] += $groupResults['skipped'];
                $results['by_type'] = array_merge_recursive($results['by_type'], $groupResults['by_type']);
                $results['by_channel'] = array_merge_recursive($results['by_channel'], $groupResults['by_channel']);

                // Add small delay between groups to prevent overwhelming external services
                if (count($groupedNotifications) > 1) {
                    usleep(200000); // 0.2 seconds
                }
            }

            $results['execution_time'] = now()->diffInSeconds($startTime);

            Log::info('Completed notification batch processing', [
                'results' => $results,
                'batch_id' => $this->getBatchId(),
            ]);

            // Schedule cleanup if needed
            $this->scheduleCleanupIfNeeded($results);

        } catch (Exception $e) {
            Log::error('Notification batch processing failed', [
                'batch_size' => $this->batchSize,
                'batch_type' => $this->batchType,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Load notifications with necessary relationships
     */
    private function loadNotifications(): Collection
    {
        return BookingNotification::whereIn('id', $this->notificationIds)
            ->with(['booking.user', 'booking.service', 'booking.serviceLocation'])
            ->where('status', NotificationStatuses::PENDING)
            ->orderBy('priority')
            ->orderBy('scheduled_at')
            ->get();
    }

    /**
     * Group notifications for efficient batch processing
     */
    private function groupNotifications(Collection $notifications): array
    {
        $grouped = [];

        foreach ($notifications as $notification) {
            $channels = json_decode($notification->channels, true) ?? [];

            foreach ($channels as $channel) {
                $groupKey = "{$notification->type}_{$channel}";

                if (!isset($grouped[$groupKey])) {
                    $grouped[$groupKey] = collect();
                }

                $grouped[$groupKey]->push($notification);
            }
        }

        return $grouped;
    }

    /**
     * Process a group of notifications
     */
    private function processNotificationGroup(
        Collection $notifications,
        string $groupKey,
        BookingNotificationService $notificationService,
        SmsNotificationService $smsService,
        PushNotificationService $pushService
    ): array {
        $results = [
            'processed' => 0,
            'failed' => 0,
            'skipped' => 0,
            'by_type' => [],
            'by_channel' => [],
        ];

        [$type, $channel] = explode('_', $groupKey, 2);

        Log::info('Processing notification group', [
            'group' => $groupKey,
            'count' => $notifications->count(),
            'type' => $type,
            'channel' => $channel,
        ]);

        foreach ($notifications as $notification) {
            try {
                $result = $this->processIndividualNotification(
                    $notification,
                    $channel,
                    $notificationService,
                    $smsService,
                    $pushService
                );

                if ($result === 'processed') {
                    $results['processed']++;
                } elseif ($result === 'skipped') {
                    $results['skipped']++;
                } else {
                    $results['failed']++;
                }

                // Track by type and channel
                $results['by_type'][$type] = ($results['by_type'][$type] ?? 0) + 1;
                $results['by_channel'][$channel] = ($results['by_channel'][$channel] ?? 0) + 1;

            } catch (Exception $e) {
                $results['failed']++;
                Log::warning('Failed to process notification in batch', [
                    'notification_id' => $notification->id,
                    'group' => $groupKey,
                    'error' => $e->getMessage(),
                ]);
            }

            // Add small delay between individual notifications
            usleep(50000); // 0.05 seconds
        }

        return $results;
    }

    /**
     * Process individual notification within batch
     */
    private function processIndividualNotification(
        BookingNotification $notification,
        string $channel,
        BookingNotificationService $notificationService,
        SmsNotificationService $smsService,
        PushNotificationService $pushService
    ): string {
        try {
            // Check if notification is still valid
            if (!$this->isNotificationValid($notification)) {
                $this->markNotificationAsSkipped($notification, 'Notification no longer valid');
                return 'skipped';
            }

            // Update notification status to indicate processing
            $notification->update([
                'status' => NotificationStatuses::SENDING,
                'attempts' => $notification->attempts + 1,
                'last_attempted_at' => now(),
            ]);

            $booking = $notification->booking;
            $data = json_decode($notification->data, true) ?? [];
            $success = false;

            // Process based on channel and type
            switch ($channel) {
                case 'mail':
                    $success = $this->processEmailNotification($notification, $booking, $data, $notificationService);
                    break;

                case 'sms':
                    $success = $this->processSmsNotification($notification, $booking, $data, $smsService);
                    break;

                case 'push':
                    $success = $this->processPushNotification($notification, $booking, $data, $pushService);
                    break;

                case 'database':
                    $success = $this->processDatabaseNotification($notification, $booking, $data);
                    break;

                default:
                    Log::warning('Unknown notification channel in batch', [
                        'notification_id' => $notification->id,
                        'channel' => $channel,
                    ]);
                    $success = false;
                    break;
            }

            // Update notification status based on result
            $notification->update([
                'status' => $success ? NotificationStatuses::SENT : NotificationStatuses::FAILED,
                'sent_at' => $success ? now() : null,
                'failure_reason' => $success ? null : 'Batch processing failed',
            ]);

            return $success ? 'processed' : 'failed';

        } catch (Exception $e) {
            $notification->update([
                'status' => NotificationStatuses::FAILED,
                'failure_reason' => $e->getMessage(),
            ]);

            Log::error('Exception processing notification in batch', [
                'notification_id' => $notification->id,
                'channel' => $channel,
                'error' => $e->getMessage(),
            ]);

            return 'failed';
        }
    }

    /**
     * Process email notification
     */
    private function processEmailNotification(
        BookingNotification $notification,
                            $booking,
        array $data,
        BookingNotificationService $notificationService
    ): bool {
        switch ($notification->type) {
            case 'booking_confirmation':
                return $notificationService->sendBookingConfirmation($booking);

            case 'booking_reminder':
                $hoursUntil = $data['hours_before'] ?? $this->calculateHoursUntil($booking);
                $reminderType = $data['reminder_type'] ?? 'standard';
                return $notificationService->sendBookingReminder($booking, $hoursUntil, $reminderType);

            case 'payment_reminder':
                return $notificationService->sendPaymentReminder($booking, $data);

            case 'booking_cancelled':
                return $notificationService->sendBookingCancellation($booking, $data['cancellation_reason'] ?? '');

            case 'booking_rescheduled':
                $oldDateTime = isset($data['old_datetime']) ? Carbon::parse($data['old_datetime']) : now();
                return $notificationService->sendBookingRescheduled($booking, $oldDateTime);

            default:
                return false;
        }
    }

    /**
     * Process SMS notification
     */
    private function processSmsNotification(
        BookingNotification $notification,
                            $booking,
        array $data,
        SmsNotificationService $smsService
    ): bool {
        switch ($notification->type) {
            case 'booking_confirmation':
                return $smsService->sendBookingConfirmation($booking, $data['variables'] ?? []);

            case 'booking_reminder':
                return $smsService->sendBookingReminder($booking, $data['variables'] ?? []);

            case 'booking_cancelled':
                return $smsService->sendBookingCancellation($booking, $data['variables'] ?? []);

            case 'booking_rescheduled':
                return $smsService->sendBookingRescheduled($booking, $data['variables'] ?? []);

            case 'payment_reminder':
                return $smsService->sendPaymentReminder($booking, $data['variables'] ?? []);

            default:
                return false;
        }
    }

    /**
     * Process push notification
     */
    private function processPushNotification(
        BookingNotification $notification,
                            $booking,
        array $data,
        PushNotificationService $pushService
    ): bool {
        switch ($notification->type) {
            case 'booking_confirmation':
                return $pushService->sendBookingConfirmation($booking, $data['variables'] ?? []);

            case 'booking_reminder':
                return $pushService->sendBookingReminder($booking, $data['variables'] ?? []);

            case 'booking_cancelled':
                return $pushService->sendBookingCancellation($booking, $data['variables'] ?? []);

            case 'booking_rescheduled':
                return $pushService->sendBookingRescheduled($booking, $data['variables'] ?? []);

            case 'payment_reminder':
                return $pushService->sendPaymentReminder($booking, $data['variables'] ?? []);

            default:
                return false;
        }
    }

    /**
     * Process database notification
     */
    private function processDatabaseNotification(
        BookingNotification $notification,
                            $booking,
        array $data
    ): bool {
        try {
            // Create in-app notification record
            $booking->user->notify(new \App\Notifications\BookingNotification([
                'type' => $notification->type,
                'booking_id' => $booking->id,
                'booking_reference' => $booking->booking_reference,
                'data' => $data,
            ]));

            return true;

        } catch (Exception $e) {
            Log::error('Failed to create database notification', [
                'notification_id' => $notification->id,
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Check if notification is still valid
     */
    private function isNotificationValid(BookingNotification $notification): bool
    {
        $booking = $notification->booking;

        if (!$booking) {
            return false;
        }

        // Check if booking is cancelled or completed
        if (in_array($booking->status, ['cancelled', 'completed', 'no_show'])) {
            return false;
        }

        // Check if booking is too far in the past
        if ($booking->scheduled_at->lt(now()->subHour())) {
            return false;
        }

        // Check specific notification type validity
        switch ($notification->type) {
            case 'payment_reminder':
                return $booking->payment_status === 'pending' && $booking->remaining_amount > 0;

            case 'booking_reminder':
                return $booking->scheduled_at->gt(now()->addMinutes(15));

            default:
                return true;
        }
    }

    /**
     * Mark notification as skipped
     */
    private function markNotificationAsSkipped(BookingNotification $notification, string $reason): void
    {
        $notification->update([
            'status' => NotificationStatuses::SKIPPED,
            'failure_reason' => $reason,
            'attempts' => $notification->attempts + 1,
            'last_attempted_at' => now(),
        ]);

        Log::info('Notification skipped in batch', [
            'notification_id' => $notification->id,
            'reason' => $reason,
        ]);
    }

    /**
     * Calculate hours until booking
     */
    private function calculateHoursUntil($booking): int
    {
        return max(0, now()->diffInHours($booking->scheduled_at, false));
    }

    /**
     * Schedule cleanup if needed
     */
    private function scheduleCleanupIfNeeded(array $results): void
    {
        // If we processed a lot of notifications, schedule cleanup
        if ($results['processed'] + $results['failed'] >= 50) {
            CleanupFailedNotifications::dispatch()
                ->delay(now()->addMinutes(30));

            Log::info('Scheduled notification cleanup after batch processing', [
                'processed_count' => $results['processed'],
                'failed_count' => $results['failed'],
            ]);
        }
    }

    /**
     * Get unique batch identifier
     */
    private function getBatchId(): string
    {
        return 'batch_' . md5(implode(',', $this->notificationIds)) . '_' . now()->format('YmdHi');
    }

    /**
     * Handle a job failure.
     */
    public function failed(Exception $exception): void
    {
        Log::error('Notification batch processing job failed permanently', [
            'batch_size' => $this->batchSize,
            'batch_type' => $this->batchType,
            'notification_ids' => $this->notificationIds,
            'attempts' => $this->attempts(),
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        // Mark remaining notifications as failed
        BookingNotification::whereIn('id', $this->notificationIds)
            ->where('status', NotificationStatuses::SENDING)
            ->update([
                'status' => NotificationStatuses::FAILED,
                'failure_reason' => 'Batch processing job failed: ' . $exception->getMessage(),
                'last_attempted_at' => now(),
            ]);
    }

    /**
     * Get unique identifier for this job type
     */
    public function uniqueId(): string
    {
        return 'notification_batch_' . md5(implode(',', $this->notificationIds));
    }

    /**
     * Determine if job should be unique
     */
    public function uniqueFor(): int
    {
        return 3600; // 1 hour - prevent duplicate batch processing
    }

    /**
     * Get job display name for monitoring
     */
    public function displayName(): string
    {
        return "Process Notification Batch ({$this->batchType}, {$this->batchSize} notifications)";
    }

    /**
     * Get job tags for monitoring and filtering
     */
    public function tags(): array
    {
        return [
            'notification_batch',
            "batch_type:{$this->batchType}",
            "batch_size:{$this->batchSize}",
            'batch_processing',
        ];
    }

    /**
     * Create batch from pending notifications
     */
    public static function createFromPending(int $batchSize = 50, ?string $type = null): ?self
    {
        $query = BookingNotification::where('status', NotificationStatuses::PENDING)
            ->where('scheduled_at', '<=', now())
            ->orderBy('priority')
            ->orderBy('scheduled_at');

        if ($type) {
            $query->where('type', $type);
        }

        $notifications = $query->limit($batchSize)->pluck('id')->toArray();

        if (empty($notifications)) {
            return null;
        }

        return new self($notifications, $type ?? 'mixed', [
            'created_from' => 'pending',
            'created_at' => now()->toISOString(),
        ]);
    }

    /**
     * Create batch from failed notifications eligible for retry
     */
    public static function createFromFailed(int $batchSize = 25): ?self
    {
        $notifications = BookingNotification::where('status', NotificationStatuses::FAILED)
            ->where('attempts', '<', config('notifications.retry.max_attempts', 3))
            ->where('last_attempted_at', '<=', now()->subMinutes(30))
            ->whereNotIn('failure_reason', [
                'User has opted out',
                'Invalid email address',
                'Booking not found',
            ])
            ->limit($batchSize)
            ->pluck('id')
            ->toArray();

        if (empty($notifications)) {
            return null;
        }

        return new self($notifications, 'retry', [
            'created_from' => 'failed_retry',
            'created_at' => now()->toISOString(),
        ]);
    }
}
