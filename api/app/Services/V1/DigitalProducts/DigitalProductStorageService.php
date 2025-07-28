<?php


namespace App\Services\V1\DigitalProducts;

use App\Models\Product;
use App\Models\ProductFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Exception;

class DigitalProductStorageService
{
    protected string $disk = 'private';
    protected string $basePath = 'digital-products';

    public function storeFile(UploadedFile $file, Product $product, array $metadata = []): ProductFile
    {
        try {
            $fileHash = $this->generateFileHash($file);
            $fileName = $this->generateSecureFileName($file, $product);
            $filePath = $this->getFilePath($product, $fileName);

            if (!Storage::disk($this->disk)->putFileAs(
                $this->basePath . '/' . dirname($filePath),
                $file,
                basename($filePath)
            )) {
                throw new Exception('Failed to store file to disk');
            }

            $productFile = ProductFile::create([
                'product_id' => $product->id,
                'name' => $metadata['name'] ?? $file->getClientOriginalName(),
                'original_filename' => $file->getClientOriginalName(),
                'file_path' => $filePath,
                'file_type' => $file->getClientOriginalExtension(),
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'file_hash' => $fileHash,
                'is_primary' => $metadata['is_primary'] ?? false,
                'download_limit' => $metadata['download_limit'] ?? null,
                'metadata' => $metadata,
                'version' => $metadata['version'] ?? '1.0.0',
                'description' => $metadata['description'] ?? null,
            ]);

            $this->ensureOnlyOnePrimary($product, $productFile);

            Log::info('Digital product file stored successfully', [
                'product_id' => $product->id,
                'file_id' => $productFile->id,
                'file_size' => $file->getSize(),
                'file_path' => $filePath
            ]);

            return $productFile;

        } catch (Exception $e) {
            Log::error('Failed to store digital product file', [
                'product_id' => $product->id,
                'error' => $e->getMessage(),
                'file_name' => $file->getClientOriginalName()
            ]);

            throw new Exception('Failed to store digital product file: ' . $e->getMessage());
        }
    }

    public function deleteFile(ProductFile $productFile): bool
    {
        try {
            $fullPath = $this->basePath . '/' . $productFile->file_path;

            if (Storage::disk($this->disk)->exists($fullPath)) {
                Storage::disk($this->disk)->delete($fullPath);
            }

            $productFile->delete();

            Log::info('Digital product file deleted successfully', [
                'file_id' => $productFile->id,
                'file_path' => $productFile->file_path
            ]);

            return true;

        } catch (Exception $e) {
            Log::error('Failed to delete digital product file', [
                'file_id' => $productFile->id,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    public function moveFile(ProductFile $productFile, string $newPath): bool
    {
        try {
            $oldPath = $this->basePath . '/' . $productFile->file_path;
            $newFullPath = $this->basePath . '/' . $newPath;

            if (!Storage::disk($this->disk)->exists($oldPath)) {
                throw new Exception('Source file does not exist');
            }

            if (!Storage::disk($this->disk)->move($oldPath, $newFullPath)) {
                throw new Exception('Failed to move file');
            }

            $productFile->update(['file_path' => $newPath]);

            Log::info('Digital product file moved successfully', [
                'file_id' => $productFile->id,
                'old_path' => $oldPath,
                'new_path' => $newFullPath
            ]);

            return true;

        } catch (Exception $e) {
            Log::error('Failed to move digital product file', [
                'file_id' => $productFile->id,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    public function getFileStream(ProductFile $productFile)
    {
        $fullPath = $this->basePath . '/' . $productFile->file_path;

        if (!Storage::disk($this->disk)->exists($fullPath)) {
            throw new Exception('File not found on disk');
        }

        return Storage::disk($this->disk)->readStream($fullPath);
    }

    public function getFileContent(ProductFile $productFile): string
    {
        $fullPath = $this->basePath . '/' . $productFile->file_path;

        if (!Storage::disk($this->disk)->exists($fullPath)) {
            throw new Exception('File not found on disk');
        }

        return Storage::disk($this->disk)->get($fullPath);
    }

    public function verifyFileIntegrity(ProductFile $productFile): bool
    {
        try {
            $fullPath = $this->basePath . '/' . $productFile->file_path;

            if (!Storage::disk($this->disk)->exists($fullPath)) {
                return false;
            }

            $content = Storage::disk($this->disk)->get($fullPath);
            $currentHash = hash('sha256', $content);

            return $currentHash === $productFile->file_hash;

        } catch (Exception $e) {
            Log::error('Failed to verify file integrity', [
                'file_id' => $productFile->id,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    public function getDiskUsage(Product $product = null): array
    {
        $files = $product
            ? $product->productFiles()
            : ProductFile::query();

        $totalFiles = $files->count();
        $totalSize = $files->sum('file_size');
        $activeFiles = $files->where('is_active', true)->count();
        $activeSize = $files->where('is_active', true)->sum('file_size');

        return [
            'total_files' => $totalFiles,
            'total_size' => $totalSize,
            'total_size_formatted' => $this->formatBytes($totalSize),
            'active_files' => $activeFiles,
            'active_size' => $activeSize,
            'active_size_formatted' => $this->formatBytes($activeSize),
        ];
    }

    protected function generateFileHash(UploadedFile $file): string
    {
        return hash_file('sha256', $file->getPathname());
    }

    protected function generateSecureFileName(UploadedFile $file, Product $product): string
    {
        $extension = $file->getClientOriginalExtension();
        $hash = hash('sha256', $file->getPathname() . $product->id . time());

        return substr($hash, 0, 32) . '.' . $extension;
    }

    protected function getFilePath(Product $product, string $fileName): string
    {
        $vendorId = $product->vendor_id;
        $productId = $product->id;

        return "{$vendorId}/{$productId}/{$fileName}";
    }

    protected function ensureOnlyOnePrimary(Product $product, ProductFile $newPrimary): void
    {
        if ($newPrimary->is_primary) {
            ProductFile::where('product_id', $product->id)
                ->where('id', '!=', $newPrimary->id)
                ->update(['is_primary' => false]);
        }
    }

    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    public function getStorageStats(): array
    {
        $totalFiles = ProductFile::count();
        $totalSize = ProductFile::sum('file_size');
        $activeFiles = ProductFile::where('is_active', true)->count();
        $activeSize = ProductFile::where('is_active', true)->sum('file_size');

        $diskFree = disk_free_space(storage_path('app/private'));
        $diskTotal = disk_total_space(storage_path('app/private'));
        $diskUsed = $diskTotal - $diskFree;

        return [
            'files' => [
                'total' => $totalFiles,
                'active' => $activeFiles,
                'inactive' => $totalFiles - $activeFiles,
            ],
            'size' => [
                'total' => $totalSize,
                'total_formatted' => $this->formatBytes($totalSize),
                'active' => $activeSize,
                'active_formatted' => $this->formatBytes($activeSize),
            ],
            'disk' => [
                'used' => $diskUsed,
                'used_formatted' => $this->formatBytes($diskUsed),
                'free' => $diskFree,
                'free_formatted' => $this->formatBytes($diskFree),
                'total' => $diskTotal,
                'total_formatted' => $this->formatBytes($diskTotal),
                'usage_percentage' => round(($diskUsed / $diskTotal) * 100, 2),
            ]
        ];
    }
}
