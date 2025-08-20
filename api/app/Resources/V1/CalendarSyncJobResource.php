<?php

namespace App\Resources\V1;

use App\Constants\CalendarProviders;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class CalendarSyncJobResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'job_type' => $this->job_type,
            'job_type_display' => $this->getJobTypeDisplay(),
            'status' => $this->status,
            'status_display' => $this->getStatusDisplay(),

            // Calendar integration context
            'calendar_integration' => [
                'id' => $this->calendarIntegration->id,
                'provider' => $this->calendarIntegration->provider,
                'provider_display_name' => $this->getProviderDisplayName(),
                'calendar_name' => $this->calendarIntegration->calendar_name,
                'user_id' => $this->calendarIntegration->user_id,
            ],

            // Job timing and duration
            'timing' => [
                'created_at' => $this->created_at->toISOString(),
                'started_at' => $this->started_at?->toISOString(),
                'completed_at' => $this->completed_at?->toISOString(),
                'updated_at' => $this->updated_at->toISOString(),
                'duration_seconds' => $this->getDurationSeconds(),
                'formatted_duration' => $this->getFormattedDuration(),
                'estimated_completion' => $this->getEstimatedCompletion()?->toISOString(),
                'time_in_queue' => $this->getTimeInQueue(),
                'time_running' => $this->getTimeRunning(),
            ],

            // Progress and performance metrics
            'progress' => [
                'events_processed' => $this->events_processed,
                'events_total' => $this->getEventsTotal(),
                'progress_percentage' => $this->getProgressPercentage(),
                'events_per_second' => $this->getEventsPerSecond(),
                'estimated_remaining_time' => $this->getEstimatedRemainingTime(),
                'current_phase' => $this->getCurrentPhase(),
                'phase_progress' => $this->getPhaseProgress(),
            ],

            // Job results and statistics
            'results' => $this->when($this->isCompleted(), function () {
                return [
                    'events_created' => $this->getEventsCreated(),
                    'events_updated' => $this->getEventsUpdated(),
                    'events_deleted' => $this->getEventsDeleted(),
                    'events_skipped' => $this->getEventsSkipped(),
                    'events_failed' => $this->getEventsFailed(),
                    'conflicts_detected' => $this->getConflictsDetected(),
                    'conflicts_resolved' => $this->getConflictsResolved(),
                    'api_calls_made' => $this->getApiCallsMade(),
                    'data_transferred_kb' => $this->getDataTransferredKb(),
                ];
            }),

            // Error information
            'error_info' => $this->when($this->hasFailed(), function () {
                return [
                    'error_message' => $this->error_message,
                    'error_type' => $this->getErrorType(),
                    'error_code' => $this->getErrorCode(),
                    'is_retryable' => $this->isRetryable(),
                    'retry_count' => $this->getRetryCount(),
                    'max_retries' => $this->getMaxRetries(),
                    'next_retry_at' => $this->getNextRetryAt()?->toISOString(),
                    'error_details' => $this->getErrorDetails(),
                    'troubleshooting_steps' => $this->getTroubleshootingSteps(),
                ];
            }),

            // Job configuration and parameters
            'configuration' => [
                'job_data' => $this->getJobDataSummary(),
                'sync_direction' => $this->getSyncDirection(),
                'date_range' => $this->getDateRange(),
                'filters_applied' => $this->getFiltersApplied(),
                'priority_level' => $this->getPriorityLevel(),
                'batch_size' => $this->getBatchSize(),
                'dry_run_mode' => $this->isDryRun(),
                'force_sync' => $this->isForceSync(),
            ],

            // Health and performance indicators
            'health_metrics' => [
                'success_rate' => $this->getSuccessRate(),
                'performance_score' => $this->getPerformanceScore(),
                'efficiency_rating' => $this->getEfficiencyRating(),
                'memory_usage_mb' => $this->getMemoryUsageMb(),
                'cpu_time_seconds' => $this->getCpuTimeSeconds(),
                'network_latency_ms' => $this->getNetworkLatencyMs(),
                'api_quota_usage' => $this->getApiQuotaUsage(),
            ],

            // Status flags and indicators
            'status_flags' => [
                'is_pending' => $this->isPending(),
                'is_processing' => $this->isProcessing(),
                'is_completed' => $this->isCompleted(),
                'is_failed' => $this->hasFailed(),
                'is_cancelled' => $this->isCancelled(),
                'is_stuck' => $this->isStuck(),
                'is_retrying' => $this->isRetrying(),
                'requires_attention' => $this->requiresAttention(),
            ],

            // User actions and permissions
            'actions' => [
                'can_retry' => $this->canRetry($request->user()),
                'can_cancel' => $this->canCancel($request->user()),
                'can_view_details' => $this->canViewDetails($request->user()),
                'can_view_logs' => $this->canViewLogs($request->user()),
                'can_download_report' => $this->canDownloadReport($request->user()),
                'available_actions' => $this->getAvailableActions($request->user()),
            ],

            // Related information
            'relationships' => [
                'parent_job_id' => $this->getParentJobId(),
                'child_jobs' => $this->when($this->hasChildJobs(), function () {
                    return $this->getChildJobsSummary();
                }),
                'related_bookings' => $this->when($this->hasRelatedBookings(), function () {
                    return $this->getRelatedBookingsSummary();
                }),
                'triggered_by' => $this->getTriggeredBy(),
            ],

            // Notifications and alerts
            'notifications' => [
                'completion_notifications_sent' => $this->getCompletionNotificationsSent(),
                'error_notifications_sent' => $this->getErrorNotificationsSent(),
                'should_notify_on_completion' => $this->shouldNotifyOnCompletion(),
                'notification_preferences' => $this->getNotificationPreferences(),
            ],

            // Display helpers for UI
            'display' => [
                'status_color' => $this->getStatusColor(),
                'status_icon' => $this->getStatusIcon(),
                'progress_bar_color' => $this->getProgressBarColor(),
                'urgency_level' => $this->getUrgencyLevel(),
                'summary_text' => $this->getSummaryText(),
                'detail_text' => $this->getDetailText(),
                'tooltip_text' => $this->getTooltipText(),
            ],

            // Debug and development info (admin only)
            'debug_info' => $this->when($this->shouldIncludeDebugInfo($request), function () {
                return [
                    'job_class' => $this->getJobClass(),
                    'queue_name' => $this->getQueueName(),
                    'job_payload' => $this->getJobPayload(),
                    'exception_trace' => $this->getExceptionTrace(),
                    'server_info' => $this->getServerInfo(),
                    'laravel_job_id' => $this->getLaravelJobId(),
                ];
            }),
        ];
    }

    /**
     * Get job type display name
     */
    private function getJobTypeDisplay(): string
    {
        return match ($this->job_type) {
            'sync_bookings' => 'Sync Bookings to Calendar',
            'sync_availability' => 'Sync Availability from Calendar',
            'sync_events' => 'Sync All Calendar Events',
            'full_sync' => 'Full Calendar Synchronization',
            'incremental_sync' => 'Incremental Calendar Sync',
            'webhook_sync' => 'Real-time Calendar Update',
            default => ucfirst(str_replace('_', ' ', $this->job_type))
        };
    }

    /**
     * Get status display name
     */
    private function getStatusDisplay(): string
    {
        return match ($this->status) {
            'pending' => 'Waiting to Start',
            'processing' => 'In Progress',
            'completed' => 'Completed Successfully',
            'failed' => 'Failed',
            'cancelled' => 'Cancelled',
            'retrying' => 'Retrying',
            'paused' => 'Paused',
            default => ucfirst($this->status)
        };
    }

    /**
     * Get provider display name
     */
    private function getProviderDisplayName(): string
    {
        return match ($this->calendarIntegration->provider) {
            CalendarProviders::GOOGLE => 'Google Calendar',
            CalendarProviders::OUTLOOK => 'Outlook Calendar',
            CalendarProviders::APPLE => 'Apple Calendar',
            CalendarProviders::ICAL => 'iCal/WebCal',
            default => ucfirst($this->calendarIntegration->provider) . ' Calendar'
        };
    }

    /**
     * Get job duration in seconds
     */
    private function getDurationSeconds(): ?int
    {
        if (!$this->started_at) {
            return null;
        }

        $endTime = $this->completed_at ?? now();
        return $this->started_at->diffInSeconds($endTime);
    }

    /**
     * Get formatted duration
     */
    private function getFormattedDuration(): ?string
    {
        $seconds = $this->getDurationSeconds();

        if ($seconds === null) {
            return null;
        }

        if ($seconds < 60) {
            return "{$seconds}s";
        }

        $minutes = floor($seconds / 60);
        $remainingSeconds = $seconds % 60;

        if ($minutes < 60) {
            return "{$minutes}m {$remainingSeconds}s";
        }

        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;

        return "{$hours}h {$remainingMinutes}m {$remainingSeconds}s";
    }

    /**
     * Get estimated completion time
     */
    private function getEstimatedCompletion(): ?Carbon
    {
        if (!$this->isProcessing() || !$this->started_at) {
            return null;
        }

        $progressPercentage = $this->getProgressPercentage();
        if ($progressPercentage <= 0) {
            return null;
        }

        $elapsedSeconds = $this->started_at->diffInSeconds(now());
        $totalEstimatedSeconds = ($elapsedSeconds / $progressPercentage) * 100;
        $remainingSeconds = $totalEstimatedSeconds - $elapsedSeconds;

        return now()->addSeconds($remainingSeconds);
    }

    /**
     * Get time in queue
     */
    private function getTimeInQueue(): ?string
    {
        if (!$this->started_at) {
            $seconds = $this->created_at->diffInSeconds(now());
            return $this->formatTimeInterval($seconds);
        }

        $seconds = $this->created_at->diffInSeconds($this->started_at);
        return $this->formatTimeInterval($seconds);
    }

    /**
     * Get time running
     */
    private function getTimeRunning(): ?string
    {
        if (!$this->started_at) {
            return null;
        }

        $endTime = $this->completed_at ?? now();
        $seconds = $this->started_at->diffInSeconds($endTime);

        return $this->formatTimeInterval($seconds);
    }

    /**
     * Get total events count
     */
    private function getEventsTotal(): ?int
    {
        $jobData = $this->job_data ?? [];
        return $jobData['events_total'] ?? null;
    }

    /**
     * Get progress percentage
     */
    private function getProgressPercentage(): float
    {
        $total = $this->getEventsTotal();

        if (!$total || $total === 0) {
            return $this->isCompleted() ? 100.0 : 0.0;
        }

        return min(100.0, ($this->events_processed / $total) * 100);
    }

    /**
     * Get events per second processing rate
     */
    private function getEventsPerSecond(): ?float
    {
        $duration = $this->getDurationSeconds();

        if (!$duration || $duration === 0) {
            return null;
        }

        return round($this->events_processed / $duration, 2);
    }

    /**
     * Get estimated remaining time
     */
    private function getEstimatedRemainingTime(): ?string
    {
        $eventsPerSecond = $this->getEventsPerSecond();
        $total = $this->getEventsTotal();

        if (!$eventsPerSecond || !$total || $eventsPerSecond === 0) {
            return null;
        }

        $remainingEvents = $total - $this->events_processed;
        $remainingSeconds = $remainingEvents / $eventsPerSecond;

        return $this->formatTimeInterval($remainingSeconds);
    }

    /**
     * Get current processing phase
     */
    private function getCurrentPhase(): string
    {
        $jobData = $this->job_data ?? [];

        return $jobData['current_phase'] ?? match ($this->status) {
            'pending' => 'queued',
            'processing' => 'syncing',
            'completed' => 'completed',
            'failed' => 'error',
            default => 'unknown'
        };
    }

    /**
     * Get phase progress
     */
    private function getPhaseProgress(): array
    {
        $phases = [
            'queued' => $this->isPending() ? 'current' : 'completed',
            'authenticating' => 'pending',
            'fetching' => 'pending',
            'processing' => 'pending',
            'syncing' => 'pending',
            'finalizing' => 'pending',
            'completed' => 'pending',
        ];

        if ($this->isProcessing()) {
            $phases['processing'] = 'current';
            $phases['queued'] = 'completed';
        }

        if ($this->isCompleted()) {
            foreach ($phases as $phase => $status) {
                $phases[$phase] = 'completed';
            }
        }

        return $phases;
    }

    /**
     * Get job results breakdown
     */
    private function getEventsCreated(): int
    {
        $jobData = $this->job_data ?? [];
        return $jobData['events_created'] ?? 0;
    }

    private function getEventsUpdated(): int
    {
        $jobData = $this->job_data ?? [];
        return $jobData['events_updated'] ?? 0;
    }

    private function getEventsDeleted(): int
    {
        $jobData = $this->job_data ?? [];
        return $jobData['events_deleted'] ?? 0;
    }

    private function getEventsSkipped(): int
    {
        $jobData = $this->job_data ?? [];
        return $jobData['events_skipped'] ?? 0;
    }

    private function getEventsFailed(): int
    {
        $jobData = $this->job_data ?? [];
        return $jobData['events_failed'] ?? 0;
    }

    private function getConflictsDetected(): int
    {
        $jobData = $this->job_data ?? [];
        return $jobData['conflicts_detected'] ?? 0;
    }

    private function getConflictsResolved(): int
    {
        $jobData = $this->job_data ?? [];
        return $jobData['conflicts_resolved'] ?? 0;
    }

    private function getApiCallsMade(): int
    {
        $jobData = $this->job_data ?? [];
        return $jobData['api_calls_made'] ?? 0;
    }

    private function getDataTransferredKb(): float
    {
        $jobData = $this->job_data ?? [];
        return round(($jobData['data_transferred_bytes'] ?? 0) / 1024, 2);
    }

    /**
     * Get error type classification
     */
    private function getErrorType(): ?string
    {
        if (!$this->error_message) {
            return null;
        }

        $message = strtolower($this->error_message);

        if (str_contains($message, 'auth') || str_contains($message, 'token')) {
            return 'authentication';
        }

        if (str_contains($message, 'rate limit') || str_contains($message, 'quota')) {
            return 'rate_limit';
        }

        if (str_contains($message, 'network') || str_contains($message, 'timeout')) {
            return 'network';
        }

        if (str_contains($message, 'permission') || str_contains($message, 'access')) {
            return 'permission';
        }

        return 'unknown';
    }

    /**
     * Get error code
     */
    private function getErrorCode(): ?string
    {
        $jobData = $this->job_data ?? [];
        return $jobData['error_code'] ?? null;
    }

    /**
     * Check if error is retryable
     */
    private function isRetryable(): bool
    {
        $errorType = $this->getErrorType();

        return in_array($errorType, ['network', 'rate_limit', 'unknown']);
    }

    /**
     * Get retry count
     */
    private function getRetryCount(): int
    {
        $jobData = $this->job_data ?? [];
        return $jobData['retry_count'] ?? 0;
    }

    /**
     * Get maximum retries allowed
     */
    private function getMaxRetries(): int
    {
        $jobData = $this->job_data ?? [];
        return $jobData['max_retries'] ?? 3;
    }

    /**
     * Get next retry time
     */
    private function getNextRetryAt(): ?Carbon
    {
        if (!$this->isRetryable() || $this->getRetryCount() >= $this->getMaxRetries()) {
            return null;
        }

        $retryCount = $this->getRetryCount();
        $backoffMinutes = min(60, pow(2, $retryCount)); // Exponential backoff, max 1 hour

        return $this->updated_at->addMinutes($backoffMinutes);
    }

    /**
     * Get error details
     */
    private function getErrorDetails(): ?array
    {
        if (!$this->hasFailed()) {
            return null;
        }

        $jobData = $this->job_data ?? [];

        return [
            'error_class' => $jobData['error_class'] ?? null,
            'error_file' => $jobData['error_file'] ?? null,
            'error_line' => $jobData['error_line'] ?? null,
            'context' => $jobData['error_context'] ?? null,
        ];
    }

    /**
     * Get troubleshooting steps
     */
    private function getTroubleshootingSteps(): array
    {
        $errorType = $this->getErrorType();

        return match ($errorType) {
            'authentication' => [
                'Check calendar connection status',
                'Reconnect your calendar account',
                'Verify calendar permissions',
                'Contact support if issue persists',
            ],
            'rate_limit' => [
                'Wait for rate limit to reset',
                'Reduce sync frequency',
                'Contact support for quota increase',
            ],
            'network' => [
                'Check internet connection',
                'Retry the sync operation',
                'Contact support if issue persists',
            ],
            'permission' => [
                'Check calendar sharing settings',
                'Verify account permissions',
                'Ensure calendar is accessible',
            ],
            default => [
                'Review error details',
                'Try syncing again',
                'Contact support with error details',
            ]
        };
    }

    /**
     * Get job data summary (filtered for display)
     */
    private function getJobDataSummary(): array
    {
        $jobData = $this->job_data ?? [];

        // Filter out sensitive information
        $summary = array_filter($jobData, function ($key) {
            return !in_array($key, ['auth_token', 'api_key', 'password', 'secret']);
        }, ARRAY_FILTER_USE_KEY);

        return $summary;
    }

    /**
     * Get sync direction
     */
    private function getSyncDirection(): string
    {
        $jobData = $this->job_data ?? [];
        return $jobData['sync_direction'] ?? 'bidirectional';
    }

    /**
     * Get date range
     */
    private function getDateRange(): ?array
    {
        $jobData = $this->job_data ?? [];

        if (!isset($jobData['date_from']) || !isset($jobData['date_to'])) {
            return null;
        }

        return [
            'from' => $jobData['date_from'],
            'to' => $jobData['date_to'],
            'days' => Carbon::parse($jobData['date_from'])->diffInDays(Carbon::parse($jobData['date_to'])),
        ];
    }

    /**
     * Get filters applied
     */
    private function getFiltersApplied(): array
    {
        $jobData = $this->job_data ?? [];
        return $jobData['filters'] ?? [];
    }

    /**
     * Get priority level
     */
    private function getPriorityLevel(): string
    {
        $jobData = $this->job_data ?? [];
        return $jobData['priority'] ?? 'normal';
    }

    /**
     * Get batch size
     */
    private function getBatchSize(): int
    {
        $jobData = $this->job_data ?? [];
        return $jobData['batch_size'] ?? 50;
    }

    /**
     * Check if dry run mode
     */
    private function isDryRun(): bool
    {
        $jobData = $this->job_data ?? [];
        return $jobData['dry_run'] ?? false;
    }

    /**
     * Check if force sync
     */
    private function isForceSync(): bool
    {
        $jobData = $this->job_data ?? [];
        return $jobData['force_sync'] ?? false;
    }

    /**
     * Calculate success rate
     */
    private function getSuccessRate(): ?float
    {
        $total = $this->events_processed;

        if ($total === 0) {
            return null;
        }

        $failed = $this->getEventsFailed();
        $successful = $total - $failed;

        return round(($successful / $total) * 100, 1);
    }

    /**
     * Calculate performance score
     */
    private function getPerformanceScore(): ?int
    {
        if (!$this->isCompleted()) {
            return null;
        }

        $score = 100;

        // Deduct for errors
        $errorRate = (100 - ($this->getSuccessRate() ?? 100));
        $score -= $errorRate;

        // Deduct for slow performance
        $eventsPerSecond = $this->getEventsPerSecond() ?? 0;
        if ($eventsPerSecond < 1) {
            $score -= 20;
        } elseif ($eventsPerSecond < 5) {
            $score -= 10;
        }

        // Deduct for long duration
        $duration = $this->getDurationSeconds() ?? 0;
        if ($duration > 300) { // 5 minutes
            $score -= 15;
        }

        return max(0, $score);
    }

    /**
     * Calculate efficiency rating
     */
    private function getEfficiencyRating(): string
    {
        $score = $this->getPerformanceScore();

        if ($score === null) {
            return 'pending';
        }

        return match (true) {
            $score >= 90 => 'excellent',
            $score >= 75 => 'good',
            $score >= 60 => 'average',
            $score >= 40 => 'poor',
            default => 'critical'
        };
    }

    /**
     * Status flag methods
     */
    private function isPending(): bool
    {
        return $this->status === 'pending';
    }

    private function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    private function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    private function hasFailed(): bool
    {
        return $this->status === 'failed';
    }

    private function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    private function isStuck(): bool
    {
        if (!$this->isProcessing()) {
            return false;
        }

        // Consider stuck if processing for more than 1 hour
        return $this->started_at && $this->started_at->lt(now()->subHour());
    }

    private function isRetrying(): bool
    {
        return $this->status === 'retrying' ||
            ($this->hasFailed() && $this->isRetryable() && $this->getRetryCount() < $this->getMaxRetries());
    }

    private function requiresAttention(): bool
    {
        return $this->hasFailed() ||
            $this->isStuck() ||
            ($this->isCompleted() && $this->getSuccessRate() < 90);
    }

    /**
     * Permission methods
     */
    private function canRetry($user): bool
    {
        return $user && $this->hasFailed() && $this->isRetryable() && (
                $user->hasPermission('retry_calendar_sync') ||
                ($user->id === $this->calendarIntegration->user_id && $user->hasPermission('retry_own_calendar_sync'))
            );
    }

    private function canCancel($user): bool
    {
        return $user && ($this->isPending() || $this->isProcessing()) && (
                $user->hasPermission('cancel_calendar_sync') ||
                ($user->id === $this->calendarIntegration->user_id && $user->hasPermission('cancel_own_calendar_sync'))
            );
    }

    private function canViewDetails($user): bool
    {
        return $user && (
                $user->hasPermission('view_calendar_sync_details') ||
                ($user->id === $this->calendarIntegration->user_id && $user->hasPermission('view_own_calendar_sync'))
            );
    }

    private function canViewLogs($user): bool
    {
        return $user && (
                $user->hasPermission('view_calendar_sync_logs') ||
                $user->hasRole('super_admin')
            );
    }

    private function canDownloadReport($user): bool
    {
        return $user && $this->isCompleted() && (
                $user->hasPermission('download_sync_reports') ||
                $user->hasRole('admin')
            );
    }

    /**
     * Get available actions for user
     */
    private function getAvailableActions($user): array
    {
        $actions = [];

        if ($this->canRetry($user)) {
            $actions[] = 'retry';
        }

        if ($this->canCancel($user)) {
            $actions[] = 'cancel';
        }

        if ($this->canViewDetails($user)) {
            $actions[] = 'view_details';
        }

        if ($this->canViewLogs($user)) {
            $actions[] = 'view_logs';
        }

        if ($this->canDownloadReport($user)) {
            $actions[] = 'download_report';
        }

        return $actions;
    }

    /**
     * Display helper methods
     */
    private function getStatusColor(): string
    {
        return match ($this->status) {
            'pending' => 'gray',
            'processing' => 'blue',
            'completed' => 'green',
            'failed' => 'red',
            'cancelled' => 'orange',
            'retrying' => 'yellow',
            default => 'gray'
        };
    }

    private function getStatusIcon(): string
    {
        return match ($this->status) {
            'pending' => 'clock',
            'processing' => 'refresh-cw',
            'completed' => 'check-circle',
            'failed' => 'x-circle',
            'cancelled' => 'stop-circle',
            'retrying' => 'rotate-ccw',
            default => 'help-circle'
        };
    }

    private function getProgressBarColor(): string
    {
        $percentage = $this->getProgressPercentage();

        if ($percentage < 25) {
            return 'red';
        } elseif ($percentage < 75) {
            return 'yellow';
        } else {
            return 'green';
        }
    }

    private function getUrgencyLevel(): string
    {
        if ($this->isStuck() || ($this->hasFailed() && !$this->isRetryable())) {
            return 'high';
        }

        if ($this->hasFailed() || $this->requiresAttention()) {
            return 'medium';
        }

        return 'low';
    }

    private function getSummaryText(): string
    {
        if ($this->isCompleted()) {
            return "Processed {$this->events_processed} events in {$this->getFormattedDuration()}";
        }

        if ($this->isProcessing()) {
            $percentage = $this->getProgressPercentage();
            return "Processing... {$percentage}% complete ({$this->events_processed} events)";
        }

        if ($this->hasFailed()) {
            return "Failed: {$this->error_message}";
        }

        return $this->getStatusDisplay();
    }

    private function getDetailText(): string
    {
        $parts = [];

        $parts[] = $this->getJobTypeDisplay();
        $parts[] = $this->getProviderDisplayName();

        if ($this->isCompleted()) {
            $parts[] = "Success Rate: " . ($this->getSuccessRate() ?? 0) . "%";
        }

        if ($this->hasFailed()) {
            $parts[] = "Error: " . $this->getErrorType();
        }

        return implode(' • ', $parts);
    }

    private function getTooltipText(): string
    {
        $lines = [];

        $lines[] = $this->getJobTypeDisplay();
        $lines[] = "Status: " . $this->getStatusDisplay();

        if ($this->isProcessing()) {
            $lines[] = "Progress: " . $this->getProgressPercentage() . "%";
            $lines[] = "Events: {$this->events_processed}/" . ($this->getEventsTotal() ?? '?');
        }

        if ($this->isCompleted()) {
            $lines[] = "Duration: " . ($this->getFormattedDuration() ?? 'Unknown');
            $lines[] = "Events: {$this->events_processed}";
        }

        if ($this->hasFailed()) {
            $lines[] = "Error: " . ($this->error_message ?? 'Unknown error');
        }

        return implode("\n", $lines);
    }

    /**
     * Helper methods for data that might not be directly stored
     */
    private function getMemoryUsageMb(): ?float
    {
        $jobData = $this->job_data ?? [];
        return isset($jobData['memory_usage_bytes']) ?
            round($jobData['memory_usage_bytes'] / (1024 * 1024), 2) : null;
    }

    private function getCpuTimeSeconds(): ?float
    {
        $jobData = $this->job_data ?? [];
        return $jobData['cpu_time_seconds'] ?? null;
    }

    private function getNetworkLatencyMs(): ?int
    {
        $jobData = $this->job_data ?? [];
        return $jobData['network_latency_ms'] ?? null;
    }

    private function getApiQuotaUsage(): ?array
    {
        $jobData = $this->job_data ?? [];
        return $jobData['api_quota_usage'] ?? null;
    }

    private function getParentJobId(): ?int
    {
        $jobData = $this->job_data ?? [];
        return $jobData['parent_job_id'] ?? null;
    }

    private function hasChildJobs(): bool
    {
        // This would check if there are related child jobs
        return false; // Placeholder
    }

    private function getChildJobsSummary(): array
    {
        // This would return summary of child jobs
        return []; // Placeholder
    }

    private function hasRelatedBookings(): bool
    {
        return $this->job_type === 'sync_bookings';
    }

    private function getRelatedBookingsSummary(): array
    {
        if (!$this->hasRelatedBookings()) {
            return [];
        }

        $jobData = $this->job_data ?? [];
        return [
            'booking_ids' => $jobData['booking_ids'] ?? [],
            'bookings_synced' => $jobData['bookings_synced'] ?? 0,
            'bookings_failed' => $jobData['bookings_failed'] ?? 0,
        ];
    }

    private function getTriggeredBy(): ?array
    {
        $jobData = $this->job_data ?? [];

        if (isset($jobData['triggered_by'])) {
            return $jobData['triggered_by'];
        }

        // Try to determine from context
        if (isset($jobData['manual_trigger'])) {
            return [
                'type' => 'manual',
                'user_id' => $jobData['triggered_by_user_id'] ?? null,
            ];
        }

        if (isset($jobData['webhook_trigger'])) {
            return [
                'type' => 'webhook',
                'source' => $jobData['webhook_source'] ?? 'external',
            ];
        }

        return [
            'type' => 'scheduled',
            'schedule' => 'automatic',
        ];
    }

    private function getCompletionNotificationsSent(): array
    {
        $jobData = $this->job_data ?? [];
        return $jobData['completion_notifications_sent'] ?? [];
    }

    private function getErrorNotificationsSent(): array
    {
        $jobData = $this->job_data ?? [];
        return $jobData['error_notifications_sent'] ?? [];
    }

    private function shouldNotifyOnCompletion(): bool
    {
        $jobData = $this->job_data ?? [];
        return $jobData['notify_on_completion'] ?? false;
    }

    private function getNotificationPreferences(): array
    {
        $jobData = $this->job_data ?? [];
        return $jobData['notification_preferences'] ?? [
            'notify_on_completion' => false,
            'notify_on_error' => true,
            'notify_on_conflicts' => true,
        ];
    }

    /**
     * Debug information (admin only)
     */
    private function shouldIncludeDebugInfo($request): bool
    {
        $user = $request->user();
        return $user && (
                $user->hasRole('super_admin') ||
                $user->hasPermission('view_sync_debug_info')
            );
    }

    private function getJobClass(): ?string
    {
        $jobData = $this->job_data ?? [];
        return $jobData['job_class'] ?? null;
    }

    private function getQueueName(): ?string
    {
        $jobData = $this->job_data ?? [];
        return $jobData['queue_name'] ?? 'default';
    }

    private function getJobPayload(): ?array
    {
        $jobData = $this->job_data ?? [];
        return $jobData['job_payload'] ?? null;
    }

    private function getExceptionTrace(): ?string
    {
        if (!$this->hasFailed()) {
            return null;
        }

        $jobData = $this->job_data ?? [];
        return $jobData['exception_trace'] ?? null;
    }

    private function getServerInfo(): ?array
    {
        $jobData = $this->job_data ?? [];
        return $jobData['server_info'] ?? null;
    }

    private function getLaravelJobId(): ?string
    {
        $jobData = $this->job_data ?? [];
        return $jobData['laravel_job_id'] ?? null;
    }

    /**
     * Format time interval in human-readable format
     */
    private function formatTimeInterval(int $seconds): string
    {
        if ($seconds < 60) {
            return "{$seconds}s";
        }

        $minutes = floor($seconds / 60);
        $remainingSeconds = $seconds % 60;

        if ($minutes < 60) {
            return $remainingSeconds > 0 ? "{$minutes}m {$remainingSeconds}s" : "{$minutes}m";
        }

        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;

        if ($hours < 24) {
            return $remainingMinutes > 0 ? "{$hours}h {$remainingMinutes}m" : "{$hours}h";
        }

        $days = floor($hours / 24);
        $remainingHours = $hours % 24;

        return $remainingHours > 0 ? "{$days}d {$remainingHours}h" : "{$days}d";
    }
}
