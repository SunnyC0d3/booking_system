<?php

namespace App\Requests\V1;

class FilterProductRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'filter' => 'array',
            'filter.name' => 'string|max:255',
            'filter.search' => 'string|max:500',

            'filter.price' => 'string|regex:/^\d+(\.\d{1,2})?(,\d+(\.\d{1,2})?)*$/',
            'filter.priceRanges' => 'string|regex:/^(\d+(\.\d{1,2})?-\d+(\.\d{1,2})?)(,\d+(\.\d{1,2})?-\d+(\.\d{1,2})?)*$/',

            'filter.category' => 'string|regex:/^(\d,?)+$/',
            'filter.categories' => 'string|regex:/^(\d,?)+$/',

            'filter.quantity' => 'string|regex:/^\d+(,\d+)?$/',
            'filter.availability' => 'string|in:in_stock,out_of_stock,low_stock,available',

            'filter.vendors' => 'string|regex:/^(\d,?)+$/',
            'filter.vendor' => 'string|max:255',
            'filter.brand' => 'string|max:255',
            'filter.brands' => 'string|regex:/^(\d,?)+$/',

            'filter.tags' => 'string|regex:/^(\d,?)+$/',
            'filter.tag_logic' => 'string|in:AND,OR',

            'filter.attributes' => 'string|regex:/^([a-zA-Z_]+:[a-zA-Z0-9_\-\s]+)(,[a-zA-Z_]+:[a-zA-Z0-9_\-\s]+)*$/',
            'filter.color' => 'string|max:50',
            'filter.colors' => 'string|regex:/^([a-zA-Z]+,?)+$/',
            'filter.size' => 'string|max:50',
            'filter.sizes' => 'string|regex:/^([a-zA-Z0-9]+,?)+$/',
            'filter.material' => 'string|max:100',

            'filter.status' => 'string|max:50',

            'filter.created_at' => 'string|regex:/^\d{4}-\d{2}-\d{2}(,\d{4}-\d{2}-\d{2})?$/',
            'filter.updated_at' => 'string|regex:/^\d{4}-\d{2}-\d{2}(,\d{4}-\d{2}-\d{2})?$/',

            'filter.include' => 'string|regex:/^(\w+(,\w+)*)?$/',

            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:100',

            'sort' => 'string|regex:/^(-?[a-zA-Z0-9_]+)(,-?[a-zA-Z0-9_]+)*$/',

            'diversify' => 'boolean',
            'explain' => 'boolean',
            'facets' => 'boolean',
            'highlight' => 'boolean',
            'personalize' => 'boolean',

            'typo_tolerance' => 'boolean',
            'exact_match' => 'boolean',
            'boost_new_products' => 'boolean',
            'boost_in_stock' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'filter.search.max' => 'Search query cannot exceed 500 characters.',
            'filter.price.regex' => 'Price filter must be in format: number or number,number (e.g., 10.50 or 10,50).',
            'filter.priceRanges.regex' => 'Price ranges must be in format: min-max,min-max (e.g., 0-25,50-100).',
            'filter.category.regex' => 'Category filter must contain only comma-separated numbers.',
            'filter.vendors.regex' => 'Vendors filter must contain only comma-separated numbers.',
            'filter.tags.regex' => 'Tags filter must contain only comma-separated numbers.',
            'filter.attributes.regex' => 'Attributes must be in format: name:value,name:value (e.g., color:red,size:large).',
            'filter.availability.in' => 'Availability must be one of: in_stock, out_of_stock, low_stock, available.',
            'filter.tag_logic.in' => 'Tag logic must be either AND or OR.',
            'filter.created_at.regex' => 'Date filter must be in YYYY-MM-DD format or date range.',
            'filter.updated_at.regex' => 'Date filter must be in YYYY-MM-DD format or date range.',
            'filter.include.regex' => 'Include parameter must be comma-separated relationship names.',
            'per_page.max' => 'Cannot request more than 100 items per page.',
            'sort.regex' => 'Sort parameter must be comma-separated field names (prefix with - for descending).',
        ];
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        if ($this->has('filter.search')) {
            $search = trim($this->input('filter.search'));
            $this->merge([
                'filter' => array_merge($this->input('filter', []), [
                    'search' => $search
                ])
            ]);
        }

        $booleanFields = ['diversify', 'explain', 'facets', 'highlight', 'personalize', 'typo_tolerance', 'exact_match', 'boost_new_products', 'boost_in_stock'];
        foreach ($booleanFields as $field) {
            if ($this->has($field)) {
                $this->merge([$field => filter_var($this->input($field), FILTER_VALIDATE_BOOLEAN)]);
            }
        }

        if ($this->has('filter')) {
            $filters = array_filter($this->input('filter'), function($value) {
                return !is_null($value) && $value !== '';
            });
            $this->merge(['filter' => $filters]);
        }
    }
}
