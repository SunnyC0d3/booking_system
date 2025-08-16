<?php

namespace App\Requests\V1;

class CompleteConsultationRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        $booking = $this->route('booking');

        return $this->user()->hasPermission('manage_consultations') ||
            $booking->user_id === $this->user()->id;
    }

    public function rules(): array
    {
        return [
            'consultation_notes' => 'required|string|max:2000',
            'proceed_with_booking' => 'required|boolean',
            'recommended_services' => 'nullable|array',
            'recommended_services.*' => 'exists:services,id',
            'estimated_duration' => 'nullable|integer|min:15|max:480',
            'consultation_completed_at' => 'nullable|date',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $booking = $this->route('booking');

// Check if consultation is required and not already completed
            if (!$booking->requires_consultation) {
                $validator->errors()->add('consultation', 'This booking does not require a consultation.');
            }

            if ($booking->consultation_completed_at) {
                $validator->errors()->add('consultation', 'Consultation has already been completed.');
            }
        });
    }
}
