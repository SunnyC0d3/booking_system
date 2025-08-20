<?php

namespace App\Requests\V1;

use App\Constants\CalendarProviders;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class CalendarOAuthRequest extends BaseFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // User must be authenticated to connect calendars
        if (!$this->user()) {
            return false;
        }

        // Check if user has permission to manage calendar integrations
        return $this->user()->hasPermission('manage_calendar_integrations');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'provider' => [
                'required',
                'string',
                Rule::in(CalendarProviders::ALL),
            ],
            'code' => 'required|string|max:500',
            'state' => 'required|string|max:255',
            'scope' => 'nullable|string|max:500',
            'service_id' => 'nullable|exists:services,id',
            'calendar_name' => 'nullable|string|max:100',
            'sync_settings' => 'nullable|array',
            'sync_settings.sync_bookings' => 'boolean',
            'sync_settings.sync_availability' => 'boolean',
            'sync_settings.auto_block_external_events' => 'boolean',
            'sync_settings.include_client_name' => 'boolean',
            'sync_settings.include_location' => 'boolean',
            'sync_settings.calendar_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'sync_settings.reminder_minutes' => 'nullable|array',
            'sync_settings.reminder_minutes.*' => 'integer|min:0|max:40320', // Max 4 weeks
            'sync_settings.sync_frequency' => 'nullable|integer|min:15|max:1440', // 15 minutes to 24 hours
            'sync_settings.max_events_per_sync' => 'nullable|integer|min:10|max:500',
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'provider.required' => 'Calendar provider is required.',
            'provider.in' => 'Invalid calendar provider. Supported providers: ' . implode(', ', CalendarProviders::ALL),
            'code.required' => 'OAuth authorization code is required.',
            'code.max' => 'Authorization code is too long.',
            'state.required' => 'OAuth state parameter is required for security.',
            'state.max' => 'State parameter is too long.',
            'scope.max' => 'OAuth scope parameter is too long.',
            'service_id.exists' => 'The selected service does not exist.',
            'calendar_name.max' => 'Calendar name cannot exceed 100 characters.',
            'sync_settings.sync_bookings' => 'Sync bookings setting must be true or false.',
            'sync_settings.sync_availability' => 'Sync availability setting must be true or false.',
            'sync_settings.auto_block_external_events' => 'Auto-block external events setting must be true or false.',
            'sync_settings.include_client_name' => 'Include client name setting must be true or false.',
            'sync_settings.include_location' => 'Include location setting must be true or false.',
            'sync_settings.calendar_color.regex' => 'Calendar color must be a valid hex color code (e.g., #4285F4).',
            'sync_settings.reminder_minutes.*.integer' => 'Reminder minutes must be a number.',
            'sync_settings.reminder_minutes.*.min' => 'Reminder minutes cannot be negative.',
            'sync_settings.reminder_minutes.*.max' => 'Reminder minutes cannot exceed 4 weeks (40320 minutes).',
            'sync_settings.sync_frequency.min' => 'Sync frequency cannot be less than 15 minutes.',
            'sync_settings.sync_frequency.max' => 'Sync frequency cannot exceed 24 hours (1440 minutes).',
            'sync_settings.max_events_per_sync.min' => 'Must sync at least 10 events per batch.',
            'sync_settings.max_events_per_sync.max' => 'Cannot sync more than 500 events per batch.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $this->validateStateToken($validator);
            $this->validateProviderSpecificRequirements($validator);
            $this->validateUserLimits($validator);
            $this->validateSyncSettings($validator);
        });
    }

    /**
     * Validate OAuth state token for security
     */
    private function validateStateToken($validator): void
    {
        $state = $this->input('state');
        $provider = $this->input('provider');

        if (!$state || !$provider) {
            return;
        }

        // State should contain user ID and provider for security
        $expectedStatePrefix = "user_{$this->user()->id}_{$provider}_";

        if (!str_starts_with($state, $expectedStatePrefix)) {
            $validator->errors()->add('state', 'Invalid OAuth state token. This may indicate a security issue.');

            Log::warning('Invalid OAuth state token detected', [
                'user_id' => $this->user()->id,
                'provider' => $provider,
                'state' => $state,
                'expected_prefix' => $expectedStatePrefix,
                'ip' => $this->ip(),
                'user_agent' => $this->userAgent(),
            ]);
        }

        // State should not be older than 10 minutes
        $stateTimestamp = substr($state, -10);
        if (is_numeric($stateTimestamp)) {
            $stateTime = (int) $stateTimestamp;
            $currentTime = time();

            if ($currentTime - $stateTime > 600) { // 10 minutes
                $validator->errors()->add('state', 'OAuth state token has expired. Please try connecting your calendar again.');

                Log::info('Expired OAuth state token', [
                    'user_id' => $this->user()->id,
                    'provider' => $provider,
                    'state_age_seconds' => $currentTime - $stateTime,
                ]);
            }
        }
    }

    /**
     * Validate provider-specific requirements
     */
    private function validateProviderSpecificRequirements($validator): void
    {
        $provider = $this->input('provider');
        $scope = $this->input('scope');

        switch ($provider) {
            case CalendarProviders::GOOGLE:
                $this->validateGoogleOAuthRequirements($validator, $scope);
                break;

            case CalendarProviders::OUTLOOK:
                $this->validateOutlookOAuthRequirements($validator, $scope);
                break;

            case CalendarProviders::APPLE:
                $this->validateAppleOAuthRequirements($validator, $scope);
                break;

            case CalendarProviders::ICAL:
                $this->validateICalRequirements($validator);
                break;
        }
    }

    /**
     * Validate Google Calendar OAuth requirements
     */
    private function validateGoogleOAuthRequirements($validator, $scope): void
    {
        $requiredScopes = [
            'https://www.googleapis.com/auth/calendar',
            'https://www.googleapis.com/auth/calendar.events'
        ];

        foreach ($requiredScopes as $requiredScope) {
            if (!str_contains($scope ?: '', $requiredScope)) {
                $validator->errors()->add('scope',
                    "Missing required Google Calendar permission: {$requiredScope}. Please grant all requested permissions.");
                break;
            }
        }
    }

    /**
     * Validate Outlook Calendar OAuth requirements
     */
    private function validateOutlookOAuthRequirements($validator, $scope): void
    {
        $requiredScopes = ['https://graph.microsoft.com/calendars.readwrite'];

        foreach ($requiredScopes as $requiredScope) {
            if (!str_contains($scope ?: '', $requiredScope)) {
                $validator->errors()->add('scope',
                    "Missing required Outlook Calendar permission: {$requiredScope}. Please grant all requested permissions.");
                break;
            }
        }
    }

    /**
     * Validate Apple Calendar OAuth requirements
     */
    private function validateAppleOAuthRequirements($validator, $scope): void
    {
        // Apple Calendar uses different authentication method
        // Add specific validation if needed
    }

    /**
     * Validate iCal requirements (no OAuth needed)
     */
    private function validateICalRequirements($validator): void
    {
        // iCal doesn't use OAuth, but might need URL validation
        if ($this->input('code')) {
            // For iCal, 'code' might contain the calendar URL
            if (!filter_var($this->input('code'), FILTER_VALIDATE_URL)) {
                $validator->errors()->add('code', 'Please provide a valid iCal calendar URL.');
            }
        }
    }

    /**
     * Validate user integration limits
     */
    private function validateUserLimits($validator): void
    {
        $user = $this->user();
        $provider = $this->input('provider');
        $serviceId = $this->input('service_id');

        // Check total calendar integrations limit
        $totalIntegrations = $user->calendarIntegrations()->where('is_active', true)->count();
        $maxIntegrations = $this->getMaxIntegrationsForUser($user);

        if ($totalIntegrations >= $maxIntegrations) {
            $validator->errors()->add('provider',
                "You have reached the maximum number of calendar integrations ({$maxIntegrations}). Please remove an existing integration first.");
        }

        // Check for duplicate provider + service combination
        $existingIntegration = $user->calendarIntegrations()
            ->where('provider', $provider)
            ->where('service_id', $serviceId)
            ->where('is_active', true)
            ->exists();

        if ($existingIntegration) {
            $serviceName = $serviceId ? \App\Models\Service::find($serviceId)?->name : 'all services';
            $validator->errors()->add('provider',
                "You already have a {$provider} calendar integration for {$serviceName}.");
        }
    }

    /**
     * Validate sync settings
     */
    private function validateSyncSettings($validator): void
    {
        $syncSettings = $this->input('sync_settings', []);

        // If auto_block_external_events is enabled, sync_availability should be enabled
        if (($syncSettings['auto_block_external_events'] ?? false) &&
            !($syncSettings['sync_availability'] ?? false)) {
            $validator->errors()->add('sync_settings.auto_block_external_events',
                'Auto-blocking external events requires availability sync to be enabled.');
        }

        // Validate reminder minutes array
        $reminderMinutes = $syncSettings['reminder_minutes'] ?? [];
        if (is_array($reminderMinutes) && count($reminderMinutes) > 5) {
            $validator->errors()->add('sync_settings.reminder_minutes',
                'Cannot set more than 5 calendar reminders.');
        }

        // Validate sync frequency against max events
        $frequency = $syncSettings['sync_frequency'] ?? 60;
        $maxEvents = $syncSettings['max_events_per_sync'] ?? 100;

        if ($frequency < 30 && $maxEvents > 200) {
            $validator->errors()->add('sync_settings.max_events_per_sync',
                'High event count with frequent sync may hit API rate limits. Reduce events or increase frequency.');
        }
    }

    /**
     * Get maximum integrations allowed for user
     */
    private function getMaxIntegrationsForUser($user): int
    {
        // Different limits based on user role/plan
        if ($user->hasRole('super_admin') || $user->hasRole('admin')) {
            return 10;
        }

        if ($user->hasRole('vendor')) {
            return 5;
        }

        return 3; // Regular users
    }

    /**
     * Prepare the data for validation
     */
    protected function prepareForValidation(): void
    {
        // Clean and normalize inputs
        $data = [];

        if ($this->has('provider')) {
            $data['provider'] = strtolower(trim($this->input('provider')));
        }

        if ($this->has('calendar_name')) {
            $data['calendar_name'] = trim($this->input('calendar_name'));
        }

        // Normalize sync settings
        if ($this->has('sync_settings')) {
            $syncSettings = $this->input('sync_settings', []);

            // Convert string booleans to actual booleans
            $booleanFields = [
                'sync_bookings', 'sync_availability', 'auto_block_external_events',
                'include_client_name', 'include_location'
            ];

            foreach ($booleanFields as $field) {
                if (isset($syncSettings[$field])) {
                    $syncSettings[$field] = filter_var($syncSettings[$field], FILTER_VALIDATE_BOOLEAN);
                }
            }

            // Clean reminder minutes array
            if (isset($syncSettings['reminder_minutes']) && is_array($syncSettings['reminder_minutes'])) {
                $syncSettings['reminder_minutes'] = array_values(array_unique(array_filter(
                    array_map('intval', $syncSettings['reminder_minutes']),
                    fn($val) => $val >= 0 && $val <= 40320
                )));
            }

            $data['sync_settings'] = $syncSettings;
        }

        $this->merge($data);
    }

    /**
     * Get custom attributes for error messages
     */
    public function attributes(): array
    {
        return [
            'provider' => 'calendar provider',
            'code' => 'authorization code',
            'state' => 'OAuth state',
            'scope' => 'OAuth scope',
            'service_id' => 'service',
            'calendar_name' => 'calendar name',
            'sync_settings.sync_bookings' => 'sync bookings to calendar',
            'sync_settings.sync_availability' => 'sync availability from calendar',
            'sync_settings.auto_block_external_events' => 'auto-block external events',
            'sync_settings.include_client_name' => 'include client name in events',
            'sync_settings.include_location' => 'include location in events',
            'sync_settings.calendar_color' => 'calendar color',
            'sync_settings.reminder_minutes' => 'reminder minutes',
            'sync_settings.sync_frequency' => 'sync frequency',
            'sync_settings.max_events_per_sync' => 'maximum events per sync',
        ];
    }
}
