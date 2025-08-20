<?php

namespace App\Requests\V1;

use Illuminate\Support\Facades\Log;

class UpdateCalendarIntegrationRequest extends BaseFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $integration = $this->route('integration');

        if (!$user) {
            return false;
        }

        // Admin users can update any integration
        if ($user->hasPermission('edit_all_calendar_integrations')) {
            return true;
        }

        // Regular users can only update their own integrations
        if ($user->hasPermission('edit_own_calendar_integrations') && $integration) {
            return $integration->user_id === $user->id;
        }

        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // Integration settings (all optional for updates)
            'calendar_name' => 'sometimes|string|max:255',
            'sync_bookings' => 'sometimes|boolean',
            'sync_availability' => 'sometimes|boolean',
            'auto_block_external_events' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',

            // Admin-only fields
            'access_token' => 'sometimes|string|max:2000',
            'refresh_token' => 'sometimes|nullable|string|max:2000',
            'token_expires_at' => 'sometimes|nullable|date',

            // Sync configuration
            'sync_settings' => 'sometimes|array',
            'sync_settings.sync_frequency' => 'sometimes|integer|min:5|max:1440', // 5 minutes to 24 hours
            'sync_settings.event_title_template' => 'sometimes|string|max:255',
            'sync_settings.include_client_name' => 'sometimes|boolean',
            'sync_settings.include_location' => 'sometimes|boolean',
            'sync_settings.include_notes' => 'sometimes|boolean',
            'sync_settings.calendar_color' => 'sometimes|string|regex:/^#[a-fA-F0-9]{6}$/',
            'sync_settings.reminder_minutes' => 'sometimes|array|max:5',
            'sync_settings.reminder_minutes.*' => 'integer|min:0|max:10080', // Up to 1 week
            'sync_settings.max_events_per_sync' => 'sometimes|integer|min:10|max:500',
            'sync_settings.sync_past_days' => 'sometimes|integer|min:0|max:90',
            'sync_settings.sync_future_days' => 'sometimes|integer|min:1|max:365',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'calendar_name.max' => 'Calendar name cannot exceed 255 characters.',
            'access_token.max' => 'Access token is too long.',
            'refresh_token.max' => 'Refresh token is too long.',
            'token_expires_at.date' => 'Token expiration must be a valid date.',
            'sync_settings.sync_frequency.min' => 'Sync frequency must be at least 5 minutes.',
            'sync_settings.sync_frequency.max' => 'Sync frequency cannot exceed 24 hours.',
            'sync_settings.event_title_template.max' => 'Event title template cannot exceed 255 characters.',
            'sync_settings.calendar_color.regex' => 'Calendar color must be a valid hex color (e.g., #FF0000).',
            'sync_settings.reminder_minutes.max' => 'You can set a maximum of 5 reminder times.',
            'sync_settings.reminder_minutes.*.min' => 'Reminder time cannot be negative.',
            'sync_settings.reminder_minutes.*.max' => 'Reminder time cannot exceed 1 week (10080 minutes).',
            'sync_settings.max_events_per_sync.min' => 'Must sync at least 10 events per operation.',
            'sync_settings.max_events_per_sync.max' => 'Cannot sync more than 500 events per operation.',
            'sync_settings.sync_past_days.min' => 'Sync past days cannot be negative.',
            'sync_settings.sync_past_days.max' => 'Cannot sync more than 90 days in the past.',
            'sync_settings.sync_future_days.min' => 'Must sync at least 1 day in the future.',
            'sync_settings.sync_future_days.max' => 'Cannot sync more than 365 days in the future.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $this->validateAdminOnlyFields($validator);
            $this->validateSyncSettings($validator);
            $this->validateTokenUpdates($validator);
            $this->validateIntegrationState($validator);
            $this->validateSyncDependencies($validator);
        });
    }

    /**
     * Validate admin-only fields
     */
    private function validateAdminOnlyFields($validator): void
    {
        $user = $this->user();
        $adminOnlyFields = ['access_token', 'refresh_token', 'token_expires_at'];

        foreach ($adminOnlyFields as $field) {
            if ($this->has($field) && !$user->hasPermission('edit_all_calendar_integrations')) {
                $validator->errors()->add($field, 'Only administrators can update this field.');
            }
        }
    }

    /**
     * Validate sync settings
     */
    private function validateSyncSettings($validator): void
    {
        if (!$this->has('sync_settings')) {
            return;
        }

        $syncSettings = $this->input('sync_settings');
        $integration = $this->route('integration');

        // Validate reminder minutes array
        if (isset($syncSettings['reminder_minutes'])) {
            $reminderMinutes = $syncSettings['reminder_minutes'];

            if (is_array($reminderMinutes)) {
                // Check for duplicates
                if (count($reminderMinutes) !== count(array_unique($reminderMinutes))) {
                    $validator->errors()->add('sync_settings.reminder_minutes', 'Reminder times must be unique.');
                }

                // Auto-sort for consistency
                $sorted = $reminderMinutes;
                sort($sorted);
                if ($sorted !== $reminderMinutes) {
                    // Merge sorted values back
                    $this->merge([
                        'sync_settings' => array_merge($syncSettings, ['reminder_minutes' => $sorted])
                    ]);
                }
            }
        }

        // Validate date range logic
        if (isset($syncSettings['sync_past_days']) || isset($syncSettings['sync_future_days'])) {
            $currentSettings = $integration->sync_settings_display ?? [];
            $pastDays = $syncSettings['sync_past_days'] ?? $currentSettings['sync_past_days'] ?? 7;
            $futureDays = $syncSettings['sync_future_days'] ?? $currentSettings['sync_future_days'] ?? 90;

            if ($pastDays + $futureDays > 400) {
                $validator->errors()->add('sync_settings.sync_past_days', 'Total sync range cannot exceed 400 days.');
            }
        }

        // Validate event title template
        if (isset($syncSettings['event_title_template'])) {
            $this->validateEventTitleTemplate($validator, $syncSettings['event_title_template']);
        }

        // Validate sync frequency against provider limits
        if (isset($syncSettings['sync_frequency'])) {
            $this->validateSyncFrequency($validator, $syncSettings['sync_frequency'], $integration);
        }
    }

    /**
     * Validate event title template
     */
    private function validateEventTitleTemplate($validator, string $template): void
    {
        $allowedPlaceholders = [
            '{service_name}',
            '{client_name}',
            '{booking_ref}',
            '{duration}',
            '{date}',
            '{time}',
            '{location}'
        ];

        // Check for unknown placeholders
        preg_match_all('/\{([^}]+)\}/', $template, $matches);
        if (!empty($matches[0])) {
            $unknownPlaceholders = array_diff($matches[0], $allowedPlaceholders);
            if (!empty($unknownPlaceholders)) {
                $validator->errors()->add('sync_settings.event_title_template',
                    'Unknown placeholders: ' . implode(', ', $unknownPlaceholders) .
                    '. Allowed: ' . implode(', ', $allowedPlaceholders));
            }
        }

        // Ensure template is not empty after processing
        $testResult = str_replace($allowedPlaceholders, '', $template);
        if (trim($testResult) === '' && !str_contains($template, '{service_name}')) {
            $validator->errors()->add('sync_settings.event_title_template',
                'Event title template must contain some static text or {service_name} placeholder.');
        }
    }

    /**
     * Validate sync frequency against provider limits
     */
    private function validateSyncFrequency($validator, int $frequency, $integration): void
    {
        $minFrequencies = [
            'google' => 15, // Google has rate limits
            'ical' => 5,    // iCal can be more frequent
        ];

        $minFrequency = $minFrequencies[$integration->provider] ?? 15;

        if ($frequency < $minFrequency) {
            $validator->errors()->add('sync_settings.sync_frequency',
                "Sync frequency for {$integration->provider} must be at least {$minFrequency} minutes.");
        }
    }

    /**
     * Validate token updates
     */
    private function validateTokenUpdates($validator): void
    {
        $integration = $this->route('integration');

        // If updating tokens, ensure they're valid for the provider
        if ($this->has('access_token')) {
            $this->validateTokenFormat($validator, $integration);
        }

        // If setting expiration, ensure it's reasonable
        if ($this->has('token_expires_at')) {
            $expiresAt = $this->input('token_expires_at');
            if ($expiresAt) {
                $expirationTime = \Carbon\Carbon::parse($expiresAt);

                // Token shouldn't expire too soon
                if ($expirationTime->lt(now()->addMinutes(30))) {
                    $validator->errors()->add('token_expires_at', 'Token expiration should be at least 30 minutes in the future.');
                }

                // Token shouldn't expire too far in future
                if ($expirationTime->gt(now()->addYear())) {
                    $validator->errors()->add('token_expires_at', 'Token expiration cannot be more than 1 year in the future.');
                }
            }
        }
    }

    /**
     * Validate token format for provider
     */
    private function validateTokenFormat($validator, $integration): void
    {
        $accessToken = $this->input('access_token');

        switch ($integration->provider) {
            case 'google':
                // Google tokens are usually JWT or start with ya29
                if (!str_starts_with($accessToken, 'ya29.') && !$this->isJwtToken($accessToken)) {
                    $validator->errors()->add('access_token', 'Access token format appears invalid for Google Calendar.');
                }
                break;

            case 'ical':
                // iCal uses URL-based tokens, more flexible validation
                if (strlen($accessToken) < 10) {
                    $validator->errors()->add('access_token', 'Access token appears too short for iCal integration.');
                }
                break;
        }
    }

    /**
     * Check if token is in JWT format
     */
    private function isJwtToken(string $token): bool
    {
        $parts = explode('.', $token);
        return count($parts) === 3;
    }

    /**
     * Validate integration state transitions
     */
    private function validateIntegrationState($validator): void
    {
        $integration = $this->route('integration');

        // If disabling integration, warn about active bookings
        if ($this->has('is_active') && !$this->input('is_active') && $integration->is_active) {
            $activeBookingsCount = $integration->user->bookings()
                ->whereIn('status', ['pending', 'confirmed'])
                ->where('scheduled_at', '>', now())
                ->count();

            if ($activeBookingsCount > 0) {
                // Don't fail validation, but add a warning
                Log::info('Calendar integration being disabled with active bookings', [
                    'integration_id' => $integration->id,
                    'active_bookings_count' => $activeBookingsCount,
                ]);
            }
        }

        // If enabling a previously failed integration, reset error count
        if ($this->has('is_active') && $this->input('is_active') && !$integration->is_active) {
            // This will be handled in the service layer, but we can validate prerequisites
            if ($integration->sync_error_count > 10) {
                $validator->errors()->add('is_active', 'Cannot reactivate integration with high error count. Please recreate the integration.');
            }
        }
    }

    /**
     * Validate sync setting dependencies
     */
    private function validateSyncDependencies($validator): void
    {
        $syncSettings = $this->input('sync_settings', []);
        $integration = $this->route('integration');
        $currentSettings = $integration->sync_settings_display ?? [];

        // If auto_block_external_events is enabled, sync_availability should be enabled
        $autoBlock = $this->input('auto_block_external_events', $integration->auto_block_external_events);
        $syncAvailability = $this->input('sync_availability', $integration->sync_availability);

        if ($autoBlock && !$syncAvailability) {
            $validator->errors()->add('auto_block_external_events',
                'Auto-blocking external events requires availability sync to be enabled.');
        }

        // If include_client_name is disabled, warn about privacy
        if (isset($syncSettings['include_client_name']) && !$syncSettings['include_client_name']) {
            Log::info('Client name inclusion disabled for calendar integration', [
                'integration_id' => $integration->id,
                'user_id' => $integration->user_id,
            ]);
        }

        // Validate max_events_per_sync against sync_frequency
        $maxEvents = $syncSettings['max_events_per_sync'] ?? $currentSettings['max_events_per_sync'] ?? 100;
        $frequency = $syncSettings['sync_frequency'] ?? $currentSettings['sync_frequency'] ?? 30;

        if ($frequency < 30 && $maxEvents > 200) {
            $validator->errors()->add('sync_settings.max_events_per_sync',
                'High event count with frequent sync may hit API rate limits. Reduce events or increase frequency.');
        }
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Clean and normalize inputs
        if ($this->has('calendar_name')) {
            $this->merge(['calendar_name' => trim($this->input('calendar_name'))]);
        }

        // Merge sync_settings with existing settings for partial updates
        if ($this->has('sync_settings')) {
            $integration = $this->route('integration');
            $currentSettings = $integration->sync_settings_display ?? [];
            $newSettings = $this->input('sync_settings');

            // Merge with existing settings
            $mergedSettings = array_merge($currentSettings, $newSettings);
            $this->merge(['sync_settings' => $mergedSettings]);
        }
    }

    /**
     * Get only the fields that should be updated
     */
    public function getUpdateData(): array
    {
        $updateData = [];

        // Basic fields
        $basicFields = [
            'calendar_name', 'sync_bookings', 'sync_availability',
            'auto_block_external_events', 'is_active'
        ];

        foreach ($basicFields as $field) {
            if ($this->has($field)) {
                $updateData[$field] = $this->input($field);
            }
        }

        // Admin-only fields
        if ($this->user()->hasPermission('edit_all_calendar_integrations')) {
            $adminFields = ['access_token', 'refresh_token', 'token_expires_at'];
            foreach ($adminFields as $field) {
                if ($this->has($field)) {
                    $updateData[$field] = $this->input($field);
                }
            }
        }

        // Sync settings
        if ($this->has('sync_settings')) {
            $updateData['sync_settings'] = $this->input('sync_settings');
        }

        return $updateData;
    }
}
