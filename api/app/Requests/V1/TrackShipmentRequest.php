<?php

namespace App\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class TrackShipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tracking_number' => [
                'required',
                'string',
                'min:8',
                'max:50',
                'regex:/^[A-Z0-9\-]+$/',
            ],
            'carrier' => [
                'nullable',
                'string',
                'max:100',
                'in:royal-mail,dpd,ups,fedex,hermes,parcelforce,dhl',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'tracking_number.required' => 'Tracking number is required.',
            'tracking_number.min' => 'Tracking number must be at least 8 characters.',
            'tracking_number.max' => 'Tracking number cannot exceed 50 characters.',
            'tracking_number.regex' => 'Invalid tracking number format. Only letters, numbers, and hyphens are allowed.',
            'carrier.in' => 'Invalid carrier specified.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('tracking_number')) {
            $this->merge([
                'tracking_number' => strtoupper(trim($this->input('tracking_number')))
            ]);
        }

        if ($this->has('carrier')) {
            $this->merge([
                'carrier' => strtolower(trim($this->input('carrier')))
            ]);
        }
    }
}
