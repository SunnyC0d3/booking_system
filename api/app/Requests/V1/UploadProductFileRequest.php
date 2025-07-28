<?php

namespace App\Requests\V1;

use Illuminate\Validation\Rule;

class UploadProductFileRequest extends BaseFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        // Check basic permission
        if (!$user->hasPermission('upload_digital_files')) {
            return false;
        }

        // Vendors can only upload files to their own products
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
        $maxFileSize = config('app.max_digital_file_size', 524288); // 512MB in KB
        $allowedMimeTypes = config('app.allowed_digital_file_types', [
            'application/zip',
            'application/x-zip-compressed',
            'application/octet-stream',
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'text/plain',
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/svg+xml',
            'audio/mpeg',
            'audio/wav',
            'audio/ogg',
            'video/mp4',
            'video/webm',
            'video/ogg',
            'application/json',
            'application/xml',
            'text/xml',
            'text/csv',
        ]);

        return [
            // File upload (single file)
            'file' => [
                'required',
                'file',
                'max:' . $maxFileSize, // Max file size in KB
                Rule::in($allowedMimeTypes)->message('The uploaded file type is not supported.'),
            ],

            // File metadata
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'version' => 'nullable|string|max:50|regex:/^[0-9]+(\.[0-9]+)*([a-zA-Z0-9\-_]*)?$/',
            'is_primary' => 'boolean',
            'download_limit' => 'nullable|integer|min:1|max:1000',
            'expires_at' => 'nullable|date|after:now',

            // File organization
            'category' => 'nullable|string|max:100',
            'tags' => 'nullable|array|max:20',
            'tags.*' => 'string|max:50',

            // Access control
            'is_active' => 'boolean',
            'requires_license' => 'boolean',
            'platform_specific' => 'nullable|string|max:100',

            // Update information
            'changelog' => 'nullable|string|max:2000',
            'release_notes' => 'nullable|string|max:2000',
            'is_beta' => 'boolean',
            'is_hotfix' => 'boolean',
        ];
    }

    /**
     * Get validation rules for bulk upload.
     */
    public function bulkRules(): array
    {
        $maxFileSize = config('app.max_digital_file_size', 524288);
        $allowedMimeTypes = config('app.allowed_digital_file_types', []);

        return [
            // Multiple files
            'files' => 'required|array|min:1|max:10',
            'files.*' => [
                'required',
                'file',
                'max:' . $maxFileSize,
                Rule::in($allowedMimeTypes)->message('One or more uploaded file types are not supported.'),
            ],

            // Metadata arrays (optional, must match file count if provided)
            'names' => 'nullable|array',
            'names.*' => 'nullable|string|max:255',
            'descriptions' => 'nullable|array',
            'descriptions.*' => 'nullable|string|max:1000',
            'versions' => 'nullable|array',
            'versions.*' => 'nullable|string|max:50|regex:/^[0-9]+(\.[0-9]+)*([a-zA-Z0-9\-_]*)?$/',
            'is_primary' => 'nullable|array',
            'is_primary.*' => 'boolean',
            'download_limits' => 'nullable|array',
            'download_limits.*' => 'nullable|integer|min:1|max:1000',
            'categories' => 'nullable|array',
            'categories.*' => 'nullable|string|max:100',

            // Global settings for all files
            'global_is_active' => 'boolean',
            'global_requires_license' => 'boolean',
            'global_expires_at' => 'nullable|date|after:now',
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        $maxFileSizeMB = config('app.max_digital_file_size', 524288) / 1024;

        return [
            'file.required' => 'A file is required for upload.',
            'file.file' => 'The uploaded item must be a valid file.',
            'file.max' => "File size cannot exceed {$maxFileSizeMB}MB.",

            'files.required' => 'At least one file is required for bulk upload.',
            'files.array' => 'Files must be provided as an array.',
            'files.min' => 'At least one file is required.',
            'files.max' => 'Cannot upload more than 10 files at once.',
            'files.*.required' => 'Each file item is required.',
            'files.*.file' => 'Each uploaded item must be a valid file.',
            'files.*.max' => "Each file cannot exceed {$maxFileSizeMB}MB.",

            'name.max' => 'File name cannot exceed 255 characters.',
            'description.max' => 'File description cannot exceed 1,000 characters.',
            'version.regex' => 'Version must be in format like: 1.0.0, 2.1.5-beta, 1.0.0-alpha.1',
            'version.max' => 'Version string cannot exceed 50 characters.',

            'download_limit.min' => 'Download limit must be at least 1.',
            'download_limit.max' => 'Download limit cannot exceed 1,000.',

            'expires_at.date' => 'Expiration date must be a valid date.',
            'expires_at.after' => 'Expiration date must be in the future.',

            'category.max' => 'Category name cannot exceed 100 characters.',
            'tags.array' => 'Tags must be provided as an array.',
            'tags.max' => 'Cannot specify more than 20 tags per file.',
            'tags.*.max' => 'Each tag cannot exceed 50 characters.',

            'platform_specific.max' => 'Platform specification cannot exceed 100 characters.',
            'changelog.max' => 'Changelog cannot exceed 2,000 characters.',
            'release_notes.max' => 'Release notes cannot exceed 2,000 characters.',

            'names.array' => 'File names must be provided as an array.',
            'descriptions.array' => 'File descriptions must be provided as an array.',
            'versions.array' => 'File versions must be provided as an array.',
            'global_expires_at.after' => 'Global expiration date must be in the future.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'file' => 'uploaded file',
            'files' => 'uploaded files',
            'files.*' => 'uploaded file',
            'name' => 'file name',
            'description' => 'file description',
            'version' => 'file version',
            'is_primary' => 'primary file flag',
            'download_limit' => 'download limit',
            'expires_at' => 'expiration date',
            'category' => 'file category',
            'tags' => 'file tags',
            'is_active' => 'active status',
            'requires_license' => 'license requirement',
            'platform_specific' => 'platform specification',
            'changelog' => 'changelog',
            'release_notes' => 'release notes',
            'is_beta' => 'beta status',
            'is_hotfix' => 'hotfix status',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $product = $this->route('product');

            // Validate product supports digital files
            if (!$product->isDigital()) {
                $validator->errors()->add(
                    'file',
                    'This product does not support digital file uploads.'
                );
                return;
            }

            // Check if setting as primary when another primary exists
            if ($this->boolean('is_primary')) {
                $existingPrimary = $product->productFiles()->where('is_primary', true)->first();
                if ($existingPrimary) {
                    $validator->errors()->add(
                        'is_primary',
                        "Another file ('{$existingPrimary->name}') is already set as primary. Only one primary file is allowed per product."
                    );
                }
            }

            // Validate file count limits
            $existingFileCount = $product->productFiles()->count();
            $maxFilesPerProduct = config('app.max_files_per_product', 50);

            if ($this->hasFile('file')) {
                // Single file upload
                if ($existingFileCount >= $maxFilesPerProduct) {
                    $validator->errors()->add(
                        'file',
                        "Cannot upload more files. Maximum {$maxFilesPerProduct} files per product."
                    );
                }
            } elseif ($this->has('files')) {
                // Bulk upload
                $newFileCount = count($this->file('files', []));
                if ($existingFileCount + $newFileCount > $maxFilesPerProduct) {
                    $validator->errors()->add(
                        'files',
                        "Cannot upload {$newFileCount} files. Would exceed maximum of {$maxFilesPerProduct} files per product."
                    );
                }
            }

            // Validate version format and uniqueness
            if ($this->has('version')) {
                $version = $this->input('version');
                if ($version && $product->productFiles()->where('version', $version)->exists()) {
                    $validator->errors()->add(
                        'version',
                        "A file with version '{$version}' already exists for this product."
                    );
                }
            }

            // Validate bulk upload metadata arrays match file count
            if ($this->has('files')) {
                $fileCount = count($this->file('files', []));

                $metadataFields = ['names', 'descriptions', 'versions', 'is_primary', 'download_limits', 'categories'];
                foreach ($metadataFields as $field) {
                    if ($this->has($field)) {
                        $metadataCount = count($this->input($field, []));
                        if ($metadataCount !== $fileCount) {
                            $validator->errors()->add(
                                $field,
                                "The {$field} array must contain exactly {$fileCount} items to match the number of uploaded files."
                            );
                        }
                    }
                }
            }

            // Security validation - check for potentially dangerous files
            $dangerousExtensions = ['exe', 'bat', 'cmd', 'com', 'pif', 'scr', 'vbs', 'js', 'jar', 'app', 'deb', 'pkg', 'dmg'];

            if ($this->hasFile('file')) {
                $file = $this->file('file');
                $extension = strtolower($file->getClientOriginalExtension());

                if (in_array($extension, $dangerousExtensions)) {
                    $validator->errors()->add(
                        'file',
                        'Executable files and potentially dangerous file types are not allowed for security reasons.'
                    );
                }
            }
        });
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set defaults
        $this->merge([
            'is_primary' => $this->boolean('is_primary', false),
            'is_active' => $this->boolean('is_active', true),
            'requires_license' => $this->boolean('requires_license', false),
            'is_beta' => $this->boolean('is_beta', false),
            'is_hotfix' => $this->boolean('is_hotfix', false),
        ]);

        // For bulk uploads
        if ($this->has('files')) {
            $this->merge([
                'global_is_active' => $this->boolean('global_is_active', true),
                'global_requires_license' => $this->boolean('global_requires_license', false),
            ]);
        }

        // Generate default name from filename if not provided
        if (!$this->has('name') && $this->hasFile('file')) {
            $file = $this->file('file');
            $name = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $this->merge(['name' => $name]);
        }

        // Set default version if not provided
        if (!$this->has('version')) {
            $this->merge(['version' => '1.0.0']);
        }
    }

    /**
     * Get the validator instance for bulk uploads.
     */
    public function getBulkValidator(): \Illuminate\Contracts\Validation\Validator
    {
        return validator($this->all(), $this->bulkRules(), $this->messages(), $this->attributes());
    }
}
