<?php

namespace App\Requests\V1;

use App\Constants\CalendarProviders;
use App\Models\CalendarIntegration;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class SyncCalendarRequest extends BaseFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // User must be authenticated
        if (!$this->user()) {
            return false;
        }

        // Check if user has permission to sync calendars
        if (!$this->user()->hasPermission('sync_calendar_integrations')) {
            return false;
        }

        // If calendar_integration_id is provided, ensure user owns it
        $integrationId = $this->input('calendar_integration_id');
        if ($integrationId) {
            $integration = CalendarIntegration::find($integrationId);

            if (!$integration || $integration->user_id !== $this->user()->id) {
                // Unless user has admin permissions
                if (!$this->user()->hasPermission('manage_all_calendar_integrations')) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'calendar_integration_id' => [
                'nullable',
                'integer',
                'exists:calendar_integrations,id'
            ],
            'sync_type' => [
                'required',
                'string',
                Rule::in(['full', 'incremental', 'bookings_only', 'availability_only', 'events_only'])
            ],
            'date_from' => 'nullable|date|before_or_equal:date_to',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'force_sync' => 'boolean',
            'ignore_conflicts' => 'boolean',
            'conflict_resolution' => [
                'nullable',
                'string',
                Rule::in(['keep_external', 'keep_internal', 'merge', 'ask_user'])
            ],
            'sync_direction' => [
                'nullable',
                'string',
                Rule::in(['push', 'pull', 'bidirectional'])
            ],
            'max_events' => 'nullable|integer|min:1|max:1000',
            'priority' => [
                'nullable',
                'string',
                Rule::in(['low', 'normal', 'high', 'urgent'])
            ],
            'notification_settings' => 'nullable|array',
            'notification_settings.notify_on_completion' => 'boolean',
            'notification_settings.notify_on_conflicts' => 'boolean',
            'notification_settings.notify_on_errors' => 'boolean',
            'dry_run' => 'boolean',
            'sync_options' => 'nullable|array',
            'sync_options.include_past_events' => 'boolean',
            'sync_options.include_all_day_events' => 'boolean',
            'sync_options.include_recurring_events' => 'boolean',
            'sync_options.update_existing_events' => 'boolean',
            'sync_options.delete_removed_events' => 'boolean',
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'calendar_integration_id.exists' => 'The selected calendar integration does not exist.',
            'sync_type.required' => 'Sync type is required.',
            'sync_type.in' => 'Invalid sync type. Choose from: full, incremental, bookings_only, availability_only, events_only.',
            'date_from.date' => 'Start date must be a valid date.',
            'date_from.before_or_equal' => 'Start date must be before or equal to end date.',
            'date_to.date' => 'End date must be a valid date.',
            'date_to.after_or_equal' => 'End date must be after or equal to start date.',
            'force_sync.boolean' => 'Force sync must be true or false.',
            'ignore_conflicts.boolean' => 'Ignore conflicts must be true or false.',
            'conflict_resolution.in' => 'Invalid conflict resolution method.',
            'sync_direction.in' => 'Invalid sync direction. Choose from: push, pull, bidirectional.',
            'max_events.integer' => 'Maximum events must be a number.',
            'max_events.min' => 'Must sync at least 1 event.',
            'max_events.max' => 'Cannot sync more than 1000 events at once.',
            'priority.in' => 'Invalid priority level.',
            'dry_run.boolean' => 'Dry run must be true or false.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $this->validateRateLimit($validator);
            $this->validateDateRange($validator);
            $this->validateSyncPermissions($validator);
            $this->validateIntegrationStatus($validator);
            $this->validateSyncConflicts($validator);
            $this->validateProviderLimits($validator);
        });
    }

    /**
     * Validate rate limiting for sync requests
     */
    private function validateRateLimit($validator): void
    {
        $user = $this->user();
        $rateLimitKey = "calendar_sync_user_{$user->id}";

        // Different rate limits based on sync type and user role
        $syncType = $this->input('sync_type');
        $forcSync = $this->input('force_sync', false);

        $limits = $this->getSyncRateLimits($user, $syncType, $forcSync);

        // Check if user has exceeded rate limit
        $attempts = RateLimiter::attempts($rateLimitKey);

        if ($attempts >= $limits['max_attempts']) {
            $availableIn = RateLimiter::availableIn($rateLimitKey);

            $validator->errors()->add('sync_type',
                "Too many sync requests. Please wait {$availableIn} seconds before trying again.");

            Log::warning('Calendar sync rate limit exceeded', [
                'user_id' => $user->id,
                'sync_type' => $syncType,
                'attempts' => $attempts,
                'limit' => $limits['max_attempts'],
                'available_in' => $availableIn,
            ]);
        } else {
            // Increment rate limit counter
            RateLimiter::hit($rateLimitKey, $limits['decay_seconds']);
        }
    }

    /**
     * Validate date range constraints
     */
    private function validateDateRange($validator): void
    {
        $dateFrom = $this->input('date_from');
        $dateTo = $this->input('date_to');
        $syncType = $this->input('sync_type');

        if (!$dateFrom && !$dateTo) {
            // Use default ranges based on sync type
            return;
        }

        if ($dateFrom && $dateTo) {
            $from = Carbon::parse($dateFrom);
            $to = Carbon::parse($dateTo);

            // Check maximum range based on sync type
            $maxDays = $this->getMaxDaysForSyncType($syncType);
            $daysDiff = $from->diffInDays($to);

            if ($daysDiff > $maxDays) {
                $validator->errors()->add('date_to',
                    "Date range too large for {$syncType} sync. Maximum {$maxDays} days allowed.");
            }

            // Warn about large ranges
            if ($daysDiff > 90) {
                Log::info('Large date range requested for calendar sync', [
                    'user_id' => $this->user()->id,
                    'sync_type' => $syncType,
                    'days_diff' => $daysDiff,
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                ]);
            }

            // Check if dates are too far in the past
            if ($from->lt(now()->subYears(2))) {
                $validator->errors()->add('date_from',
                    'Cannot sync events older than 2 years.');
            }

            // Check if dates are too far in the future
            if ($to->gt(now()->addYears(2))) {
                $validator->errors()->add('date_to',
                    'Cannot sync events more than 2 years in the future.');
            }
        }
    }

    /**
     * Validate sync permissions and access
     */
    private function validateSyncPermissions($validator): void
    {
        $user = $this->user();
        $syncType = $this->input('sync_type');
        $forceSync = $this->input('force_sync', false);

        // Force sync requires special permission
        if ($forceSync && !$user->hasPermission('force_calendar_sync')) {
            $validator->errors()->add('force_sync',
                'You do not have permission to force calendar synchronization.');
        }

        // Full sync requires higher permissions
        if ($syncType === 'full' && !$user->hasPermission('full_calendar_sync')) {
            $validator->errors()->add('sync_type',
                'You do not have permission to perform full calendar synchronization.');
        }

        // Check if user can perform bidirectional sync
        $syncDirection = $this->input('sync_direction');
        if ($syncDirection === 'bidirectional' && !$user->hasPermission('bidirectional_calendar_sync')) {
            $validator->errors()->add('sync_direction',
                'You do not have permission to perform bidirectional calendar synchronization.');
        }
    }

    /**
     * Validate calendar integration status
     */
    private function validateIntegrationStatus($validator): void
    {
        $integrationId = $this->input('calendar_integration_id');

        if ($integrationId) {
            $integration = CalendarIntegration::find($integrationId);

            if ($integration) {
                // Check if integration is active
                if (!$integration->is_active) {
                    $validator->errors()->add('calendar_integration_id',
                        'Cannot sync inactive calendar integration.');
                }

                // Check if integration has too many recent errors
                if ($integration->sync_error_count >= 5) {
                    $validator->errors()->add('calendar_integration_id',
                        'Calendar integration has too many errors. Please check connection and try again later.');
                }

                // Check if integration tokens are valid
                if ($integration->token_expires_at && $integration->token_expires_at->isPast()) {
                    $validator->errors()->add('calendar_integration_id',
                        'Calendar integration tokens have expired. Please reconnect your calendar.');
                }

                // Check provider-specific limitations
                $this->validateProviderSpecificLimits($validator, $integration);
            }
        }
    }

    /**
     * Validate sync conflict settings
     */
    private function validateSyncConflicts($validator): void
    {
        $conflictResolution = $this->input('conflict_resolution');
        $ignoreConflicts = $this->input('ignore_conflicts', false);

        // If ignore_conflicts is true, conflict_resolution should not be set
        if ($ignoreConflicts && $conflictResolution) {
            $validator->errors()->add('conflict_resolution',
                'Cannot specify conflict resolution when ignoring conflicts.');
        }

        // Some conflict resolution methods require additional permissions
        if ($conflictResolution === 'keep_external' && !$this->user()->hasPermission('override_booking_conflicts')) {
            $validator->errors()->add('conflict_resolution',
                'You do not have permission to keep external events over internal bookings.');
        }
    }

    /**
     * Validate provider-specific limits
     */
    private function validateProviderLimits($validator): void
    {
        $integrationId = $this->input('calendar_integration_id');
        $maxEvents = $this->input('max_events');

        if ($integrationId && $maxEvents) {
            $integration = CalendarIntegration::find($integrationId);

            if ($integration) {
                $providerLimits = $this->getProviderLimits($integration->provider);

                if ($maxEvents > $providerLimits['max_events_per_sync']) {
                    $validator->errors()->add('max_events',
                        "Maximum {$providerLimits['max_events_per_sync']} events allowed per sync for {$integration->provider}.");
                }
            }
        }
    }

    /**
     * Validate provider-specific limitations
     */
    private function validateProviderSpecificLimits($validator, CalendarIntegration $integration): void
    {
        $syncType = $this->input('sync_type');
        $syncDirection = $this->input('sync_direction');

        switch ($integration->provider) {
            case CalendarProviders::GOOGLE:
                // Google has API quotas
                if ($syncType === 'full' && $this->getRecentSyncCount($integration) >= 5) {
                    $validator->errors()->add('sync_type',
                        'Too many full syncs with Google Calendar today. Please try incremental sync.');
                }
                break;

            case CalendarProviders::OUTLOOK:
                // Outlook has different rate limits
                if ($syncDirection === 'bidirectional' && !$integration->sync_settings_display['advanced_sync'] ?? false) {
                    $validator->errors()->add('sync_direction',
                        'Bidirectional sync requires advanced sync settings for Outlook.');
                }
                break;

            case CalendarProviders::ICAL:
                // iCal is read-only
                if ($syncDirection === 'push' || $syncDirection === 'bidirectional') {
                    $validator->errors()->add('sync_direction',
                        'iCal calendars are read-only. Only pull synchronization is supported.');
                }
                break;
        }
    }

    /**
     * Get sync rate limits based on user and sync type
     */
    private function getSyncRateLimits($user, string $syncType, bool $forceSync): array
    {
        $baseLimits = [
            'full' => ['max_attempts' => 3, 'decay_seconds' => 3600], // 3 per hour
            'incremental' => ['max_attempts' => 10, 'decay_seconds' => 3600], // 10 per hour
            'bookings_only' => ['max_attempts' => 20, 'decay_seconds' => 3600], // 20 per hour
            'availability_only' => ['max_attempts' => 15, 'decay_seconds' => 3600], // 15 per hour
            'events_only' => ['max_attempts' => 25, 'decay_seconds' => 3600], // 25 per hour
        ];

        $limits = $baseLimits[$syncType] ?? $baseLimits['incremental'];

        // Adjust limits based on user role
        if ($user->hasRole('super_admin') || $user->hasRole('admin')) {
            $limits['max_attempts'] *= 3;
        } elseif ($user->hasRole('vendor')) {
            $limits['max_attempts'] *= 2;
        }

        // Force sync reduces limits
        if ($forceSync) {
            $limits['max_attempts'] = max(1, intval($limits['max_attempts'] / 2));
        }

        return $limits;
    }

    /**
     * Get maximum days allowed for each sync type
     */
    private function getMaxDaysForSyncType(string $syncType): int
    {
        return match ($syncType) {
            'full' => 365, // 1 year
            'incremental' => 90, // 3 months
            'bookings_only' => 180, // 6 months
            'availability_only' => 60, // 2 months
            'events_only' => 30, // 1 month
            default => 90
        };
    }

    /**
     * Get provider-specific limits
     */
    private function getProviderLimits(string $provider): array
    {
        return match ($provider) {
            CalendarProviders::GOOGLE => [
                'max_events_per_sync' => 500,
                'max_api_calls_per_minute' => 100,
            ],
            CalendarProviders::OUTLOOK => [
                'max_events_per_sync' => 300,
                'max_api_calls_per_minute' => 60,
            ],
            CalendarProviders::APPLE => [
                'max_events_per_sync' => 200,
                'max_api_calls_per_minute' => 30,
            ],
            CalendarProviders::ICAL => [
                'max_events_per_sync' => 1000,
                'max_api_calls_per_minute' => 10,
            ],
            default => [
                'max_events_per_sync' => 100,
                'max_api_calls_per_minute' => 30,
            ]
        };
    }

    /**
     * Get recent sync count for integration
     */
    private function getRecentSyncCount(CalendarIntegration $integration): int
    {
        return $integration->calendarSyncJobs()
            ->where('created_at', '>=', now()->subDay())
            ->where('job_type', 'sync_events')
            ->count();
    }

    /**
     * Prepare the data for validation
     */
    protected function prepareForValidation(): void
    {
        $data = [];

        // Set defaults for sync type
        if (!$this->has('sync_type')) {
            $data['sync_type'] = 'incremental';
        }

        // Set default date range if not provided
        if (!$this->has('date_from') && !$this->has('date_to')) {
            $syncType = $this->input('sync_type', 'incremental');

            switch ($syncType) {
                case 'full':
                    $data['date_from'] = now()->subMonths(6)->toDateString();
                    $data['date_to'] = now()->addMonths(6)->toDateString();
                    break;
                case 'incremental':
                    $data['date_from'] = now()->subWeeks(2)->toDateString();
                    $data['date_to'] = now()->addMonths(3)->toDateString();
                    break;
                default:
                    $data['date_from'] = now()->subWeek()->toDateString();
                    $data['date_to'] = now()->addMonth()->toDateString();
                    break;
            }
        }

        // Set default priority
        if (!$this->has('priority')) {
            $data['priority'] = 'normal';
        }

        // Set default sync direction
        if (!$this->has('sync_direction')) {
            $data['sync_direction'] = 'bidirectional';
        }

        // Convert string booleans to actual booleans
        $booleanFields = [
            'force_sync', 'ignore_conflicts', 'dry_run'
        ];

        foreach ($booleanFields as $field) {
            if ($this->has($field)) {
                $data[$field] = filter_var($this->input($field), FILTER_VALIDATE_BOOLEAN);
            }
        }

        // Handle notification settings
        if ($this->has('notification_settings')) {
            $notificationSettings = $this->input('notification_settings', []);

            $notificationBooleans = [
                'notify_on_completion', 'notify_on_conflicts', 'notify_on_errors'
            ];

            foreach ($notificationBooleans as $field) {
                if (isset($notificationSettings[$field])) {
                    $notificationSettings[$field] = filter_var($notificationSettings[$field], FILTER_VALIDATE_BOOLEAN);
                }
            }

            $data['notification_settings'] = $notificationSettings;
        }

        // Handle sync options
        if ($this->has('sync_options')) {
            $syncOptions = $this->input('sync_options', []);

            $syncBooleans = [
                'include_past_events', 'include_all_day_events', 'include_recurring_events',
                'update_existing_events', 'delete_removed_events'
            ];

            foreach ($syncBooleans as $field) {
                if (isset($syncOptions[$field])) {
                    $syncOptions[$field] = filter_var($syncOptions[$field], FILTER_VALIDATE_BOOLEAN);
                }
            }

            $data['sync_options'] = $syncOptions;
        }

        $this->merge($data);
    }

    /**
     * Get custom attributes for error messages
     */
    public function attributes(): array
    {
        return [
            'calendar_integration_id' => 'calendar integration',
            'sync_type' => 'sync type',
            'date_from' => 'start date',
            'date_to' => 'end date',
            'force_sync' => 'force sync',
            'ignore_conflicts' => 'ignore conflicts',
            'conflict_resolution' => 'conflict resolution',
            'sync_direction' => 'sync direction',
            'max_events' => 'maximum events',
            'priority' => 'priority level',
            'dry_run' => 'dry run mode',
        ];
    }
}
