<?php

namespace App\Requests\V1;

class BookingAvailabilityRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'service_id' => 'required_without:service_package_id|exists:services,id',
            'service_package_id' => 'required_without:service_id|exists:service_packages,id',
            'service_location_id' => 'nullable|exists:service_locations,id',
            'date' => 'required|date|after_or_equal:today',
            'duration_minutes' => 'nullable|integer|min:15|max:480',
            'days_ahead' => 'nullable|integer|min:1|max:90',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (!$this->has('days_ahead')) {
            $this->merge(['days_ahead' => 7]);
        }
    }
}
