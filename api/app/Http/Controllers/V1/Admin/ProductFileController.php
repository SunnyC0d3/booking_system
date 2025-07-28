<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Traits\V1\ApiResponses;
use App\Models\Product;
use App\Models\ProductFile;
use App\Services\V1\DigitalProducts\DigitalProductFileService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ProductFileController extends Controller
{
    use ApiResponses;

    protected DigitalProductFileService $fileService;

    public function __construct(DigitalProductFileService $fileService)
    {
        $this->fileService = $fileService;
    }

    /**
     * Display a listing of product files
     *
     * Retrieve all files for a specific digital product. Supports filtering and sorting
     * with proper permission checks based on user role and product ownership.
     *
     * @group Product File Management
     * @authenticated
     *
     * @urlParam product integer required The ID of the product. Example: 1
     * @queryParam active boolean optional Filter by active status. Example: true
     * @queryParam primary boolean optional Filter primary files only. Example: false
     * @queryParam sort_by string optional Sort field (name, created_at, file_size). Example: created_at
     * @queryParam sort_direction string optional Sort direction (asc, desc). Example: desc
     *
     * @response 200 scenario="Product files retrieved successfully" {
     *   "data": {
     *     "files": [
     *       {
     *         "id": 15,
     *         "name": "ProjectManager Pro - Windows Installer",
     *         "original_filename": "projectmanager-pro-v2.1.5-windows.zip",
     *         "file_size": 47185920,
     *         "file_size_formatted": "45.2 MB",
     *         "file_type": "application/zip",
     *         "mime_type": "application/zip",
     *         "version": "2.1.5",
     *         "is_primary": true,
     *         "is_active": true,
     *         "download_count": 127,
     *         "description": "Main Windows installation file",
     *         "created_at": "2024-01-15T10:30:00Z",
     *         "expires_at": null
     *       }
     *     ],
     *     "product": {
     *       "id": 1,
     *       "name": "ProjectManager Pro",
     *       "product_type": "digital"
     *     },
     *     "summary": {
     *       "total_files": 4,
     *       "active_files": 4,
     *       "total_size": "125.8 MB",
     *       "primary_file_id": 15
     *     }
     *   },
     *   "message": "Product files retrieved successfully.",
     *   "status": 200
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "Insufficient permissions to view product files.",
     *   "status": 403
     * }
     */
    public function index(Request $request, Product $product): JsonResponse
    {
        try {
            // Check permissions
            if (!$request->user()->hasPermission('view_product_files')) {
                return $this->error('Insufficient permissions to view product files.', 403);
            }

            // Check product ownership for vendors
            if (!$this->canAccessProduct($request->user(), $product)) {
                return $this->error('You do not have access to this product.', 403);
            }

            // Validate that this is a digital product
            if (!$product->isDigital()) {
                return $this->error('This product does not support digital files.', 400);
            }

            $filters = [
                'active' => $request->get('active'),
                'primary' => $request->boolean('primary'),
                'sort_by' => $request->get('sort_by', 'created_at'),
                'sort_direction' => $request->get('sort_direction', 'desc'),
            ];

            $filesData = $this->fileService->getProductFiles($product, $filters);

            return $this->ok(
                'Product files retrieved successfully.',
                $filesData
            );

        } catch (\Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Display a specific product file
     *
     * Retrieve detailed information about a specific product file including
     * download statistics, version history, and metadata.
     *
     * @group Product File Management
     * @authenticated
     *
     * @urlParam product integer required The ID of the product. Example: 1
     * @urlParam file integer required The ID of the product file. Example: 15
     *
     * @response 200 scenario="Product file retrieved successfully" {
     *   "data": {
     *     "id": 15,
     *     "name": "ProjectManager Pro - Windows Installer",
     *     "original_filename": "projectmanager-pro-v2.1.5-windows.zip",
     *     "file_path": "products/1/files/abc123def456.zip",
     *     "file_size": 47185920,
     *     "file_size_formatted": "45.2 MB",
     *     "file_type": "application/zip",
     *     "mime_type": "application/zip",
     *     "file_hash": "sha256:abc123def456...",
     *     "version": "2.1.5",
     *     "is_primary": true,
     *     "is_active": true,
     *     "download_limit": null,
     *     "download_count": 127,
     *     "description": "Main Windows installation file",
     *     "metadata": {
     *       "uploaded_by": "admin",
     *       "upload_ip": "192.168.1.100"
     *     },
     *     "expires_at": null,
     *     "created_at": "2024-01-15T10:30:00Z",
     *     "updated_at": "2024-01-15T10:30:00Z",
     *     "download_stats": {
     *       "total_downloads": 127,
     *       "unique_downloaders": 45,
     *       "last_downloaded": "2024-01-20T14:30:00Z"
     *     }
     *   },
     *   "message": "Product file retrieved successfully.",
     *   "status": 200
     * }
     */
    public function show(Request $request, Product $product, ProductFile $file): JsonResponse
    {
        try {
            // Check permissions
            if (!$request->user()->hasPermission('view_product_files')) {
                return $this->error('Insufficient permissions to view product files.', 403);
            }

            // Check product ownership for vendors
            if (!$this->canAccessProduct($request->user(), $product)) {
                return $this->error('You do not have access to this product.', 403);
            }

            // Verify file belongs to product
            if ($file->product_id !== $product->id) {
                return $this->error('File does not belong to this product.', 404);
            }

            $fileData = $this->fileService->getProductFileDetails($file, $request->user());

            return $this->ok(
                'Product file retrieved successfully.',
                $fileData
            );

        } catch (\Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Upload a new product file
     *
     * Upload a new digital file to a product. Supports various file types with validation,
     * automatic hash generation, and metadata extraction. Files are securely stored and organized.
     *
     * @group Product File Management
     * @authenticated
     *
     * @urlParam product integer required The ID of the product. Example: 1
     *
     * @bodyParam file file required The file to upload (max 500MB). Must be a supported file type.
     * @bodyParam name string optional Custom name for the file (defaults to filename). Example: "Windows Installer v2.1.5"
     * @bodyParam description string optional File description. Example: "Main installation file for Windows systems"
     * @bodyParam version string optional File version. Example: "2.1.5"
     * @bodyParam is_primary boolean optional Set as primary file for product. Example: false
     * @bodyParam download_limit integer optional Download limit for this specific file. Example: 10
     * @bodyParam expires_at string optional File expiration date (ISO format). Example: "2025-12-31T23:59:59Z"
     *
     * @response 201 scenario="File uploaded successfully" {
     *   "data": {
     *     "id": 16,
     *     "name": "Windows Installer v2.1.5",
     *     "original_filename": "projectmanager-v2.1.5.zip",
     *     "file_size": 47185920,
     *     "file_size_formatted": "45.2 MB",
     *     "file_type": "application/zip",
     *     "version": "2.1.5",
     *     "is_primary": false,
     *     "is_active": true,
     *     "download_count": 0,
     *     "upload_progress": "completed",
     *     "created_at": "2024-01-20T15:30:00Z"
     *   },
     *   "message": "Product file uploaded successfully.",
     *   "status": 201
     * }
     *
     * @response 413 scenario="File too large" {
     *   "message": "File size exceeds maximum allowed size of 500MB",
     *   "status": 413
     * }
     *
     * @response 415 scenario="Unsupported file type" {
     *   "message": "File type not allowed: application/exe",
     *   "status": 415
     * }
     */
    public function store(Request $request, Product $product): JsonResponse
    {
        try {
            // Check permissions
            if (!$request->user()->hasPermission('upload_digital_files')) {
                return $this->error('Insufficient permissions to upload product files.', 403);
            }

            // Check product ownership for vendors
            if (!$this->canManageProduct($request->user(), $product)) {
                return $this->error('You do not have permission to upload files to this product.', 403);
            }

            // Validate that this is a digital product
            if (!$product->isDigital()) {
                return $this->error('This product does not support digital files.', 400);
            }

            $fileData = $this->fileService->uploadProductFile($product, $request, $request->user());

            Log::info('Product file uploaded', [
                'product_id' => $product->id,
                'file_id' => $fileData['id'],
                'file_name' => $fileData['name'],
                'file_size' => $fileData['file_size'],
                'uploaded_by' => $request->user()->id
            ]);

            return $this->success(
                'Product file uploaded successfully.',
                $fileData,
                201
            );

        } catch (ValidationException $e) {
            return $this->error($e->errors(), 422);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error($e->getMessage(), $statusCode);
        }
    }

    /**
     * Upload multiple product files
     *
     * Upload multiple files to a product in a single request. Supports batch processing
     * with individual file validation and error reporting.
     *
     * @group Product File Management
     * @authenticated
     *
     * @urlParam product integer required The ID of the product. Example: 1
     *
     * @bodyParam files file[] required Array of files to upload (max 10 files).
     * @bodyParam names string[] optional Array of custom names for each file.
     * @bodyParam descriptions string[] optional Array of descriptions for each file.
     * @bodyParam versions string[] optional Array of versions for each file.
     * @bodyParam is_primary boolean[] optional Array of primary flags for each file.
     *
     * @response 201 scenario="Files uploaded successfully" {
     *   "data": {
     *     "successful_uploads": 3,
     *     "failed_uploads": 0,
     *     "files": [
     *       {
     *         "id": 16,
     *         "name": "Windows Installer",
     *         "file_size_formatted": "45.2 MB",
     *         "status": "uploaded"
     *       },
     *       {
     *         "id": 17,
     *         "name": "macOS Installer",
     *         "file_size_formatted": "42.8 MB",
     *         "status": "uploaded"
     *       },
     *       {
     *         "id": 18,
     *         "name": "User Manual",
     *         "file_size_formatted": "2.1 MB",
     *         "status": "uploaded"
     *       }
     *     ],
     *     "errors": []
     *   },
     *   "message": "3 files uploaded successfully.",
     *   "status": 201
     * }
     */
    public function bulkStore(Request $request, Product $product): JsonResponse
    {
        try {
            // Check permissions
            if (!$request->user()->hasPermission('upload_digital_files')) {
                return $this->error('Insufficient permissions to upload product files.', 403);
            }

            // Check product ownership for vendors
            if (!$this->canManageProduct($request->user(), $product)) {
                return $this->error('You do not have permission to upload files to this product.', 403);
            }

            // Validate that this is a digital product
            if (!$product->isDigital()) {
                return $this->error('This product does not support digital files.', 400);
            }

            $result = $this->fileService->bulkUploadProductFiles($product, $request, $request->user());

            $message = $result['successful_uploads'] > 0
                ? "{$result['successful_uploads']} files uploaded successfully."
                : "No files were uploaded successfully.";

            $statusCode = $result['successful_uploads'] > 0 ? 201 : 400;

            return $this->success($message, $result, $statusCode);

        } catch (ValidationException $e) {
            return $this->error($e->errors(), 422);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Update an existing product file
     *
     * Update metadata and properties of an existing product file. The actual file content
     * cannot be modified - use upload new version instead.
     *
     * @group Product File Management
     * @authenticated
     *
     * @urlParam product integer required The ID of the product. Example: 1
     * @urlParam file integer required The ID of the product file. Example: 15
     *
     * @bodyParam name string optional Updated file name. Example: "Windows Installer v2.1.6"
     * @bodyParam description string optional Updated description. Example: "Latest version with bug fixes"
     * @bodyParam version string optional Updated version. Example: "2.1.6"
     * @bodyParam is_primary boolean optional Set as primary file. Example: true
     * @bodyParam is_active boolean optional Active status. Example: true
     * @bodyParam download_limit integer optional Download limit for this file. Example: 15
     * @bodyParam expires_at string optional File expiration date. Example: "2025-12-31T23:59:59Z"
     *
     * @response 200 scenario="File updated successfully" {
     *   "data": {
     *     "id": 15,
     *     "name": "Windows Installer v2.1.6",
     *     "description": "Latest version with bug fixes",
     *     "version": "2.1.6",
     *     "is_primary": true,
     *     "is_active": true,
     *     "updated_at": "2024-01-20T16:45:00Z"
     *   },
     *   "message": "Product file updated successfully.",
     *   "status": 200
     * }
     */
    public function update(Request $request, Product $product, ProductFile $file): JsonResponse
    {
        try {
            // Check permissions
            if (!$request->user()->hasPermission('edit_product_files')) {
                return $this->error('Insufficient permissions to edit product files.', 403);
            }

            // Check product ownership for vendors
            if (!$this->canManageProduct($request->user(), $product)) {
                return $this->error('You do not have permission to edit files for this product.', 403);
            }

            // Verify file belongs to product
            if ($file->product_id !== $product->id) {
                return $this->error('File does not belong to this product.', 404);
            }

            $updatedFile = $this->fileService->updateProductFile($file, $request, $request->user());

            Log::info('Product file updated', [
                'product_id' => $product->id,
                'file_id' => $file->id,
                'updated_by' => $request->user()->id,
                'changes' => $request->only(['name', 'description', 'version', 'is_primary', 'is_active'])
            ]);

            return $this->ok(
                'Product file updated successfully.',
                $updatedFile
            );

        } catch (ValidationException $e) {
            return $this->error($e->errors(), 422);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Delete a product file
     *
     * Permanently delete a product file. This action cannot be undone and will
     * invalidate any existing download links for this file.
     *
     * @group Product File Management
     * @authenticated
     *
     * @urlParam product integer required The ID of the product. Example: 1
     * @urlParam file integer required The ID of the product file to delete. Example: 15
     *
     * @bodyParam confirm boolean required Confirmation flag to prevent accidental deletion. Example: true
     * @bodyParam reason string optional Reason for deletion (for audit log). Example: "Replaced with newer version"
     *
     * @response 200 scenario="File deleted successfully" {
     *   "data": {
     *     "deleted_file_id": 15,
     *     "deleted_at": "2024-01-20T17:00:00Z",
     *     "reason": "Replaced with newer version"
     *   },
     *   "message": "Product file deleted successfully.",
     *   "status": 200
     * }
     *
     * @response 409 scenario="Cannot delete primary file" {
     *   "message": "Cannot delete the primary file. Set another file as primary first.",
     *   "status": 409
     * }
     */
    public function destroy(Request $request, Product $product, ProductFile $file): JsonResponse
    {
        try {
            // Check permissions
            if (!$request->user()->hasPermission('delete_product_files')) {
                return $this->error('Insufficient permissions to delete product files.', 403);
            }

            // Check product ownership for vendors
            if (!$this->canManageProduct($request->user(), $product)) {
                return $this->error('You do not have permission to delete files from this product.', 403);
            }

            // Verify file belongs to product
            if ($file->product_id !== $product->id) {
                return $this->error('File does not belong to this product.', 404);
            }

            $request->validate([
                'confirm' => 'required|boolean|accepted',
                'reason' => 'nullable|string|max:500'
            ]);

            $reason = $request->input('reason', 'File deleted by user');

            $result = $this->fileService->deleteProductFile($file, $request->user(), $reason);

            Log::warning('Product file deleted', [
                'product_id' => $product->id,
                'file_id' => $file->id,
                'file_name' => $file->name,
                'deleted_by' => $request->user()->id,
                'reason' => $reason
            ]);

            return $this->ok(
                'Product file deleted successfully.',
                [
                    'deleted_file_id' => $file->id,
                    'deleted_at' => now(),
                    'reason' => $reason
                ]
            );

        } catch (ValidationException $e) {
            return $this->error($e->errors(), 422);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Download a product file (admin/vendor access)
     *
     * Direct download of product files for administrators and vendors for testing
     * and verification purposes. Does not count against customer download limits.
     *
     * @group Product File Management
     * @authenticated
     *
     * @urlParam product integer required The ID of the product. Example: 1
     * @urlParam file integer required The ID of the product file. Example: 15
     *
     * @response 200 scenario="File download started" {
     *   "Content-Type": "application/octet-stream",
     *   "Content-Disposition": "attachment; filename=\"projectmanager-v2.1.5.zip\"",
     *   "Content-Length": "47185920"
     * }
     */
    public function download(Request $request, Product $product, ProductFile $file)
    {
        try {
            // Check permissions
            if (!$request->user()->hasPermission('download_digital_files')) {
                return $this->error('Insufficient permissions to download product files.', 403);
            }

            // Check product ownership for vendors
            if (!$this->canAccessProduct($request->user(), $product)) {
                return $this->error('You do not have access to this product.', 403);
            }

            // Verify file belongs to product
            if ($file->product_id !== $product->id) {
                return $this->error('File does not belong to this product.', 404);
            }

            // Check file existence
            if (!$file->exists()) {
                return $this->error('File not found or temporarily unavailable.', 404);
            }

            Log::info('Admin/vendor file download', [
                'product_id' => $product->id,
                'file_id' => $file->id,
                'downloaded_by' => $request->user()->id,
                'user_role' => $request->user()->roles()->pluck('name')->toArray()
            ]);

            return $this->fileService->serveFileForAdmin($file);

        } catch (\Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Check if user can access a product (view permissions)
     */
    protected function canAccessProduct($user, Product $product): bool
    {
        // Admin or manager can access all products
        if ($user->hasPermission('manage_digital_products')) {
            return true;
        }

        // Vendor can access their own products
        if ($user->hasRole('vendor')) {
            $vendorId = $user->vendors()->first()?->id;
            return $product->vendor_id === $vendorId;
        }

        // Customer service can view products for support
        if ($user->hasRole('customer_service') && $user->hasPermission('view_products')) {
            return true;
        }

        return false;
    }

    /**
     * Check if user can manage a product (create/edit/delete permissions)
     */
    protected function canManageProduct($user, Product $product): bool
    {
        // Admin or manager can manage all products
        if ($user->hasPermission('manage_digital_products')) {
            return true;
        }

        // Vendor can manage their own products
        if ($user->hasRole('vendor')) {
            $vendorId = $user->vendors()->first()?->id;
            return $product->vendor_id === $vendorId;
        }

        return false;
    }
}
