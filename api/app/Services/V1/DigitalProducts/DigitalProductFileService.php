<?php

namespace App\Services\V1\DigitalProducts;

use App\Models\Product;
use App\Models\ProductFile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Exception;

class DigitalProductFileService
{
    protected DigitalProductStorageService $storageService;

    protected array $allowedMimeTypes = [
        'application/pdf',
        'application/zip',
        'application/x-zip-compressed',
        'application/x-rar-compressed',
        'application/x-7z-compressed',
        'application/octet-stream',
        'text/plain',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'audio/mpeg',
        'audio/wav',
        'audio/x-wav',
        'video/mp4',
        'video/avi',
        'video/x-msvideo',
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
    ];

    protected array $allowedExtensions = [
        'pdf', 'zip', 'rar', '7z', 'exe', 'msi', 'dmg', 'pkg',
        'txt', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
        'mp3', 'wav', 'flac', 'aac', 'ogg',
        'mp4', 'avi', 'mkv', 'mov', 'wmv',
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg',
        'json', 'xml', 'csv'
    ];

    protected int $maxFileSize = 1073741824; // 1GB in bytes

    public function __construct(DigitalProductStorageService $storageService)
    {
        $this->storageService = $storageService;
    }

    public function uploadFile(Request $request, Product $product, User $user): ProductFile
    {
        $this->validateRequest($request, $user);
        $this->validateProduct($product, $user);

        $file = $request->file('file');
        $this->validateFile($file);

        $metadata = $this->extractMetadata($request);

        try {
            $productFile = $this->storageService->storeFile($file, $product, $metadata);

            Log::info('Digital product file uploaded successfully', [
                'product_id' => $product->id,
                'file_id' => $productFile->id,
                'uploaded_by' => $user->id,
                'file_size' => $file->getSize()
            ]);

            return $productFile;

        } catch (Exception $e) {
            Log::error('Failed to upload digital product file', [
                'product_id' => $product->id,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            throw new Exception('Failed to upload file: ' . $e->getMessage());
        }
    }

    public function updateFile(ProductFile $productFile, array $data, User $user): ProductFile
    {
        $this->validateUpdatePermissions($productFile, $user);

        $updateData = [];

        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }

        if (isset($data['description'])) {
            $updateData['description'] = $data['description'];
        }

        if (isset($data['version'])) {
            $updateData['version'] = $data['version'];
        }

        if (isset($data['is_primary'])) {
            $updateData['is_primary'] = $data['is_primary'];
        }

        if (isset($data['is_active'])) {
            $updateData['is_active'] = $data['is_active'];
        }

        if (isset($data['download_limit'])) {
            $updateData['download_limit'] = $data['download_limit'];
        }

        if (isset($data['expires_at'])) {
            $updateData['expires_at'] = $data['expires_at'];
        }

        if (isset($data['metadata'])) {
            $updateData['metadata'] = array_merge($productFile->metadata ?? [], $data['metadata']);
        }

        $productFile->update($updateData);

        if ($updateData['is_primary'] ?? false) {
            ProductFile::where('product_id', $productFile->product_id)
                ->where('id', '!=', $productFile->id)
                ->update(['is_primary' => false]);
        }

        Log::info('Digital product file updated', [
            'file_id' => $productFile->id,
            'updated_by' => $user->id,
            'changes' => array_keys($updateData)
        ]);

        return $productFile->fresh();
    }

    public function deleteFile(ProductFile $productFile, User $user): bool
    {
        $this->validateDeletePermissions($productFile, $user);

        try {
            $result = $this->storageService->deleteFile($productFile);

            Log::info('Digital product file deleted', [
                'file_id' => $productFile->id,
                'deleted_by' => $user->id
            ]);

            return $result;

        } catch (Exception $e) {
            Log::error('Failed to delete digital product file', [
                'file_id' => $productFile->id,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            throw new Exception('Failed to delete file: ' . $e->getMessage());
        }
    }

    public function getFileInfo(ProductFile $productFile, User $user): array
    {
        $this->validateViewPermissions($productFile, $user);

        $fileExists = $this->storageService->verifyFileIntegrity($productFile);

        return [
            'id' => $productFile->id,
            'name' => $productFile->name,
            'original_filename' => $productFile->original_filename,
            'file_type' => $productFile->file_type,
            'mime_type' => $productFile->mime_type,
            'file_size' => $productFile->file_size,
            'file_size_formatted' => $productFile->file_size_formatted,
            'file_hash' => $productFile->file_hash,
            'is_primary' => $productFile->is_primary,
            'is_active' => $productFile->is_active,
            'download_limit' => $productFile->download_limit,
            'download_count' => $productFile->download_count,
            'version' => $productFile->version,
            'description' => $productFile->description,
            'expires_at' => $productFile->expires_at,
            'file_exists' => $fileExists,
            'file_icon' => $productFile->getFileTypeIcon(),
            'metadata' => $productFile->metadata,
            'created_at' => $productFile->created_at,
            'updated_at' => $productFile->updated_at,
        ];
    }

    public function bulkUpload(Request $request, Product $product, User $user): array
    {
        $this->validateProduct($product, $user);

        $files = $request->file('files', []);
        $results = [];
        $errors = [];

        foreach ($files as $index => $file) {
            try {
                $metadata = $this->extractBulkMetadata($request, $index);
                $productFile = $this->storageService->storeFile($file, $product, $metadata);

                $results[] = [
                    'index' => $index,
                    'success' => true,
                    'file_id' => $productFile->id,
                    'filename' => $file->getClientOriginalName(),
                ];

            } catch (Exception $e) {
                $errors[] = [
                    'index' => $index,
                    'success' => false,
                    'filename' => $file->getClientOriginalName(),
                    'error' => $e->getMessage(),
                ];
            }
        }

        Log::info('Bulk upload completed', [
            'product_id' => $product->id,
            'user_id' => $user->id,
            'total_files' => count($files),
            'successful' => count($results),
            'failed' => count($errors)
        ]);

        return [
            'successful' => $results,
            'failed' => $errors,
            'total_processed' => count($files),
            'success_count' => count($results),
            'error_count' => count($errors),
        ];
    }

    protected function validateRequest(Request $request, User $user): void
    {
        if (!$user->hasPermission('manage_digital_products')) {
            throw new Exception('Insufficient permissions to upload digital product files', 403);
        }

        $validator = Validator::make($request->all(), [
            'file' => 'required|file',
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'version' => 'nullable|string|max:50',
            'is_primary' => 'nullable|boolean',
            'download_limit' => 'nullable|integer|min:1|max:1000',
            'expires_at' => 'nullable|date|after:now',
        ]);

        if ($validator->fails()) {
            throw new Exception('Validation failed: ' . implode(', ', $validator->errors()->all()), 422);
        }
    }

    protected function validateProduct(Product $product, User $user): void
    {
        if (!$product->isDigital()) {
            throw new Exception('Product must be digital or hybrid to upload files', 400);
        }

        if ($user->hasRole('vendor') && $product->vendor_id !== $user->vendors()->first()?->id) {
            throw new Exception('You can only upload files to your own products', 403);
        }
    }

    protected function validateFile(UploadedFile $file): void
    {
        if (!$file->isValid()) {
            throw new Exception('Invalid file upload', 400);
        }

        if ($file->getSize() > $this->maxFileSize) {
            throw new Exception('File size exceeds maximum allowed size of ' . ($this->maxFileSize / 1024 / 1024) . 'MB', 413);
        }

        $mimeType = $file->getMimeType();
        if (!in_array($mimeType, $this->allowedMimeTypes)) {
            throw new Exception('File type not allowed: ' . $mimeType, 415);
        }

        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, $this->allowedExtensions)) {
            throw new Exception('File extension not allowed: ' . $extension, 415);
        }
    }

    protected function validateUpdatePermissions(ProductFile $productFile, User $user): void
    {
        if (!$user->hasPermission('manage_digital_products')) {
            throw new Exception('Insufficient permissions to update digital product files', 403);
        }

        if ($user->hasRole('vendor') && $productFile->product->vendor_id !== $user->vendors()->first()?->id) {
            throw new Exception('You can only update files for your own products', 403);
        }
    }

    protected function validateDeletePermissions(ProductFile $productFile, User $user): void
    {
        if (!$user->hasPermission('manage_digital_products')) {
            throw new Exception('Insufficient permissions to delete digital product files', 403);
        }

        if ($user->hasRole('vendor') && $productFile->product->vendor_id !== $user->vendors()->first()?->id) {
            throw new Exception('You can only delete files for your own products', 403);
        }
    }

    protected function validateViewPermissions(ProductFile $productFile, User $user): void
    {
        if (!$user->hasPermission('view_digital_products')) {
            throw new Exception('Insufficient permissions to view digital product files', 403);
        }

        if ($user->hasRole('vendor') && $productFile->product->vendor_id !== $user->vendors()->first()?->id) {
            throw new Exception('You can only view files for your own products', 403);
        }
    }

    protected function extractMetadata(Request $request): array
    {
        return [
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'version' => $request->input('version', '1.0.0'),
            'is_primary' => $request->boolean('is_primary', false),
            'download_limit' => $request->input('download_limit'),
            'upload_ip' => $request->ip(),
            'upload_user_agent' => $request->userAgent(),
        ];
    }

    protected function extractBulkMetadata(Request $request, int $index): array
    {
        return [
            'name' => $request->input("names.{$index}"),
            'description' => $request->input("descriptions.{$index}"),
            'version' => $request->input("versions.{$index}", '1.0.0'),
            'is_primary' => $request->boolean("is_primary.{$index}", false),
            'download_limit' => $request->input("download_limits.{$index}"),
            'upload_ip' => $request->ip(),
            'upload_user_agent' => $request->userAgent(),
        ];
    }
}
