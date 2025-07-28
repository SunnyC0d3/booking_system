<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductFile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class ProductFileSeeder extends Seeder
{
    public function run(): void
    {
        // Get digital products
        $digitalProducts = Product::whereIn('product_type', ['digital', 'hybrid'])->get();

        if ($digitalProducts->isEmpty()) {
            $this->command->warn('No digital products found. Run DigitalProductSeeder first.');
            return;
        }

        // Ensure private storage directory exists
        if (!Storage::disk('private')->exists('digital-products')) {
            Storage::disk('private')->makeDirectory('digital-products');
        }

        foreach ($digitalProducts as $product) {
            $this->createProductFiles($product);
        }

        $this->command->info('Product files created successfully!');
        $this->command->info('Total digital products processed: ' . $digitalProducts->count());
    }

    private function createProductFiles(Product $product): void
    {
        $vendorId = $product->vendor_id;
        $productId = $product->id;
        $basePath = "digital-products/{$vendorId}/{$productId}";

        // Ensure product directory exists
        if (!Storage::disk('private')->exists($basePath)) {
            Storage::disk('private')->makeDirectory($basePath);
        }

        // Define file types based on product category
        $files = $this->getFilesForProduct($product);

        foreach ($files as $fileData) {
            $this->createSampleFile($product, $fileData, $basePath);
        }
    }

    private function getFilesForProduct(Product $product): array
    {
        $categoryName = $product->category?->name;

        return match($categoryName) {
            'Software' => [
                [
                    'name' => $product->name . ' - Windows Installer',
                    'filename' => 'installer-windows.exe',
                    'content' => 'Mock Windows installer executable',
                    'is_primary' => true,
                    'version' => $product->latest_version ?? '1.0.0',
                    'description' => 'Main Windows installation file',
                ],
                [
                    'name' => $product->name . ' - macOS Installer',
                    'filename' => 'installer-macos.dmg',
                    'content' => 'Mock macOS installer disk image',
                    'is_primary' => false,
                    'version' => $product->latest_version ?? '1.0.0',
                    'description' => 'macOS installation file',
                ],
                [
                    'name' => 'User Manual',
                    'filename' => 'user-manual.pdf',
                    'content' => 'Mock PDF user manual content',
                    'is_primary' => false,
                    'version' => '1.0',
                    'description' => 'Complete user guide and documentation',
                ],
                [
                    'name' => 'License Agreement',
                    'filename' => 'license.txt',
                    'content' => 'Software License Agreement - This is a mock license file for demonstration purposes.',
                    'is_primary' => false,
                    'version' => '1.0',
                    'description' => 'Software license terms and conditions',
                ],
            ],
            'Digital Books' => [
                [
                    'name' => $product->name . ' - PDF Version',
                    'filename' => 'book.pdf',
                    'content' => 'Mock PDF book content - This would be the actual book content in PDF format.',
                    'is_primary' => true,
                    'version' => $product->latest_version ?? '1.0',
                    'description' => 'Main book in PDF format',
                ],
                [
                    'name' => $product->name . ' - EPUB Version',
                    'filename' => 'book.epub',
                    'content' => 'Mock EPUB book content',
                    'is_primary' => false,
                    'version' => $product->latest_version ?? '1.0',
                    'description' => 'E-reader compatible EPUB format',
                ],
                [
                    'name' => 'Bonus Resources',
                    'filename' => 'bonus-resources.zip',
                    'content' => 'Mock bonus resources archive',
                    'is_primary' => false,
                    'version' => '1.0',
                    'description' => 'Additional templates, checklists, and resources',
                ],
            ],
            'Digital Media' => [
                [
                    'name' => $product->name . ' - High Resolution Pack',
                    'filename' => 'media-pack-hd.zip',
                    'content' => 'Mock high resolution media files archive',
                    'is_primary' => true,
                    'version' => $product->latest_version ?? '1.0',
                    'description' => 'High resolution media files',
                ],
                [
                    'name' => 'Web Optimized Pack',
                    'filename' => 'media-pack-web.zip',
                    'content' => 'Mock web optimized media files',
                    'is_primary' => false,
                    'version' => '1.0',
                    'description' => 'Web-optimized versions for online use',
                ],
                [
                    'name' => 'Usage License',
                    'filename' => 'usage-license.pdf',
                    'content' => 'Mock usage license document',
                    'is_primary' => false,
                    'version' => '1.0',
                    'description' => 'Commercial usage terms and licensing',
                ],
            ],
            'Templates & Graphics' => [
                [
                    'name' => $product->name . ' - All Formats',
                    'filename' => 'templates-complete.zip',
                    'content' => 'Mock template files archive',
                    'is_primary' => true,
                    'version' => $product->latest_version ?? '1.0',
                    'description' => 'Complete template collection in all formats',
                ],
                [
                    'name' => 'Vector Source Files',
                    'filename' => 'vector-sources.zip',
                    'content' => 'Mock vector source files',
                    'is_primary' => false,
                    'version' => '1.0',
                    'description' => 'Editable vector source files (AI, EPS, SVG)',
                ],
                [
                    'name' => 'Quick Start Guide',
                    'filename' => 'quick-start-guide.pdf',
                    'content' => 'Mock quick start guide content',
                    'is_primary' => false,
                    'version' => '1.0',
                    'description' => 'How to use and customize the templates',
                ],
            ],
            default => [
                [
                    'name' => $product->name . ' - Main File',
                    'filename' => 'main-file.zip',
                    'content' => 'Mock digital product file content',
                    'is_primary' => true,
                    'version' => $product->latest_version ?? '1.0',
                    'description' => 'Main product download',
                ],
            ]
        };
    }

    private function createSampleFile(Product $product, array $fileData, string $basePath): void
    {
        // Generate file content
        $content = $this->generateMockFileContent($fileData);

        // Create unique filename
        $uniqueFilename = $this->generateUniqueFilename($fileData['filename'], $product);
        $filePath = "{$basePath}/{$uniqueFilename}";

        // Store file
        Storage::disk('private')->put($filePath, $content);

        // Get file info
        $fileSize = strlen($content);
        $fileHash = hash('sha256', $content);
        $mimeType = $this->getMimeType($fileData['filename']);
        $extension = pathinfo($fileData['filename'], PATHINFO_EXTENSION);

        // Create ProductFile record
        ProductFile::create([
            'product_id' => $product->id,
            'name' => $fileData['name'],
            'original_filename' => $fileData['filename'],
            'file_path' => str_replace('digital-products/', '', $filePath),
            'file_type' => $extension,
            'mime_type' => $mimeType,
            'file_size' => $fileSize,
            'file_hash' => $fileHash,
            'is_primary' => $fileData['is_primary'],
            'is_active' => true,
            'download_limit' => null,
            'download_count' => rand(0, 50),
            'version' => $fileData['version'],
            'description' => $fileData['description'],
            'metadata' => [
                'sample_file' => true,
                'created_by_seeder' => true,
                'created_at' => now()->toISOString(),
            ],
        ]);
    }

    private function generateMockFileContent(array $fileData): string
    {
        $extension = pathinfo($fileData['filename'], PATHINFO_EXTENSION);

        return match($extension) {
            'txt' => $fileData['content'],
            'pdf' => '%PDF-1.4' . "\n" . $fileData['content'] . "\n" . '%%EOF',
            'zip' => 'PK' . pack('v*', 0x0403, 0x0014, 0x0000, 0x0008) . $fileData['content'],
            'exe' => 'MZ' . str_repeat("\x00", 58) . "\x3c\x00\x00\x00" . $fileData['content'],
            'dmg' => 'koly' . str_repeat("\x00", 508) . $fileData['content'],
            'epub' => 'PK' . pack('v*', 0x0403, 0x0014, 0x0000, 0x0000) . 'mimetypeapplication/epub+zip' . $fileData['content'],
            default => $fileData['content'] . ' - Mock file content for demonstration purposes.'
        };
    }

    private function generateUniqueFilename(string $originalFilename, Product $product): string
    {
        $extension = pathinfo($originalFilename, PATHINFO_EXTENSION);
        $hash = hash('sha256', $originalFilename . $product->id . time() . rand());

        return substr($hash, 0, 32) . '.' . $extension;
    }

    private function getMimeType(string $filename): string
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return match($extension) {
            'pdf' => 'application/pdf',
            'zip' => 'application/zip',
            'exe' => 'application/octet-stream',
            'dmg' => 'application/octet-stream',
            'txt' => 'text/plain',
            'epub' => 'application/epub+zip',
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'mp4' => 'video/mp4',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            default => 'application/octet-stream'
        };
    }
}
