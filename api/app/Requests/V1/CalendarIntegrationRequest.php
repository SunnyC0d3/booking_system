<?php

namespace App\Requests\V1;

class CalendarIntegrationRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('manage_calendar_integrations');
    }

    public function rules(): array
    {
        return \App\Models\CalendarIntegration::getValidationRules();
    }

    public function messages(): array
    {
        return [
            'provider.required' => 'Please select a calendar provider.',
            'provider.in' => 'Invalid calendar provider selected.',
            'calendar_id.required' => 'Calendar ID is required.',
        ];
    }
}
