<?php

namespace App\Requests\V1;

use Illuminate\Validation\Rule;

class UpdateDigitalProductRequest extends BaseFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        // Check basic permission
        if (!$user->hasPermission('edit_digital_products')) {
            return false;
        }

        // Vendors can only edit their own products
        if ($user->hasRole('vendor')) {
            $product = $this->route('product');
            $vendorId = $user->vendors()->first()?->id;
            return $product && $product->vendor_id === $vendorId;
        }

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // Basic product information (all optional for updates)
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string|max:5000',
            'price' => 'sometimes|numeric|min:0|max:999999.99',
            'quantity' => 'sometimes|integer|min:0|max:999999',
            'low_stock_threshold' => 'sometimes|integer|min:0|max:1000',

            // Category and status
            'product_category_id' => 'sometimes|integer|exists:product_categories,id',
            'product_status_id' => 'sometimes|integer|exists:product_statuses,id',

            // Digital product specific fields
            'product_type' => ['sometimes', Rule::in(['digital', 'hybrid'])],
            'requires_license' => 'sometimes|boolean',
            'auto_deliver' => 'sometimes|boolean',
            'download_limit' => 'sometimes|integer|min:1|max:100',
            'download_expiry_days' => 'sometimes|integer|min:1|max:3650',

            // Platform and system requirements
            'supported_platforms' => 'sometimes|array|max:20',
            'supported_platforms.*' => 'string|max:100',
            'system_requirements' => 'sometimes|array',
            'system_requirements.os' => 'nullable|string|max:500',
            'system_requirements.ram' => 'nullable|string|max:200',
            'system_requirements.storage' => 'nullable|string|max:200',
            'system_requirements.processor' => 'nullable|string|max:200',
            'system_requirements.graphics' => 'nullable|string|max:200',
            'system_requirements.network' => 'nullable|string|max:200',
            'system_requirements.additional' => 'nullable|string|max:1000',

            // Version control
            'latest_version' => 'sometimes|string|max:50',
            'version_control_enabled' => 'sometimes|boolean',

            // Tags and variants
            'product_tags' => 'sometimes|array|max:50',
            'product_tags.*' => 'integer|exists:product_tags,id',
            'product_variants' => 'sometimes|array|max:20',
            'product_variants.*.product_attribute_id' => 'required|integer|exists:product_attributes,id',
            'product_variants.*.value' => 'required|string|max:255',
            'product_variants.*.additional_price' => 'nullable|numeric|min:0|max:99999.99',
            'product_variants.*.quantity' => 'nullable|integer|min:0|max:999999',
            'product_variants.*.low_stock_threshold' => 'nullable|integer|min:0|max:1000',

            // Media uploads
            'media' => 'sometimes|array',
            'media.featured_image' => [
                'nullable',
                'file',
                'image',
                'mimes:jpeg,jpg,png,webp',
                'max:5120',
                'dimensions:min_width=100,min_height=100,max_width=4000,max_height=4000'
            ],
            'media.gallery' => 'sometimes|array|max:10',
            'media.gallery.*' => [
                'file',
                'image',
                'mimes:jpeg,jpg,png,webp',
                'max:5120',
                'dimensions:min_width=100,min_height=100,max_width=4000,max_height=4000'
            ],
            'media.remove_featured' => 'sometimes|boolean',
            'media.remove_gallery_ids' => 'sometimes|array',
            'media.remove_gallery_ids.*' => 'integer',

            // SEO and metadata
            'seo_title' => 'sometimes|string|max:255',
            'seo_description' => 'sometimes|string|max:500',
            'search_keywords' => 'sometimes|array|max:50',
            'search_keywords.*' => 'string|max:100',

            // Shipping (for hybrid products)
            'requires_shipping' => 'sometimes|boolean',
            'weight' => 'sometimes|numeric|min:0|max:999999.99',
            'length' => 'sometimes|numeric|min:0|max:999999.99',
            'width' => 'sometimes|numeric|min:0|max:999999.99',
            'height' => 'sometimes|numeric|min:0|max:999999.99',
            'shipping_class' => 'sometimes|string|max:100',

            // Update metadata
            'update_reason' => 'sometimes|string|max:500',
            'notify_customers' => 'sometimes|boolean',
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'name.max' => 'Product name cannot exceed 255 characters.',
            'description.max' => 'Product description cannot exceed 5,000 characters.',

            'price.numeric' => 'Product price must be a valid number.',
            'price.min' => 'Product price cannot be negative.',
            'price.max' => 'Product price cannot exceed £999,999.99.',

            'product_category_id.exists' => 'Selected product category does not exist.',
            'product_status_id.exists' => 'Selected product status does not exist.',

            'product_type.in' => 'Product type must be either digital or hybrid.',

            'download_limit.min' => 'Download limit must be at least 1.',
            'download_limit.max' => 'Download limit cannot exceed 100.',

            'download_expiry_days.min' => 'Download expiry must be at least 1 day.',
            'download_expiry_days.max' => 'Download expiry cannot exceed 10 years (3650 days).',

            'supported_platforms.max' => 'Cannot specify more than 20 supported platforms.',
            'supported_platforms.*.max' => 'Platform name cannot exceed 100 characters.',

            'latest_version.max' => 'Version string cannot exceed 50 characters.',

            'product_tags.max' => 'Cannot assign more than 50 tags to a product.',
            'product_tags.*.exists' => 'One or more selected tags do not exist.',

            'product_variants.max' => 'Cannot create more than 20 variants for a product.',
            'product_variants.*.product_attribute_id.exists' => 'Selected variant attribute does not exist.',
            'product_variants.*.value.required' => 'Variant value is required.',
            'product_variants.*.value.max' => 'Variant value cannot exceed 255 characters.',

            'media.featured_image.mimes' => 'Featured image must be a JPEG, PNG, or WebP file.',
            'media.featured_image.max' => 'Featured image cannot exceed 5MB.',
            'media.featured_image.dimensions' => 'Featured image dimensions must be between 100x100 and 4000x4000 pixels.',

            'media.gallery.max' => 'Cannot upload more than 10 gallery images.',
            'media.gallery.*.mimes' => 'Gallery images must be JPEG, PNG, or WebP files.',
            'media.gallery.*.max' => 'Each gallery image cannot exceed 5MB.',
            'media.gallery.*.dimensions' => 'Gallery image dimensions must be between 100x100 and 4000x4000 pixels.',

            'update_reason.max' => 'Update reason cannot exceed 500 characters.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'product_category_id' => 'product category',
            'product_status_id' => 'product status',
            'product_type' => 'product type',
            'requires_license' => 'license requirement',
            'auto_deliver' => 'auto delivery',
            'download_limit' => 'download limit',
            'download_expiry_days' => 'download expiry period',
            'supported_platforms' => 'supported platforms',
            'system_requirements' => 'system requirements',
            'latest_version' => 'latest version',
            'version_control_enabled' => 'version control',
            'product_tags' => 'product tags',
            'product_variants' => 'product variants',
            'media.featured_image' => 'featured image',
            'media.gallery' => 'gallery images',
            'seo_title' => 'SEO title',
            'seo_description' => 'SEO description',
            'search_keywords' => 'search keywords',
            'requires_shipping' => 'shipping requirement',
            'update_reason' => 'update reason',
            'notify_customers' => 'customer notification',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $product = $this->route('product');

            // Prevent changing product type if files exist
            if ($this->has('product_type') && $product->productFiles()->exists()) {
                $currentType = $product->product_type;
                $newType = $this->input('product_type');

                if ($currentType !== $newType) {
                    $validator->errors()->add(
                        'product_type',
                        'Cannot change product type when digital files exist. Remove all files first.'
                    );
                }
            }

            // Custom validation for hybrid products
            if ($this->input('product_type') === 'hybrid' && $this->has('requires_shipping') && !$this->input('requires_shipping')) {
                $validator->errors()->add(
                    'requires_shipping',
                    'Hybrid products must have shipping enabled.'
                );
            }

            // Validate supported platforms are unique
            if ($this->has('supported_platforms')) {
                $platforms = $this->input('supported_platforms', []);
                if (count($platforms) !== count(array_unique($platforms))) {
                    $validator->errors()->add(
                        'supported_platforms',
                        'Supported platforms must be unique.'
                    );
                }
            }

            // Validate tag uniqueness
            if ($this->has('product_tags')) {
                $tags = $this->input('product_tags', []);
                if (count($tags) !== count(array_unique($tags))) {
                    $validator->errors()->add(
                        'product_tags',
                        'Product tags must be unique.'
                    );
                }
            }

            // Validate variant attribute uniqueness
            if ($this->has('product_variants')) {
                $variants = $this->input('product_variants', []);
                $attributeIds = array_column($variants, 'product_attribute_id');
                if (count($attributeIds) !== count(array_unique($attributeIds))) {
                    $validator->errors()->add(
                        'product_variants',
                        'Each product attribute can only be used once per product.'
                    );
                }
            }

            // Validate download limit changes don't affect existing access
            if ($this->has('download_limit') && $product->downloadAccesses()->active()->exists()) {
                $newLimit = $this->input('download_limit');
                $existingAccesses = $product->downloadAccesses()->active()->get();

                foreach ($existingAccesses as $access) {
                    if ($access->downloads_used > $newLimit) {
                        $validator->errors()->add(
                            'download_limit',
                            "Cannot set download limit lower than existing usage. User {$access->user->name} has already used {$access->downloads_used} downloads."
                        );
                        break;
                    }
                }
            }
        });
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convert price to pennies for storage
        if ($this->has('price')) {
            $this->merge([
                'price' => round($this->input('price') * 100)
            ]);
        }

        // Convert variant additional prices to pennies
        if ($this->has('product_variants')) {
            $variants = $this->input('product_variants', []);
            foreach ($variants as $index => $variant) {
                if (isset($variant['additional_price'])) {
                    $variants[$index]['additional_price'] = round($variant['additional_price'] * 100);
                }
            }
            $this->merge(['product_variants' => $variants]);
        }

        // Set defaults for boolean fields if provided
        if ($this->has('requires_license')) {
            $this->merge(['requires_license' => $this->boolean('requires_license')]);
        }
        if ($this->has('auto_deliver')) {
            $this->merge(['auto_deliver' => $this->boolean('auto_deliver')]);
        }
        if ($this->has('version_control_enabled')) {
            $this->merge(['version_control_enabled' => $this->boolean('version_control_enabled')]);
        }
        if ($this->has('requires_shipping')) {
            $this->merge(['requires_shipping' => $this->boolean('requires_shipping')]);
        }
        if ($this->has('notify_customers')) {
            $this->merge(['notify_customers' => $this->boolean('notify_customers', false)]);
        }
    }
}
