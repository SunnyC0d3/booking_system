<?php

namespace App\Requests\V1;

use App\Constants\CalendarProviders;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;

class StoreCalendarIntegrationRequest extends BaseFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        // Check if user has permission to create calendar integrations
        if (!$user || !$user->hasPermission('create_calendar_integrations')) {
            return false;
        }

        // Admin users can create integrations for any user
        if ($user->hasPermission('create_all_calendar_integrations')) {
            return true;
        }

        // Regular users can only create integrations for themselves
        $targetUserId = $this->input('user_id');
        return !$targetUserId || $targetUserId == $user->id;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // Basic integration details
            'user_id' => [
                'sometimes',
                'integer',
                'exists:users,id',
                function ($attribute, $value, $fail) {
                    $user = $this->user();
                    // Only admins can specify different user_id
                    if ($value && $value != $user->id && !$user->hasPermission('create_all_calendar_integrations')) {
                        $fail('You can only create calendar integrations for yourself.');
                    }
                },
            ],
            'service_id' => 'nullable|integer|exists:services,id',
            'provider' => [
                'required',
                'string',
                Rule::in([CalendarProviders::GOOGLE, CalendarProviders::ICAL]),
            ],
            'calendar_id' => 'required|string|max:255',
            'calendar_name' => 'required|string|max:255',

            // OAuth tokens (for admin creation)
            'access_token' => 'sometimes|string|max:2000',
            'refresh_token' => 'sometimes|nullable|string|max:2000',
            'token_expires_at' => 'sometimes|nullable|date|after:now',

            // Integration settings
            'sync_bookings' => 'sometimes|boolean',
            'sync_availability' => 'sometimes|boolean',
            'auto_block_external_events' => 'sometimes|boolean',

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
            'user_id.exists' => 'The selected user does not exist.',
            'service_id.exists' => 'The selected service does not exist.',
            'provider.required' => 'Please select a calendar provider.',
            'provider.in' => 'The selected calendar provider is not supported.',
            'calendar_id.required' => 'Calendar ID is required.',
            'calendar_id.max' => 'Calendar ID cannot exceed 255 characters.',
            'calendar_name.required' => 'Calendar name is required.',
            'calendar_name.max' => 'Calendar name cannot exceed 255 characters.',
            'access_token.max' => 'Access token is too long.',
            'refresh_token.max' => 'Refresh token is too long.',
            'token_expires_at.after' => 'Token expiration must be in the future.',
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
            $this->validateProviderSpecificRules($validator);
            $this->validateServiceAssociation($validator);
            $this->validateSyncSettings($validator);
            $this->validateIntegrationLimits($validator);
            $this->validateCalendarId($validator);
        });
    }

    /**
     * Validate provider-specific rules
     */
    private function validateProviderSpecificRules($validator): void
    {
        $provider = $this->input('provider');

        switch ($provider) {
            case CalendarProviders::GOOGLE:
                $this->validateGoogleCalendarRules($validator);
                break;
            case CalendarProviders::ICAL:
                $this->validateICalRules($validator);
                break;
        }
    }

    /**
     * Validate Google Calendar specific rules
     */
    private function validateGoogleCalendarRules($validator): void
    {
        $calendarId = $this->input('calendar_id');

        // Google calendar IDs should be email-like or "primary"
        if ($calendarId !== 'primary' && !filter_var($calendarId, FILTER_VALIDATE_EMAIL)) {
            $validator->errors()->add('calendar_id', 'Google calendar ID must be "primary" or a valid email address.');
        }

        // For admin creation, tokens should be provided
        $user = $this->user();
        if ($user && $user->hasPermission('create_all_calendar_integrations')) {
            if (!$this->has('access_token')) {
                $validator->errors()->add('access_token', 'Access token is required for Google Calendar integration.');
            }
        }
    }

    /**
     * Validate iCal specific rules
     */
    private function validateICalRules($validator): void
    {
        $calendarId = $this->input('calendar_id');

        // iCal calendar ID should be a valid URL or file path
        if (!filter_var($calendarId, FILTER_VALIDATE_URL) && !str_starts_with($calendarId, '/')) {
            $validator->errors()->add('calendar_id', 'iCal calendar ID must be a valid URL or file path.');
        }

        // iCal doesn't use refresh tokens
        if ($this->has('refresh_token')) {
            $validator->errors()->add('refresh_token', 'iCal integrations do not use refresh tokens.');
        }
    }

    /**
     * Validate service association
     */
    private function validateServiceAssociation($validator): void
    {
        $serviceId = $this->input('service_id');
        $userId = $this->input('user_id') ?? $this->user()->id;

        if ($serviceId) {
            $service = \App\Models\Service::find($serviceId);

            if ($service) {
                // Check if user has access to this service
                $user = \App\Models\User::find($userId);
                if (!$user || (!$user->hasPermission('view_all_services') && $service->user_id !== $user->id)) {
                    $validator->errors()->add('service_id', 'You do not have access to this service.');
                }

                // Check if service supports calendar integration
                if (!$service->is_active || !$service->is_bookable) {
                    $validator->errors()->add('service_id', 'Selected service is not available for calendar integration.');
                }
            }
        }
    }

    /**
     * Validate sync settings
     */
    private function validateSyncSettings($validator): void
    {
        $syncSettings = $this->input('sync_settings', []);

        // Validate reminder minutes array
        if (isset($syncSettings['reminder_minutes'])) {
            $reminderMinutes = $syncSettings['reminder_minutes'];

            if (is_array($reminderMinutes)) {
                // Check for duplicates
                if (count($reminderMinutes) !== count(array_unique($reminderMinutes))) {
                    $validator->errors()->add('sync_settings.reminder_minutes', 'Reminder times must be unique.');
                }

                // Sort and validate logical order
                $sorted = $reminderMinutes;
                sort($sorted);
                if ($sorted !== $reminderMinutes) {
                    // Auto-sort for user convenience
                    $this->merge(['sync_settings' => array_merge($syncSettings, ['reminder_minutes' => $sorted])]);
                }
            }
        }

        // Validate date range logic
        $pastDays = $syncSettings['sync_past_days'] ?? 7;
        $futureDays = $syncSettings['sync_future_days'] ?? 90;

        if ($pastDays + $futureDays > 400) {
            $validator->errors()->add('sync_settings.sync_past_days', 'Total sync range cannot exceed 400 days.');
        }

        // Validate event title template
        if (isset($syncSettings['event_title_template'])) {
            $template = $syncSettings['event_title_template'];
            $allowedPlaceholders = ['{service_name}', '{client_name}', '{booking_ref}', '{duration}'];

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
        }
    }

    /**
     * Validate integration limits
     */
    private function validateIntegrationLimits($validator): void
    {
        $userId = $this->input('user_id') ?? $this->user()->id;
        $provider = $this->input('provider');
        $serviceId = $this->input('service_id');

        // Check total integrations per user
        $userIntegrationCount = \App\Models\CalendarIntegration::where('user_id', $userId)->count();
        $maxIntegrationsPerUser = config('calendar.max_integrations_per_user', 10);

        if ($userIntegrationCount >= $maxIntegrationsPerUser) {
            $validator->errors()->add('user_id', "Maximum {$maxIntegrationsPerUser} calendar integrations allowed per user.");
        }

        // Check for duplicate integrations
        $existingIntegration = \App\Models\CalendarIntegration::where('user_id', $userId)
            ->where('provider', $provider)
            ->where('calendar_id', $this->input('calendar_id'))
            ->when($serviceId, function ($query) use ($serviceId) {
                $query->where('service_id', $serviceId);
            }, function ($query) {
                $query->whereNull('service_id');
            })
            ->first();

        if ($existingIntegration) {
            $validator->errors()->add('calendar_id', 'This calendar is already integrated for the selected service.');
        }

        // Check provider-specific limits
        $providerIntegrationCount = \App\Models\CalendarIntegration::where('user_id', $userId)
            ->where('provider', $provider)
            ->count();

        $maxPerProvider = match ($provider) {
            CalendarProviders::GOOGLE => 5,
            CalendarProviders::ICAL => 10,
            default => 3
        };

        if ($providerIntegrationCount >= $maxPerProvider) {
            $validator->errors()->add('provider', "Maximum {$maxPerProvider} {$provider} integrations allowed per user.");
        }
    }

    /**
     * Validate calendar ID format and accessibility
     */
    private function validateCalendarId($validator): void
    {
        $calendarId = $this->input('calendar_id');
        $provider = $this->input('provider');

        // Basic format validation
        if (strlen($calendarId) > 255) {
            $validator->errors()->add('calendar_id', 'Calendar ID is too long.');
            return;
        }

        // Provider-specific validation
        switch ($provider) {
            case CalendarProviders::ICAL:
                if (filter_var($calendarId, FILTER_VALIDATE_URL)) {
                    // For URLs, check if they're accessible (basic check)
                    $this->validateICalUrl($validator, $calendarId);
                }
                break;
        }
    }

    /**
     * Validate iCal URL accessibility
     */
    private function validateICalUrl($validator, string $url): void
    {
        try {
            // Basic URL validation
            $parsed = parse_url($url);

            if (!$parsed || !isset($parsed['scheme']) || !in_array($parsed['scheme'], ['http', 'https'])) {
                $validator->errors()->add('calendar_id', 'iCal URL must use HTTP or HTTPS protocol.');
                return;
            }

            // Optional: Basic connectivity check (commented out to avoid blocking validation)
            /*
            $context = stream_context_create([
                'http' => [
                    'timeout' => 5,
                    'method' => 'HEAD',
                ]
            ]);

            $headers = @get_headers($url, 0, $context);
            if (!$headers || !str_contains($headers[0], '200')) {
                $validator->errors()->add('calendar_id', 'iCal URL is not accessible.');
            }
            */

        } catch (\Exception $e) {
            Log::warning('iCal URL validation error', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            // Don't fail validation for network issues
        }
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set default user_id to current user if not provided
        if (!$this->has('user_id')) {
            $this->merge(['user_id' => $this->user()->id]);
        }

        // Set default sync settings
        if (!$this->has('sync_settings')) {
            $this->merge(['sync_settings' => $this->getDefaultSyncSettings()]);
        }

        // Clean and normalize inputs
        if ($this->has('calendar_name')) {
            $this->merge(['calendar_name' => trim($this->input('calendar_name'))]);
        }

        if ($this->has('calendar_id')) {
            $this->merge(['calendar_id' => trim($this->input('calendar_id'))]);
        }
    }

    /**
     * Get default sync settings
     */
    private function getDefaultSyncSettings(): array
    {
        return [
            'sync_frequency' => 30, // 30 minutes
            'event_title_template' => '{service_name} - {client_name}',
            'include_client_name' => true,
            'include_location' => true,
            'include_notes' => false,
            'calendar_color' => '#4285F4',
            'reminder_minutes' => [15, 60], // 15 min and 1 hour
            'max_events_per_sync' => 100,
            'sync_past_days' => 7,
            'sync_future_days' => 90,
        ];
    }
}
