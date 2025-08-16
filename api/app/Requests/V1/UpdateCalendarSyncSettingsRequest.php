<?php

namespace App\Requests\V1;

use App\Models\CalendarIntegration;

class UpdateCalendarSyncSettingsRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        $integration = $this->route('integration');

        return $this->user()->hasPermission('manage_calendar_integrations') &&
            $integration->user_id === $this->user()->id;
    }

    public function rules(): array
    {
        return CalendarIntegration::getSyncSettingsValidationRules();
    }

    public function messages(): array
    {
        return [
            'sync_frequency.min' => 'Sync frequency must be at least 5 minutes.',
            'sync_frequency.max' => 'Sync frequency cannot exceed 24 hours.',
            'calendar_color.regex' => 'Calendar color must be a valid hex color code.',
            'reminder_minutes.*.max' => 'Reminder cannot be more than 1 week in advance.',
        ];
    }
}
