<?php

namespace App\Requests\V1;

class CreateAvailabilityExceptionRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('manage_service_availability');
    }

    public function rules(): array
    {
        $exceptionType = $this->input('exception_type', 'blocked');

        return \App\Models\ServiceAvailabilityException::getValidationRules($exceptionType);
    }

    public function messages(): array
    {
        return [
            'service_id.required' => 'Please select a service.',
            'exception_date.required' => 'Please select a date.',
            'exception_date.after_or_equal' => 'Exception date cannot be in the past.',
            'exception_type.required' => 'Please select an exception type.',
            'start_time.required' => 'Start time is required for custom hours.',
            'end_time.required' => 'End time is required for custom hours.',
            'end_time.after' => 'End time must be after start time.',
            'price_modifier.required' => 'Price modifier is required for special pricing.',
        ];
    }
}
