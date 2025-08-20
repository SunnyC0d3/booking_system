<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class CalendarSyncJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'calendar_integration_id',
        'job_type',
        'status',
        'started_at',
        'completed_at',
        'events_processed',
        'error_message',
        'job_data',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'events_processed' => 'integer',
        'job_data' => 'array',
    ];

    protected $dates = [
        'started_at',
        'completed_at',
    ];

    // Relationships

    /**
     * Get the calendar integration that owns this sync job
     */
    public function calendarIntegration(): BelongsTo
    {
        return $this->belongsTo(CalendarIntegration::class);
    }

    // Scopes

    /**
     * Scope to filter jobs by status
     */
    public function scopeWithStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to filter pending jobs
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope to filter processing jobs
     */
    public function scopeProcessing(Builder $query): Builder
    {
        return $query->where('status', 'processing');
    }

    /**
     * Scope to filter completed jobs
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope to filter failed jobs
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope to filter jobs by type
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('job_type', $type);
    }

    /**
     * Scope to filter jobs within date range
     */
    public function scopeInDateRange(Builder $query, Carbon $startDate, Carbon $endDate): Builder
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Scope to filter recent jobs
     */
    public function scopeRecent(Builder $query, int $hours = 24): Builder
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }

    /**
     * Scope to filter stuck jobs (processing for too long)
     */
    public function scopeStuck(Builder $query, int $hours = 1): Builder
    {
        return $query->where('status', 'processing')
            ->where('started_at', '<', now()->subHours($hours));
    }

    /**
     * Scope to filter jobs with errors
     */
    public function scopeWithErrors(Builder $query): Builder
    {
        return $query->where('status', 'failed')
            ->whereNotNull('error_message');
    }

    /**
     * Scope to filter successful jobs
     */
    public function scopeSuccessful(Builder $query): Builder
    {
        return $query->where('status', 'completed')
            ->whereNull('error_message');
    }

    /**
     * Scope to filter jobs for a specific integration
     */
    public function scopeForIntegration(Builder $query, int $integrationId): Builder
    {
        return $query->where('calendar_integration_id', $integrationId);
    }

    /**
     * Scope to filter webhook jobs
     */
    public function scopeWebhookJobs(Builder $query): Builder
    {
        return $query->where('job_type', 'webhook_sync')
            ->orWhere('job_type', 'like', '%webhook%');
    }

    /**
     * Scope to filter manual jobs
     */
    public function scopeManualJobs(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->whereJsonContains('job_data->triggered_by->type', 'manual')
                ->orWhereJsonContains('job_data->manual_trigger', true);
        });
    }

    /**
     * Scope to filter automatic jobs
     */
    public function scopeAutomaticJobs(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->whereJsonContains('job_data->triggered_by->type', 'scheduled')
                ->orWhereJsonContains('job_data->auto_triggered', true);
        });
    }

    // Accessor Methods

    /**
     * Get the job duration in seconds
     */
    public function getDurationSecondsAttribute(): ?int
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
    public function getFormattedDurationAttribute(): ?string
    {
        $seconds = $this->duration_seconds;

        if ($seconds === null) {
            return null;
        }

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

        return $remainingMinutes > 0 ? "{$hours}h {$remainingMinutes}m" : "{$hours}h";
    }

    /**
     * Get job type display name
     */
    public function getJobTypeDisplayAttribute(): string
    {
        return match ($this->job_type) {
            'sync_bookings' => 'Sync Bookings to Calendar',
            'sync_availability' => 'Sync Availability from Calendar',
            'sync_events' => 'Sync All Calendar Events',
            'webhook_sync' => 'Real-time Calendar Update',
            'full_sync' => 'Full Calendar Synchronization',
            'incremental_sync' => 'Incremental Calendar Sync',
            default => ucfirst(str_replace('_', ' ', $this->job_type))
        };
    }

    /**
     * Get status display name
     */
    public function getStatusDisplayAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'Waiting to Start',
            'processing' => 'In Progress',
            'completed' => 'Completed Successfully',
            'failed' => 'Failed',
            default => ucfirst($this->status)
        };
    }

    /**
     * Get progress percentage
     */
    public function getProgressPercentageAttribute(): float
    {
        $total = $this->getJobDataValue('events_total');

        if (!$total || $total === 0) {
            return $this->isCompleted() ? 100.0 : 0.0;
        }

        return min(100.0, ($this->events_processed / $total) * 100);
    }

    /**
     * Get events per second processing rate
     */
    public function getEventsPerSecondAttribute(): ?float
    {
        $duration = $this->duration_seconds;

        if (!$duration || $duration === 0) {
            return null;
        }

        return round($this->events_processed / $duration, 2);
    }

    /**
     * Get success rate for this job
     */
    public function getSuccessRateAttribute(): ?float
    {
        $total = $this->events_processed;

        if ($total === 0) {
            return null;
        }

        $failed = $this->getJobDataValue('events_failed', 0);
        $successful = $total - $failed;

        return round(($successful / $total) * 100, 1);
    }

    /**
     * Get estimated completion time
     */
    public function getEstimatedCompletionAttribute(): ?Carbon
    {
        if (!$this->isProcessing() || !$this->started_at) {
            return null;
        }

        $progressPercentage = $this->progress_percentage;
        if ($progressPercentage <= 0) {
            return null;
        }

        $elapsedSeconds = $this->started_at->diffInSeconds(now());
        $totalEstimatedSeconds = ($elapsedSeconds / $progressPercentage) * 100;
        $remainingSeconds = $totalEstimatedSeconds - $elapsedSeconds;

        return now()->addSeconds($remainingSeconds);
    }

    // Status Check Methods

    /**
     * Check if job is pending
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if job is processing
     */
    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    /**
     * Check if job is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if job has failed
     */
    public function hasFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Check if job is stuck (processing for too long)
     */
    public function isStuck(int $hours = 1): bool
    {
        return $this->isProcessing() &&
            $this->started_at &&
            $this->started_at->lt(now()->subHours($hours));
    }

    /**
     * Check if job was successful
     */
    public function wasSuccessful(): bool
    {
        return $this->isCompleted() && empty($this->error_message);
    }

    /**
     * Check if job was triggered manually
     */
    public function wasTriggeredManually(): bool
    {
        $triggeredBy = $this->getJobDataValue('triggered_by');
        return isset($triggeredBy['type']) && $triggeredBy['type'] === 'manual';
    }

    /**
     * Check if job was triggered automatically
     */
    public function wasTriggeredAutomatically(): bool
    {
        $triggeredBy = $this->getJobDataValue('triggered_by');
        return isset($triggeredBy['type']) &&
            in_array($triggeredBy['type'], ['scheduled', 'webhook']);
    }

    /**
     * Check if this is a webhook job
     */
    public function isWebhookJob(): bool
    {
        return str_contains($this->job_type, 'webhook') ||
            $this->getJobDataValue('webhook_trigger', false);
    }

    // Job Data Helper Methods

    /**
     * Get a value from job_data array
     */
    public function getJobDataValue(string $key, $default = null)
    {
        $data = $this->job_data ?? [];
        return data_get($data, $key, $default);
    }

    /**
     * Set a value in job_data array
     */
    public function setJobDataValue(string $key, $value): void
    {
        $data = $this->job_data ?? [];
        data_set($data, $key, $value);
        $this->update(['job_data' => $data]);
    }

    /**
     * Append to job_data array
     */
    public function appendJobData(array $newData): void
    {
        $data = array_merge($this->job_data ?? [], $newData);
        $this->update(['job_data' => $data]);
    }

    // Job Lifecycle Methods

    /**
     * Mark job as started
     */
    public function markAsStarted(): void
    {
        $this->update([
            'status' => 'processing',
            'started_at' => now(),
        ]);
    }

    /**
     * Mark job as completed
     */
    public function markAsCompleted(array $additionalData = []): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
            'job_data' => array_merge($this->job_data ?? [], $additionalData),
        ]);
    }

    /**
     * Mark job as failed
     */
    public function markAsFailed(string $errorMessage, array $additionalData = []): void
    {
        $this->update([
            'status' => 'failed',
            'completed_at' => now(),
            'error_message' => $errorMessage,
            'job_data' => array_merge($this->job_data ?? [], $additionalData),
        ]);
    }

    /**
     * Update progress
     */
    public function updateProgress(int $eventsProcessed, array $additionalData = []): void
    {
        $this->update([
            'events_processed' => $eventsProcessed,
            'job_data' => array_merge($this->job_data ?? [], $additionalData),
        ]);
    }

    // Error Analysis Methods

    /**
     * Get error type classification
     */
    public function getErrorType(): ?string
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

        if (str_contains($message, 'conflict') || str_contains($message, 'overlap')) {
            return 'conflict';
        }

        return 'unknown';
    }
}
