<?php

namespace App\Requests\V1;

class StoreServicePackageBookingRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('create_own_bookings');
    }

    public function rules(): array
    {
        return [
            'service_package_id' => 'required|exists:service_packages,id',
            'service_location_id' => 'nullable|exists:service_locations,id',
            'scheduled_at' => 'required|date|after:now',
            'selected_optional_services' => 'nullable|array',
            'selected_optional_services.*' => 'exists:services,id',
            'client_name' => 'required|string|max:255',
            'client_email' => 'required|email|max:255',
            'client_phone' => 'nullable|string|max:20',
            'notes' => 'nullable|string|max:1000',
            'special_requirements' => 'nullable|string|max:500',
            'metadata' => 'nullable|array',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->has('service_package_id') && $this->has('selected_optional_services')) {
                $package = \App\Models\ServicePackage::find($this->service_package_id);

                if ($package) {
                    $optionalServiceIds = $package->services()
                        ->wherePivot('is_optional', true)
                        ->pluck('services.id')
                        ->toArray();

                    foreach ($this->selected_optional_services as $index => $serviceId) {
                        if (!in_array($serviceId, $optionalServiceIds)) {
                            $validator->errors()->add("selected_optional_services.{$index}", 'Invalid optional service for this package.');
                        }
                    }
                }
            }

            // Check package availability
            if ($this->has('service_package_id') && $this->has('scheduled_at')) {
                $package = \App\Models\ServicePackage::find($this->service_package_id);
                $scheduledAt = Carbon::parse($this->scheduled_at);

                if ($package && !$package->canBeBookedOn($scheduledAt)) {
                    $validator->errors()->add('scheduled_at', 'This package is not available for booking at the selected time.');
                }
            }
        });
    }

    protected function prepareForValidation(): void
    {
        // Set default client info from authenticated user if not provided
        if (!$this->has('client_name') && $this->user()) {
            $this->merge(['client_name' => $this->user()->name]);
        }

        if (!$this->has('client_email') && $this->user()) {
            $this->merge(['client_email' => $this->user()->email]);
        }
    }
}
