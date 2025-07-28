<?php

namespace App\Http\Controllers\V1\Public;

use App\Http\Controllers\Controller;
use App\Traits\V1\ApiResponses;
use App\Models\DownloadAccess;
use App\Models\ProductFile;
use App\Models\User;
use App\Services\V1\DigitalProducts\DownloadAccessService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadController extends Controller
{
    use ApiResponses;

    protected DownloadAccessService $downloadAccessService;

    public function __construct(DownloadAccessService $downloadAccessService)
    {
        $this->downloadAccessService = $downloadAccessService;
    }

    /**
     * Download a digital product file
     *
     * Securely download a digital product file using a valid access token. The token is validated
     * for expiry, download limits, and IP restrictions. Downloads are tracked and counted against limits.
     *
     * @group Digital Downloads
     * @authenticated
     *
     * @urlParam token string required The download access token. Example: abc123def456ghi789
     * @queryParam file_id integer optional Specific file ID to download (defaults to primary file). Example: 15
     * @queryParam preview boolean optional Return file info instead of downloading. Example: false
     *
     * @response 200 scenario="File download started" {
     *   "Content-Type": "application/octet-stream",
     *   "Content-Disposition": "attachment; filename=\"ProjectManager-Pro-v2.1.5.zip\"",
     *   "Content-Length": "47185920",
     *   "X-Download-Token": "abc123def456ghi789",
     *   "X-Downloads-Remaining": "2"
     * }
     *
     * @response 200 scenario="File preview mode" {
     *   "data": {
     *     "file": {
     *       "id": 15,
     *       "name": "ProjectManager Pro - Windows Installer",
     *       "file_size": "45.2 MB",
     *       "file_type": "application/zip",
     *       "version": "2.1.5",
     *       "description": "Main Windows installation file"
     *     },
     *     "access": {
     *       "downloads_remaining": 2,
     *       "expires_at": "2024-12-15T23:59:59Z",
     *       "status": "active"
     *     },
     *     "product": {
     *       "id": 1,
     *       "name": "ProjectManager Pro",
     *       "latest_version": "2.1.5"
     *     }
     *   },
     *   "message": "Download preview retrieved successfully.",
     *   "status": 200
     * }
     *
     * @response 404 scenario="Invalid token" {
     *   "message": "Invalid download token",
     *   "status": 404
     * }
     *
     * @response 403 scenario="Access denied" {
     *   "message": "Download access invalid: Token has expired",
     *   "status": 403
     * }
     *
     * @response 429 scenario="Download limit exceeded" {
     *   "message": "Download limit exceeded",
     *   "status": 429
     * }
     */
    public function download(Request $request, string $token)
    {
        try {
            // Check user permissions
            if (!$request->user()->hasPermission('download_digital_files')) {
                return $this->error('Insufficient permissions to download digital files.', 403);
            }

            // Validate download access
            $downloadAccess = $this->downloadAccessService->validateAccess($token, $request);

            // Verify user owns this download access or has admin permissions
            if (!$this->canAccessDownload($request->user(), $downloadAccess)) {
                return $this->error('You do not have access to this download.', 403);
            }

            // Get the file to download
            $fileId = $request->get('file_id');
            $productFile = $this->getProductFile($downloadAccess, $fileId);

            // Preview mode - return file info without downloading
            if ($request->boolean('preview')) {
                return $this->getDownloadPreview($downloadAccess, $productFile);
            }

            // Check file existence
            if (!$productFile->exists()) {
                Log::error('Product file not found on disk', [
                    'file_id' => $productFile->id,
                    'file_path' => $productFile->file_path,
                    'access_token' => $token,
                    'user_id' => $request->user()->id
                ]);
                return $this->error('File not found or temporarily unavailable.', 404);
            }

            // Record download attempt
            $downloadAttempt = $this->downloadAccessService->recordDownloadAttempt(
                $downloadAccess,
                $productFile,
                $request
            );

            // Serve the file
            return $this->serveFile($productFile, $downloadAccess, $downloadAttempt);

        } catch (\Exception $e) {
            $statusCode = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;

            Log::warning('Download failed', [
                'token' => $token,
                'error' => $e->getMessage(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'user_id' => $request->user()->id
            ]);

            return $this->error($e->getMessage(), $statusCode);
        }
    }

    /**
     * Get download access information
     *
     * Retrieve information about download access without initiating a download.
     * Useful for checking remaining downloads, expiry, and available files.
     *
     * @group Digital Downloads
     * @authenticated
     *
     * @urlParam token string required The download access token. Example: abc123def456ghi789
     *
     * @response 200 scenario="Access info retrieved successfully" {
     *   "data": {
     *     "access": {
     *       "token": "abc123def456ghi789",
     *       "status": "active",
     *       "download_limit": 5,
     *       "downloads_used": 3,
     *       "downloads_remaining": 2,
     *       "expires_at": "2024-12-15T23:59:59Z",
     *       "first_downloaded_at": "2024-01-15T10:30:00Z",
     *       "last_downloaded_at": "2024-01-20T14:45:00Z"
     *     },
     *     "product": {
     *       "id": 1,
     *       "name": "ProjectManager Pro",
     *       "latest_version": "2.1.5",
     *       "product_type": "digital"
     *     },
     *     "files": [
     *       {
     *         "id": 15,
     *         "name": "Windows Installer",
     *         "file_size_formatted": "45.2 MB",
     *         "version": "2.1.5",
     *         "is_primary": true,
     *         "download_url": "/api/v1/download/abc123def456ghi789?file_id=15"
     *       },
     *       {
     *         "id": 16,
     *         "name": "macOS Installer",
     *         "file_size_formatted": "42.8 MB",
     *         "version": "2.1.5",
     *         "is_primary": false,
     *         "download_url": "/api/v1/download/abc123def456ghi789?file_id=16"
     *       }
     *     ],
     *     "license": {
     *       "key": "PROJ-XXXX-XXXX-XXXX",
     *       "type": "single_use",
     *       "status": "active",
     *       "activations_remaining": 1
     *     }
     *   },
     *   "message": "Download access information retrieved successfully.",
     *   "status": 200
     * }
     */
    public function info(Request $request, string $token): JsonResponse
    {
        try {
            // Check user permissions
            if (!$request->user()->hasPermission('view_download_access')) {
                return $this->error('Insufficient permissions to view download access information.', 403);
            }

            $downloadAccess = $this->downloadAccessService->validateAccess($token, $request);

            // Verify user owns this download access or has admin permissions
            if (!$this->canAccessDownload($request->user(), $downloadAccess)) {
                return $this->error('You do not have access to this download information.', 403);
            }

            $accessInfo = [
                'access' => [
                    'token' => $downloadAccess->access_token,
                    'status' => $downloadAccess->status,
                    'download_limit' => $downloadAccess->download_limit,
                    'downloads_used' => $downloadAccess->downloads_used,
                    'downloads_remaining' => $downloadAccess->getRemainingDownloads(),
                    'expires_at' => $downloadAccess->expires_at,
                    'first_downloaded_at' => $downloadAccess->first_downloaded_at,
                    'last_downloaded_at' => $downloadAccess->last_downloaded_at,
                ],
                'product' => [
                    'id' => $downloadAccess->product->id,
                    'name' => $downloadAccess->product->name,
                    'latest_version' => $downloadAccess->product->latest_version,
                    'product_type' => $downloadAccess->product->product_type,
                ],
                'files' => $downloadAccess->product->activeProductFiles->map(function ($file) use ($token) {
                    return [
                        'id' => $file->id,
                        'name' => $file->name,
                        'file_size_formatted' => $file->file_size_formatted,
                        'version' => $file->version,
                        'is_primary' => $file->is_primary,
                        'download_url' => route('download.file', ['token' => $token]) . "?file_id={$file->id}",
                    ];
                }),
            ];

            // Add license information if applicable
            if ($downloadAccess->product->requiresLicense()) {
                $license = $downloadAccess->order->licenseKeys()
                    ->where('product_id', $downloadAccess->product_id)
                    ->first();

                if ($license) {
                    $accessInfo['license'] = [
                        'key' => $license->license_key,
                        'type' => $license->type,
                        'status' => $license->status,
                        'activations_remaining' => $license->getRemainingActivations(),
                    ];
                }
            }

            return $this->ok(
                'Download access information retrieved successfully.',
                $accessInfo
            );

        } catch (\Exception $e) {
            $statusCode = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error($e->getMessage(), $statusCode);
        }
    }

    /**
     * Track download progress
     *
     * Update download progress for monitoring and analytics. Called by download clients
     * to report download progress and handle interruptions.
     *
     * @group Digital Downloads
     * @authenticated
     *
     * @urlParam token string required The download access token. Example: abc123def456ghi789
     * @urlParam attempt_id integer required The download attempt ID. Example: 123
     *
     * @bodyParam bytes_downloaded integer required Bytes downloaded so far. Example: 15728640
     * @bodyParam status string required Current status (downloading, completed, failed). Example: downloading
     * @bodyParam error_message string optional Error message if status is failed. Example: "Connection timeout"
     *
     * @response 200 scenario="Progress updated successfully" {
     *   "data": {
     *     "attempt_id": 123,
     *     "progress_percentage": 33.5,
     *     "bytes_downloaded": 15728640,
     *     "total_file_size": 47185920,
     *     "status": "downloading"
     *   },
     *   "message": "Download progress updated successfully.",
     *   "status": 200
     * }
     */
    public function updateProgress(Request $request, string $token, int $attemptId): JsonResponse
    {
        try {
            // Check user permissions
            if (!$request->user()->hasPermission('download_digital_files')) {
                return $this->error('Insufficient permissions to update download progress.', 403);
            }

            $downloadAccess = $this->downloadAccessService->validateAccess($token, $request);

            // Verify user owns this download access or has admin permissions
            if (!$this->canAccessDownload($request->user(), $downloadAccess)) {
                return $this->error('You do not have access to update this download progress.', 403);
            }

            $downloadAttempt = $downloadAccess->downloadAttempts()
                ->where('id', $attemptId)
                ->firstOrFail();

            $bytesDownloaded = $request->integer('bytes_downloaded');
            $status = $request->input('status');
            $errorMessage = $request->input('error_message');

            // Update progress
            $downloadAttempt->updateProgress($bytesDownloaded);

            // Handle status changes
            if ($status === 'completed') {
                $this->downloadAccessService->markDownloadComplete($downloadAccess, $downloadAttempt);
            } elseif ($status === 'failed') {
                $this->downloadAccessService->markDownloadFailed($downloadAttempt, $errorMessage ?? 'Download failed');
            }

            return $this->ok(
                'Download progress updated successfully.',
                [
                    'attempt_id' => $downloadAttempt->id,
                    'progress_percentage' => $downloadAttempt->progress_percentage,
                    'bytes_downloaded' => $downloadAttempt->bytes_downloaded,
                    'total_file_size' => $downloadAttempt->total_file_size,
                    'status' => $downloadAttempt->status,
                ]
            );

        } catch (\Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Get the appropriate product file for download
     */
    protected function getProductFile(DownloadAccess $downloadAccess, ?int $fileId): ProductFile
    {
        if ($fileId) {
            return $downloadAccess->product->productFiles()
                ->where('id', $fileId)
                ->where('is_active', true)
                ->firstOrFail();
        }

        // Default to primary file
        $primaryFile = $downloadAccess->product->primaryProductFile;
        if ($primaryFile && $primaryFile->is_active) {
            return $primaryFile;
        }

        // Fallback to first active file
        $firstFile = $downloadAccess->product->activeProductFiles()->first();
        if (!$firstFile) {
            throw new \Exception('No downloadable files available for this product.', 404);
        }

        return $firstFile;
    }

    /**
     * Get download preview information
     */
    protected function getDownloadPreview(DownloadAccess $downloadAccess, ProductFile $productFile): JsonResponse
    {
        $previewData = [
            'file' => [
                'id' => $productFile->id,
                'name' => $productFile->name,
                'file_size' => $productFile->file_size_formatted,
                'file_type' => $productFile->mime_type,
                'version' => $productFile->version,
                'description' => $productFile->description,
            ],
            'access' => [
                'downloads_remaining' => $downloadAccess->getRemainingDownloads(),
                'expires_at' => $downloadAccess->expires_at,
                'status' => $downloadAccess->status,
            ],
            'product' => [
                'id' => $downloadAccess->product->id,
                'name' => $downloadAccess->product->name,
                'latest_version' => $downloadAccess->product->latest_version,
            ],
        ];

        return $this->ok(
            'Download preview retrieved successfully.',
            $previewData
        );
    }

    /**
     * Check if user can access a specific download
     */
    protected function canAccessDownload(User $user, DownloadAccess $downloadAccess): bool
    {
        // User owns the download access
        if ($downloadAccess->user_id === $user->id) {
            return true;
        }

        // Admin or manager can access all downloads
        if ($user->hasPermission('manage_download_access')) {
            return true;
        }

        // Vendor can access downloads for their products
        if ($user->hasRole('vendor')) {
            $vendorId = $user->vendors()->first()?->id;
            return $downloadAccess->product->vendor_id === $vendorId;
        }

        // Customer service can view downloads for support
        if ($user->hasRole('customer_service') && $user->hasPermission('view_customer_data')) {
            return true;
        }

        return false;
    }

    /**
     * Serve the file for download
     */
    protected function serveFile(ProductFile $productFile, DownloadAccess $downloadAccess, $downloadAttempt): StreamedResponse
    {
        $filePath = $productFile->getFullPath();
        $fileName = $productFile->original_filename;

        return response()->stream(
            function () use ($filePath, $downloadAccess, $downloadAttempt) {
                $stream = fopen($filePath, 'rb');
                $chunkSize = 8192; // 8KB chunks
                $bytesDownloaded = 0;

                while (!feof($stream)) {
                    $chunk = fread($stream, $chunkSize);
                    echo $chunk;

                    $bytesDownloaded += strlen($chunk);

                    // Update progress periodically
                    if ($bytesDownloaded % (1024 * 1024) === 0) { // Every 1MB
                        $downloadAttempt->updateProgress($bytesDownloaded);
                    }

                    if (connection_aborted()) {
                        fclose($stream);
                        $this->downloadAccessService->markDownloadFailed(
                            $downloadAttempt,
                            'Connection aborted by client'
                        );
                        break;
                    }
                }

                fclose($stream);

                // Mark as completed if we reached here successfully
                if (!connection_aborted()) {
                    $this->downloadAccessService->markDownloadComplete($downloadAccess, $downloadAttempt);
                }
            },
            200,
            [
                'Content-Type' => $productFile->mime_type,
                'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
                'Content-Length' => $productFile->file_size,
                'X-Download-Token' => $downloadAccess->access_token,
                'X-Downloads-Remaining' => $downloadAccess->getRemainingDownloads(),
                'Accept-Ranges' => 'bytes',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]
        );
    }
}
