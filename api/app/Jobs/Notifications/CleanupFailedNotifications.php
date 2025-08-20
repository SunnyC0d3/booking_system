<?php

namespace App\Jobs\Notifications;

use App\Constants\NotificationStatuses;
use App\Models\BookingNotification;
use Carbon\Carbon;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class CleanupFailedNotifications implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public array $options;
    public int $batchSize;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 2;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 900; // 15 minutes

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
    public function __construct(array $options = [])
    {
        $this->options = $options;
        $this->batchSize = $options['batch_size'] ?? 1000;

        // Use maintenance queue for cleanup tasks
        $this->onQueue('maintenance');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info('Starting notification cleanup process', [
                'options' => $this->options,
                'batch_size' => $this->batchSize,
            ]);

            $startTime = now();
            $results = [
                'expired_notifications_deleted' => 0,
                'old_failed_notifications_deleted' => 0,
                'old_sent_notifications_archived' => 0,
                'orphaned_notifications_deleted' => 0,
                'notification_logs_cleaned' => 0,
                'statistics_updated' => false,
                'total_processed' => 0,
                'execution_time' => 0,
            ];

            // Clean up expired notifications
            $results['expired_notifications_deleted'] = $this->cleanupExpiredNotifications();

            // Clean up old failed notifications
            $results['old_failed_notifications_deleted'] = $this->cleanupOldFailedNotifications();

            // Archive old sent notifications
            $results['old_sent_notifications_archived'] = $this->archiveOldSentNotifications();

            // Clean up orphaned notifications
            $results['orphaned_notifications_deleted'] = $this->cleanupOrphanedNotifications();

            // Clean up notification logs
            $results['notification_logs_cleaned'] = $this->cleanupNotificationLogs();

            // Update notification statistics
            $results['statistics_updated'] = $this->updateNotificationStatistics();

            $results['total_processed'] = array_sum(array_filter($results, 'is_numeric'));
            $results['execution_time'] = now()->diffInSeconds($startTime);

            Log::info('Completed notification cleanup process', [
                'results' => $results,
                'cleanup_id' => $this->getCleanupId(),
            ]);

            // Schedule next cleanup if needed
            $this->scheduleNextCleanup($results);

        } catch (Exception $e) {
            Log::error('Notification cleanup process failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Clean up expired notifications
     */
    private function cleanupExpiredNotifications(): int
    {
        try {
            // Delete notifications for bookings that have passed
            $expiredCount = BookingNotification::whereIn('status', [
                NotificationStatuses::PENDING,
                NotificationStatuses::QUEUED,
                NotificationStatuses::SENDING,
            ])
                ->whereHas('booking', function ($query) {
                    $query->where('scheduled_at', '<', now()->subHours(2));
                })
                ->delete();

            if ($expiredCount > 0) {
                Log::info('Cleaned up expired notifications', [
                    'deleted_count' => $expiredCount,
                ]);
            }

            return $expiredCount;

        } catch (Exception $e) {
            Log::error('Failed to cleanup expired notifications', [
                'error' => $e->getMessage(),
            ]);
            return 0;
        }
    }

    /**
     * Clean up old failed notifications
     */
    private function cleanupOldFailedNotifications(): int
    {
        try {
            $retentionDays = config('notifications.cleanup.retention_days.failed_notifications', 7);
            $cutoffDate = now()->subDays($retentionDays);

            $deletedCount = BookingNotification::whereIn('status', [
                NotificationStatuses::FAILED,
                NotificationStatuses::SMS_FAILED,
                NotificationStatuses::PUSH_FAILED,
                NotificationStatuses::EMAIL_BOUNCED,
                NotificationStatuses::EMAIL_COMPLAINED,
            ])
                ->where('last_attempted_at', '<', $cutoffDate)
                ->limit($this->batchSize)
                ->delete();

            if ($deletedCount > 0) {
                Log::info('Cleaned up old failed notifications', [
                    'deleted_count' => $deletedCount,
                    'retention_days' => $retentionDays,
                ]);
            }

            return $deletedCount;

        } catch (Exception $e) {
            Log::error('Failed to cleanup old failed notifications', [
                'error' => $e->getMessage(),
            ]);
            return 0;
        }
    }

    /**
     * Archive old sent notifications
     */
    private function archiveOldSentNotifications(): int
    {
        try {
            $readRetentionDays = config('notifications.cleanup.retention_days.read_notifications', 30);
            $unreadRetentionDays = config('notifications.cleanup.retention_days.unread_notifications', 90);

            $archivedCount = 0;

            // Archive read notifications older than retention period
            $readCutoff = now()->subDays($readRetentionDays);
            $readArchived = BookingNotification::whereIn('status', [
                NotificationStatuses::READ,
                NotificationStatuses::EMAIL_OPENED,
                NotificationStatuses::EMAIL_CLICKED,
                NotificationStatuses::PUSH_CLICKED,
            ])
                ->where('sent_at', '<', $readCutoff)
                ->limit($this->batchSize / 2)
                ->delete();

            $archivedCount += $readArchived;

            // Archive unread notifications older than retention period
            $unreadCutoff = now()->subDays($unreadRetentionDays);
            $unreadArchived = BookingNotification::whereIn('status', [
                NotificationStatuses::SENT,
                NotificationStatuses::DELIVERED,
                NotificationStatuses::SMS_SENT,
                NotificationStatuses::SMS_DELIVERED,
                NotificationStatuses::PUSH_SENT,
                NotificationStatuses::PUSH_DELIVERED,
                NotificationStatuses::EMAIL_SENT,
                NotificationStatuses::EMAIL_DELIVERED,
            ])
                ->where('sent_at', '<', $unreadCutoff)
                ->limit($this->batchSize / 2)
                ->delete();

            $archivedCount += $unreadArchived;

            if ($archivedCount > 0) {
                Log::info('Archived old sent notifications', [
                    'read_archived' => $readArchived,
                    'unread_archived' => $unreadArchived,
                    'total_archived' => $archivedCount,
                    'read_retention_days' => $readRetentionDays,
                    'unread_retention_days' => $unreadRetentionDays,
                ]);
            }

            return $archivedCount;

        } catch (Exception $e) {
            Log::error('Failed to archive old sent notifications', [
                'error' => $e->getMessage(),
            ]);
            return 0;
        }
    }

    /**
     * Clean up orphaned notifications
     */
    private function cleanupOrphanedNotifications(): int
    {
        try {
            // Delete notifications where the associated booking no longer exists
            $orphanedCount = BookingNotification::whereDoesntHave('booking')
                ->limit($this->batchSize)
                ->delete();

            if ($orphanedCount > 0) {
                Log::info('Cleaned up orphaned notifications', [
                    'deleted_count' => $orphanedCount,
                ]);
            }

            return $orphanedCount;

        } catch (Exception $e) {
            Log::error('Failed to cleanup orphaned notifications', [
                'error' => $e->getMessage(),
            ]);
            return 0;
        }
    }

    /**
     * Clean up notification logs
     */
    private function cleanupNotificationLogs(): int
    {
        try {
            $logRetentionDays = config('notifications.cleanup.retention_days.notification_logs', 60);

            // This would clean up a notification_logs table if it exists
            // For now, we'll just return 0 as the table doesn't exist yet

            Log::info('Notification logs cleanup completed', [
                'retention_days' => $logRetentionDays,
                'cleaned_count' => 0,
                'note' => 'Notification logs table not implemented yet',
            ]);

            return 0;

        } catch (Exception $e) {
            Log::error('Failed to cleanup notification logs', [
                'error' => $e->getMessage(),
            ]);
            return 0;
        }
    }

    /**
     * Update notification statistics
     */
    private function updateNotificationStatistics(): bool
    {
        try {
            $stats = $this->calculateNotificationStatistics();

            // Store statistics in cache for dashboard display
            cache()->put('notification_statistics', $stats, now()->addHours(6));

            Log::info('Updated notification statistics', [
                'statistics' => $stats,
            ]);

            return true;

        } catch (Exception $e) {
            Log::error('Failed to update notification statistics', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Calculate notification statistics
     */
    private function calculateNotificationStatistics(): array
    {
        try {
            $stats = [
                'total_notifications' => 0,
                'by_status' => [],
                'by_type' => [],
                'by_channel' => [],
                'success_rate' => 0,
                'recent_activity' => [],
                'calculated_at' => now()->toISOString(),
            ];

            // Total notifications
            $stats['total_notifications'] = BookingNotification::count();

            // By status
            $statusCounts = BookingNotification::select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray();
            $stats['by_status'] = $statusCounts;

            // By type
            $typeCounts = BookingNotification::select('type', DB::raw('count(*) as count'))
                ->groupBy('type')
                ->pluck('count', 'type')
                ->toArray();
            $stats['by_type'] = $typeCounts;

            // Calculate success rate
            $totalSent = array_sum(array_filter($statusCounts, function($key) {
                return in_array($key, NotificationStatuses::SUCCESSFUL_STATUSES);
            }, ARRAY_FILTER_USE_KEY));

            $totalAttempted = array_sum($statusCounts);
            $stats['success_rate'] = $totalAttempted > 0 ? round(($totalSent / $totalAttempted) * 100, 2) : 0;

            // Recent activity (last 24 hours)
            $recentCounts = BookingNotification::where('created_at', '>=', now()->subDay())
                ->select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray();
            $stats['recent_activity'] = $recentCounts;

            return $stats;

        } catch (Exception $e) {
            Log::error('Failed to calculate notification statistics', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Schedule next cleanup if needed
     */
    private function scheduleNextCleanup(array $results): void
    {
        // If we processed a significant amount, schedule another cleanup soon
        if ($results['total_processed'] >= $this->batchSize * 0.8) {
            CleanupFailedNotifications::dispatch($this->options)
                ->delay(now()->addHours(1));

            Log::info('Scheduled next notification cleanup', [
                'delay_hours' => 1,
                'reason' => 'High processing volume detected',
                'processed_count' => $results['total_processed'],
            ]);
        }
    }

    /**
     * Get unique cleanup identifier
     */
    private function getCleanupId(): string
    {
        return 'cleanup_' . now()->format('Y-m-d_H-i-s');
    }

    /**
     * Handle a job failure.
     */
    public function failed(Exception $exception): void
    {
        Log::error('Notification cleanup job failed permanently', [
            'options' => $this->options,
            'batch_size' => $this->batchSize,
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
        return 'cleanup_notifications_' . now()->format('Y-m-d-H');
    }

    /**
     * Determine if job should be unique
     */
    public function uniqueFor(): int
    {
        return 3600; // 1 hour - prevent multiple cleanup jobs
    }

    /**
     * Get job display name for monitoring
     */
    public function displayName(): string
    {
        return "Cleanup Failed Notifications (batch: {$this->batchSize})";
    }

    /**
     * Get job tags for monitoring and filtering
     */
    public function tags(): array
    {
        return [
            'notification_cleanup',
            'maintenance',
            "batch_size:{$this->batchSize}",
        ];
    }

    /**
     * Optimize notification tables
     */
    private function optimizeNotificationTables(): void
    {
        try {
            // Optimize the notifications table
            DB::statement('OPTIMIZE TABLE booking_notifications');

            Log::info('Optimized notification tables');

        } catch (Exception $e) {
            Log::warning('Failed to optimize notification tables', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Generate cleanup report
     */
    private function generateCleanupReport(array $results): array
    {
        $report = [
            'cleanup_date' => now()->toDateString(),
            'execution_time' => $results['execution_time'],
            'summary' => [
                'total_items_processed' => $results['total_processed'],
                'expired_notifications' => $results['expired_notifications_deleted'],
                'failed_notifications' => $results['old_failed_notifications_deleted'],
                'archived_notifications' => $results['old_sent_notifications_archived'],
                'orphaned_notifications' => $results['orphaned_notifications_deleted'],
            ],
            'retention_policies' => [
                'failed_notifications_days' => config('notifications.cleanup.retention_days.failed_notifications', 7),
                'read_notifications_days' => config('notifications.cleanup.retention_days.read_notifications', 30),
                'unread_notifications_days' => config('notifications.cleanup.retention_days.unread_notifications', 90),
            ],
            'next_recommended_cleanup' => now()->addDays(1)->toDateString(),
        ];

        return $report;
    }

    /**
     * Check if cleanup is needed
     */
    public static function isCleanupNeeded(): bool
    {
        try {
            // Check if there are too many old failed notifications
            $oldFailedCount = BookingNotification::whereIn('status', [
                NotificationStatuses::FAILED,
                NotificationStatuses::SMS_FAILED,
                NotificationStatuses::PUSH_FAILED,
            ])
                ->where('last_attempted_at', '<', now()->subDays(7))
                ->count();

            // Check if there are too many old sent notifications
            $oldSentCount = BookingNotification::whereIn('status', [
                NotificationStatuses::READ,
                NotificationStatuses::EMAIL_OPENED,
            ])
                ->where('sent_at', '<', now()->subDays(30))
                ->count();

            // Cleanup needed if we have more than 1000 old records
            return ($oldFailedCount + $oldSentCount) > 1000;

        } catch (Exception $e) {
            Log::error('Failed to check if cleanup is needed', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Schedule automatic cleanup
     */
    public static function scheduleAutomatic(): void
    {
        if (self::isCleanupNeeded()) {
            CleanupFailedNotifications::dispatch([
                'automatic' => true,
                'triggered_at' => now()->toISOString(),
            ])->delay(now()->addMinutes(30));

            Log::info('Scheduled automatic notification cleanup', [
                'delay_minutes' => 30,
            ]);
        }
    }
}
