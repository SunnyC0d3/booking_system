<?php

namespace App\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreDropshipOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('create_dropship_orders');
    }

    public function rules(): array
    {
        return [
            'order_id' => 'required|exists:orders,id',
            'supplier_id' => 'required|exists:suppliers,id',
            'total_cost' => 'required|integer|min:1',
            'total_retail' => 'required|integer|min:1',
            'shipping_address' => 'required|array',
            'shipping_address.name' => 'required|string|max:255',
            'shipping_address.line1' => 'required|string|max:255',
            'shipping_address.line2' => 'nullable|string|max:255',
            'shipping_address.city' => 'required|string|max:255',
            'shipping_address.county' => 'nullable|string|max:255',
            'shipping_address.postcode' => 'required|string|max:20',
            'shipping_address.country' => 'required|string|size:2',
            'shipping_address.phone' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:2000',
            'auto_retry_enabled' => 'boolean',
            'items' => 'required|array|min:1',
            'items.*.order_item_id' => 'required|exists:order_items,id',
            'items.*.supplier_product_id' => 'required|exists:supplier_products,id',
            'items.*.supplier_sku' => 'required|string|max:255',
            'items.*.quantity' => 'required|integer|min:1|max:1000',
            'items.*.supplier_price' => 'required|integer|min:1',
            'items.*.retail_price' => 'required|integer|min:1',
            'items.*.product_details' => 'nullable|array',
        ];
    }

    public function messages(): array
    {
        return [
            'order_id.required' => 'Order is required.',
            'order_id.exists' => 'Selected order does not exist.',
            'supplier_id.required' => 'Supplier is required.',
            'supplier_id.exists' => 'Selected supplier does not exist.',
            'total_cost.required' => 'Total cost is required.',
            'total_cost.min' => 'Total cost must be at least 1 penny.',
            'total_retail.required' => 'Total retail price is required.',
            'total_retail.min' => 'Total retail price must be at least 1 penny.',
            'shipping_address.required' => 'Shipping address is required.',
            'shipping_address.name.required' => 'Recipient name is required.',
            'shipping_address.line1.required' => 'Address line 1 is required.',
            'shipping_address.city.required' => 'City is required.',
            'shipping_address.postcode.required' => 'Postcode is required.',
            'shipping_address.country.required' => 'Country is required.',
            'shipping_address.country.size' => 'Country code must be exactly 2 characters.',
            'items.required' => 'At least one item is required.',
            'items.min' => 'At least one item is required.',
            'items.*.order_item_id.required' => 'Order item ID is required for each item.',
            'items.*.order_item_id.exists' => 'One or more order items do not exist.',
            'items.*.supplier_product_id.required' => 'Supplier product ID is required for each item.',
            'items.*.supplier_product_id.exists' => 'One or more supplier products do not exist.',
            'items.*.supplier_sku.required' => 'Supplier SKU is required for each item.',
            'items.*.quantity.required' => 'Quantity is required for each item.',
            'items.*.quantity.min' => 'Quantity must be at least 1 for each item.',
            'items.*.quantity.max' => 'Quantity cannot exceed 1000 for each item.',
            'items.*.supplier_price.required' => 'Supplier price is required for each item.',
            'items.*.supplier_price.min' => 'Supplier price must be at least 1 penny for each item.',
            'items.*.retail_price.required' => 'Retail price is required for each item.',
            'items.*.retail_price.min' => 'Retail price must be at least 1 penny for each item.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'auto_retry_enabled' => $this->boolean('auto_retry_enabled', true),
        ]);

        if ($this->has('total_cost') && is_numeric($this->input('total_cost'))) {
            $this->merge([
                'total_cost' => (int) round($this->input('total_cost') * 100)
            ]);
        }

        if ($this->has('total_retail') && is_numeric($this->input('total_retail'))) {
            $this->merge([
                'total_retail' => (int) round($this->input('total_retail') * 100)
            ]);
        }

        if ($this->has('items')) {
            $items = $this->input('items');
            foreach ($items as $index => $item) {
                if (isset($item['supplier_price']) && is_numeric($item['supplier_price'])) {
                    $items[$index]['supplier_price'] = (int) round($item['supplier_price'] * 100);
                }
                if (isset($item['retail_price']) && is_numeric($item['retail_price'])) {
                    $items[$index]['retail_price'] = (int) round($item['retail_price'] * 100);
                }
            }
            $this->merge(['items' => $items]);
        }
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->has('total_cost') && $this->has('total_retail')) {
                if ($this->input('total_retail') <= $this->input('total_cost')) {
                    $validator->errors()->add('total_retail', 'Total retail price must be greater than total cost.');
                }
            }

            if ($this->has('items')) {
                $items = $this->input('items');
                foreach ($items as $index => $item) {
                    if (isset($item['retail_price']) && isset($item['supplier_price'])) {
                        if ($item['retail_price'] <= $item['supplier_price']) {
                            $validator->errors()->add(
                                "items.{$index}.retail_price",
                                'Retail price must be greater than supplier price for each item.'
                            );
                        }
                    }
                }
            }
        });
    }
}
