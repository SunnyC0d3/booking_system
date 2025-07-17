<?php

namespace App\Requests\V1;

class StoreShippingRateRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'shipping_method_id' => ['required', 'integer', 'exists:shipping_methods,id'],
            'shipping_zone_id' => ['required', 'integer', 'exists:shipping_zones,id'],
            'min_weight' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'max_weight' => ['nullable', 'numeric', 'min:0', 'max:999999.99', 'gte:min_weight'],
            'min_total' => ['required', 'numeric', 'min:0'],
            'max_total' => ['nullable', 'numeric', 'min:0', 'gte:min_total'],
            'rate' => ['required', 'numeric', 'min:0'],
            'free_threshold' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'shipping_method_id.required' => 'Shipping method is required.',
            'shipping_method_id.exists' => 'Selected shipping method does not exist.',
            'shipping_zone_id.required' => 'Shipping zone is required.',
            'shipping_zone_id.exists' => 'Selected shipping zone does not exist.',
            'min_weight.required' => 'Minimum weight is required.',
            'min_weight.min' => 'Minimum weight cannot be negative.',
            'min_weight.max' => 'Minimum weight cannot exceed 999,999.99 kg.',
            'max_weight.gte' => 'Maximum weight must be greater than or equal to minimum weight.',
            'max_weight.max' => 'Maximum weight cannot exceed 999,999.99 kg.',
            'min_total.required' => 'Minimum order total is required.',
            'min_total.min' => 'Minimum order total cannot be negative.',
            'max_total.gte' => 'Maximum order total must be greater than or equal to minimum total.',
            'rate.required' => 'Shipping rate is required.',
            'rate.min' => 'Shipping rate cannot be negative.',
            'free_threshold.min' => 'Free shipping threshold cannot be negative.',
        ];
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        if ($this->has('min_total')) {
            $this->merge(['min_total' => (int) round($this->input('min_total') * 100)]);
        }

        if ($this->has('max_total') && $this->input('max_total') !== null) {
            $this->merge(['max_total' => (int) round($this->input('max_total') * 100)]);
        }

        if ($this->has('rate')) {
            $this->merge(['rate' => (int) round($this->input('rate') * 100)]);
        }

        if ($this->has('free_threshold') && $this->input('free_threshold') !== null) {
            $this->merge(['free_threshold' => (int) round($this->input('free_threshold') * 100)]);
        }

        $this->merge([
            'is_active' => $this->boolean('is_active', true),
        ]);
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $methodId = $this->input('shipping_method_id');
            $zoneId = $this->input('shipping_zone_id');
            $minWeight = $this->input('min_weight');
            $maxWeight = $this->input('max_weight');
            $minTotal = $this->input('min_total');
            $maxTotal = $this->input('max_total');

            if ($methodId && $zoneId) {
                $existingRate = \App\Models\ShippingRate::where('shipping_method_id', $methodId)
                    ->where('shipping_zone_id', $zoneId)
                    ->where(function ($query) use ($minWeight, $maxWeight) {
                        $query->where(function ($q) use ($minWeight, $maxWeight) {
                            $q->where('min_weight', '<=', $minWeight)
                                ->where(function ($subQ) use ($minWeight) {
                                    $subQ->where('max_weight', '>=', $minWeight)
                                        ->orWhereNull('max_weight');
                                });
                        });
                        if ($maxWeight !== null) {
                            $query->orWhere(function ($q) use ($maxWeight) {
                                $q->where('min_weight', '<=', $maxWeight)
                                    ->where(function ($subQ) use ($maxWeight) {
                                        $subQ->where('max_weight', '>=', $maxWeight)
                                            ->orWhereNull('max_weight');
                                    });
                            });
                        }
                    })
                    ->where(function ($query) use ($minTotal, $maxTotal) {
                        $query->where(function ($q) use ($minTotal, $maxTotal) {
                            $q->where('min_total', '<=', $minTotal)
                                ->where(function ($subQ) use ($minTotal) {
                                    $subQ->where('max_total', '>=', $minTotal)
                                        ->orWhereNull('max_total');
                                });
                        });
                        if ($maxTotal !== null) {
                            $query->orWhere(function ($q) use ($maxTotal) {
                                $q->where('min_total', '<=', $maxTotal)
                                    ->where(function ($subQ) use ($maxTotal) {
                                        $subQ->where('max_total', '>=', $maxTotal)
                                            ->orWhereNull('max_total');
                                    });
                            });
                        }
                    })
                    ->exists();

                if ($existingRate) {
                    $validator->errors()->add('rate_conflict', 'A shipping rate already exists for this method/zone combination with overlapping weight and total ranges.');
                }
            }
        });
    }
}
