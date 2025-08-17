<?php

namespace App\Requests\V1;

class StoreConsultationRequest extends BaseFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->hasPermission('create_consultations');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'service_id' => 'required|exists:services,id',
            'main_booking_id' => 'nullable|exists:bookings,id',
            'scheduled_at' => 'required|date|after:now',
            'duration_minutes' => 'nullable|integer|min:15|max:180',
            'type' => 'required|in:pre_booking,design,planning,technical,follow_up',
            'format' => 'required|in:phone,video,in_person,site_visit',

            // Client information
            'client_name' => 'required|string|max:255',
            'client_email' => 'required|email|max:255',
            'client_phone' => 'nullable|string|max:20',

            // Consultation content
            'consultation_notes' => 'nullable|string|max:2000',
            'preparation_instructions' => 'nullable|string|max:2000',
            'consultation_questions' => 'nullable|array',
            'consultation_questions.*' => 'string|max:500',

            // Meeting details (format-specific)
            'meeting_location' => 'nullable|string|max:500',
            'meeting_instructions' => 'nullable|array',
            'preferred_platform' => 'nullable|string|in:zoom,teams,google_meet,phone',

            // Scheduling preferences
            'timezone' => 'nullable|string|max:50',
            'language_preference' => 'nullable|string|max:10',
            'accessibility_requirements' => 'nullable|string|max:500',

            // Additional options
            'send_reminders' => 'boolean',
            'allow_recording' => 'boolean',
            'include_main_booking_details' => 'boolean',

            // Metadata
            'metadata' => 'nullable|array',
            'priority_level' => 'nullable|in:low,normal,high,urgent',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'service_id.required' => 'Please select a service for the consultation.',
            'service_id.exists' => 'The selected service is not available.',
            'main_booking_id.exists' => 'The selected booking does not exist.',
            'scheduled_at.required' => 'Please select a consultation time.',
            'scheduled_at.after' => 'Consultation time must be in the future.',
            'duration_minutes.min' => 'Consultation duration must be at least 15 minutes.',
            'duration_minutes.max' => 'Consultation duration cannot exceed 3 hours.',
            'type.required' => 'Please select a consultation type.',
            'type.in' => 'Invalid consultation type selected.',
            'format.required' => 'Please select a consultation format.',
            'format.in' => 'Invalid consultation format selected.',
            'client_name.required' => 'Client name is required.',
            'client_email.required' => 'Client email is required.',
            'client_email.email' => 'Please provide a valid email address.',
            'consultation_questions.*.max' => 'Each question cannot exceed 500 characters.',
            'preferred_platform.in' => 'Invalid platform selected.',
            'priority_level.in' => 'Invalid priority level selected.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validate service consultation requirements
            $this->validateServiceConsultationRequirements($validator);

            // Validate consultation timing
            $this->validateConsultationTiming($validator);

            // Validate format-specific requirements
            $this->validateFormatSpecificRequirements($validator);

            // Validate main booking relationship
            $this->validateMainBookingRelationship($validator);

            // Validate business hours and availability
            $this->validateBusinessHours($validator);

            // Validate consultant availability
            $this->validateConsultantAvailability($validator);

            // Validate client booking limits
            $this->validateClientBookingLimits($validator);
        });
    }

    /**
     * Validate service consultation requirements
     */
    private function validateServiceConsultationRequirements($validator): void
    {
        $serviceId = $this->input('service_id');
        $service = \App\Models\Service::find($serviceId);

        if (!$service) {
            return; // Will be caught by exists rule
        }

        // Check if service supports consultations
        if (!$service->requires_consultation && !$service->supports_consultation) {
            $validator->errors()->add('service_id', 'This service does not support consultations.');
        }

        // Validate duration against service limits
        $durationMinutes = $this->input('duration_minutes', $service->consultation_duration_minutes ?? 30);
        $serviceDuration = $service->consultation_duration_minutes ?? 60;

        if ($durationMinutes > $serviceDuration * 2) {
            $validator->errors()->add('duration_minutes',
                "Consultation duration cannot exceed twice the service's standard duration ({$serviceDuration} minutes).");
        }

        // Check if service is active and bookable
        if (!$service->is_active || !$service->is_bookable) {
            $validator->errors()->add('service_id', 'The selected service is not currently available for consultations.');
        }
    }

    /**
     * Validate consultation timing
     */
    private function validateConsultationTiming($validator): void
    {
        $scheduledAt = $this->input('scheduled_at');

        if (!$scheduledAt) {
            return;
        }

        $consultationTime = \Carbon\Carbon::parse($scheduledAt);

        // Check minimum advance booking (2 hours for consultations)
        if ($consultationTime->lt(now()->addHours(2))) {
            $validator->errors()->add('scheduled_at', 'Consultations must be scheduled at least 2 hours in advance.');
        }

        // Check maximum advance booking (90 days)
        if ($consultationTime->gt(now()->addDays(90))) {
            $validator->errors()->add('scheduled_at', 'Consultations cannot be scheduled more than 90 days in advance.');
        }

        // Validate against main booking if provided
        $mainBookingId = $this->input('main_booking_id');
        if ($mainBookingId) {
            $mainBooking = \App\Models\Booking::find($mainBookingId);
            if ($mainBooking && $consultationTime->gte($mainBooking->scheduled_at)) {
                $validator->errors()->add('scheduled_at', 'Consultation must be scheduled before the main service date.');
            }
        }
    }

    /**
     * Validate format-specific requirements
     */
    private function validateFormatSpecificRequirements($validator): void
    {
        $format = $this->input('format');
        $meetingLocation = $this->input('meeting_location');
        $preferredPlatform = $this->input('preferred_platform');

        switch ($format) {
            case 'in_person':
                if (empty($meetingLocation)) {
                    $validator->errors()->add('meeting_location', 'Meeting location is required for in-person consultations.');
                }
                break;

            case 'site_visit':
                if (empty($meetingLocation)) {
                    $validator->errors()->add('meeting_location', 'Site location is required for site visit consultations.');
                }

                // Site visits need longer duration
                $duration = $this->input('duration_minutes', 30);
                if ($duration < 45) {
                    $validator->errors()->add('duration_minutes', 'Site visits typically require at least 45 minutes.');
                }
                break;

            case 'video':
                if ($preferredPlatform && $preferredPlatform === 'phone') {
                    $validator->errors()->add('preferred_platform', 'Phone platform is not compatible with video format.');
                }
                break;

            case 'phone':
                if ($preferredPlatform && !in_array($preferredPlatform, ['phone', 'zoom'])) {
                    $validator->errors()->add('preferred_platform', 'Invalid platform for phone consultations.');
                }
                break;
        }
    }

    /**
     * Validate main booking relationship
     */
    private function validateMainBookingRelationship($validator): void
    {
        $mainBookingId = $this->input('main_booking_id');
        $serviceId = $this->input('service_id');

        if ($mainBookingId && $serviceId) {
            $mainBooking = \App\Models\Booking::find($mainBookingId);

            if ($mainBooking) {
                // Check if user owns the booking
                if ($mainBooking->user_id !== $this->user()->id) {
                    $validator->errors()->add('main_booking_id', 'You can only create consultations for your own bookings.');
                }

                // Check if services match
                if ($mainBooking->service_id !== $serviceId) {
                    $validator->errors()->add('service_id', 'Service must match the main booking service.');
                }

                // Check if booking is in valid status for consultation
                if (!in_array($mainBooking->status, ['pending', 'confirmed'])) {
                    $validator->errors()->add('main_booking_id', 'Consultations can only be created for pending or confirmed bookings.');
                }

                // Check if consultation already exists for this booking
                $existingConsultation = \App\Models\ConsultationBooking::where('main_booking_id', $mainBookingId)
                    ->where('type', $this->input('type'))
                    ->whereIn('status', ['scheduled', 'in_progress'])
                    ->exists();

                if ($existingConsultation) {
                    $validator->errors()->add('main_booking_id', 'A consultation of this type already exists for this booking.');
                }
            }
        }
    }

    /**
     * Validate business hours
     */
    private function validateBusinessHours($validator): void
    {
        $scheduledAt = $this->input('scheduled_at');

        if (!$scheduledAt) {
            return;
        }

        $consultationTime = \Carbon\Carbon::parse($scheduledAt);
        $dayOfWeek = $consultationTime->dayOfWeek;
        $hour = $consultationTime->hour;

        // Check if it's a weekend (consultations only on weekdays)
        if ($dayOfWeek === 0 || $dayOfWeek === 6) { // Sunday or Saturday
            $validator->errors()->add('scheduled_at', 'Consultations are only available Monday through Friday.');
        }

        // Check business hours (9 AM to 6 PM)
        if ($hour < 9 || $hour >= 18) {
            $validator->errors()->add('scheduled_at', 'Consultations are only available between 9:00 AM and 6:00 PM.');
        }

        // Special handling for lunch break (12:00-13:00)
        if ($hour === 12) {
            $validator->errors()->add('scheduled_at', 'Consultations are not available during lunch hour (12:00-1:00 PM). Please select a different time.');
        }
    }

    /**
     * Validate consultant availability
     */
    private function validateConsultantAvailability($validator): void
    {
        $scheduledAt = $this->input('scheduled_at');
        $durationMinutes = $this->input('duration_minutes', 30);

        if (!$scheduledAt) {
            return;
        }

        $consultationTime = \Carbon\Carbon::parse($scheduledAt);
        $endTime = $consultationTime->clone()->addMinutes($durationMinutes);

        // Check for conflicting consultations (simplified check)
        $conflictingConsultations = \App\Models\ConsultationBooking::where('status', 'scheduled')
            ->where(function ($query) use ($consultationTime, $endTime) {
                $query->where(function ($q) use ($consultationTime, $endTime) {
                    $q->where('scheduled_at', '<', $endTime)
                        ->where('ends_at', '>', $consultationTime);
                });
            })
            ->count();

        // Allow max 3 concurrent consultations
        if ($conflictingConsultations >= 3) {
            $validator->errors()->add('scheduled_at', 'This time slot is fully booked. Please select a different time.');
        }
    }

    /**
     * Validate client booking limits
     */
    private function validateClientBookingLimits($validator): void
    {
        $user = $this->user();

        // Check daily consultation limit (max 3 per day)
        $consultationDate = \Carbon\Carbon::parse($this->input('scheduled_at'))->toDateString();
        $dailyConsultations = \App\Models\ConsultationBooking::where('user_id', $user->id)
            ->whereDate('scheduled_at', $consultationDate)
            ->whereIn('status', ['scheduled', 'in_progress'])
            ->count();

        if ($dailyConsultations >= 3) {
            $validator->errors()->add('scheduled_at', 'You can only book up to 3 consultations per day.');
        }

        // Check pending consultations limit (max 5 pending)
        $pendingConsultations = \App\Models\ConsultationBooking::where('user_id', $user->id)
            ->where('status', 'scheduled')
            ->where('scheduled_at', '>', now())
            ->count();

        if ($pendingConsultations >= 5) {
            $validator->errors()->add('scheduled_at', 'You can only have 5 pending consultations at a time.');
        }
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set default values
        $this->mergeIfMissing([
            'send_reminders' => true,
            'allow_recording' => false,
            'include_main_booking_details' => true,
            'priority_level' => 'normal',
        ]);

        // Set client info from user if not provided
        $user = $this->user();
        if (!$this->has('client_name')) {
            $this->merge(['client_name' => $user->name]);
        }
        if (!$this->has('client_email')) {
            $this->merge(['client_email' => $user->email]);
        }
        if (!$this->has('client_phone') && $user->phone) {
            $this->merge(['client_phone' => $user->phone]);
        }

        // Set default duration based on service if not provided
        if (!$this->has('duration_minutes') && $this->has('service_id')) {
            $service = \App\Models\Service::find($this->input('service_id'));
            if ($service && $service->consultation_duration_minutes) {
                $this->merge(['duration_minutes' => $service->consultation_duration_minutes]);
            }
        }

        // Convert boolean fields
        $booleanFields = ['send_reminders', 'allow_recording', 'include_main_booking_details'];
        foreach ($booleanFields as $field) {
            if ($this->has($field)) {
                $this->merge([$field => $this->boolean($field)]);
            }
        }

        // Ensure timezone is set
        if (!$this->has('timezone')) {
            $this->merge(['timezone' => config('app.timezone', 'UTC')]);
        }
    }
}
