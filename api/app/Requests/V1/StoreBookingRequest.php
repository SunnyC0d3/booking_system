<?php

namespace App\Requests\V1;

class StoreBookingRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('create_own_bookings');
    }

    public function rules(): array
    {
        return [
            'service_id' => 'required|exists:services,id',
            'service_package_id' => 'nullable|exists:service_packages,id',
            'service_location_id' => 'nullable|exists:service_locations,id',
            'scheduled_at' => 'required|date|after:now',
            'duration_minutes' => 'nullable|integer|min:15|max:480',
            'client_name' => 'required|string|max:255',
            'client_email' => 'required|email|max:255',
            'client_phone' => 'nullable|string|max:20',
            'notes' => 'nullable|string|max:1000',
            'special_requirements' => 'nullable|string|max:500',
            'requires_consultation' => 'boolean',
            'add_ons' => 'nullable|array',
            'add_ons.*.service_add_on_id' => 'required|exists:service_add_ons,id',
            'add_ons.*.quantity' => 'required|integer|min:1|max:10',
            'optional_services' => 'nullable|array',
            'optional_services.*' => 'exists:services,id',
            'metadata' => 'nullable|array',
        ];
    }

    public function messages(): array
    {
        return [
            'service_id.required' => 'Please select a service.',
            'service_id.exists' => 'The selected service is not available.',
            'scheduled_at.required' => 'Please select a booking time.',
            'scheduled_at.after' => 'Booking time must be in the future.',
            'client_name.required' => 'Client name is required.',
            'client_email.required' => 'Client email is required.',
            'client_email.email' => 'Please provide a valid email address.',
            'duration_minutes.min' => 'Booking duration must be at least 15 minutes.',
            'duration_minutes.max' => 'Booking duration cannot exceed 8 hours.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->has('scheduled_at')) {
                $scheduledAt = \Carbon\Carbon::parse($this->scheduled_at);

                if ($scheduledAt->gt(now()->addYear())) {
                    $validator->errors()->add('scheduled_at', 'Bookings cannot be made more than 1 year in advance.');
                }

                if ($scheduledAt->lt(now()->addHours(2))) {
                    $validator->errors()->add('scheduled_at', 'Bookings must be made at least 2 hours in advance.');
                }
            }

            if ($this->has('service_id') && $this->has('service_package_id')) {
                $validator->errors()->add('service_package_id', 'Cannot book both individual service and package.');
            }

            if ($this->has('add_ons') && $this->has('service_id')) {
                $serviceId = $this->service_id;
                $validAddOnIds = \App\Models\ServiceAddOn::where('service_id', $serviceId)
                    ->where('is_active', true)
                    ->pluck('id')
                    ->toArray();

                foreach ($this->add_ons as $index => $addOn) {
                    if (!in_array($addOn['service_add_on_id'], $validAddOnIds)) {
                        $validator->errors()->add("add_ons.{$index}.service_add_on_id", 'Invalid add-on for this service.');
                    }
                }
            }
        });
    }

    protected function prepareForValidation(): void
    {
        if (!$this->has('client_name') && $this->user()) {
            $this->merge(['client_name' => $this->user()->name]);
        }

        if (!$this->has('client_email') && $this->user()) {
            $this->merge(['client_email' => $this->user()->email]);
        }
    }
}
