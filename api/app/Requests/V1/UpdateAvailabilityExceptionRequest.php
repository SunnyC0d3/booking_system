<?php

namespace App\Requests\V1;

class UpdateAvailabilityExceptionRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('manage_service_availability');
    }

    public function rules(): array
    {
        $exception = $this->route('exception');
        $exceptionType = $this->input('exception_type', $exception->exception_type);

        return \App\Models\ServiceAvailabilityException::getValidationRules($exceptionType);
    }
}
