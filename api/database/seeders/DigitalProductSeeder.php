<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductStatus;
use App\Models\Vendor;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DigitalProductSeeder extends Seeder
{
    public function run(): void
    {
        // Create digital product categories
        $softwareCategory = ProductCategory::firstOrCreate([
            'name' => 'Software'
        ]);

        $ebooksCategory = ProductCategory::firstOrCreate([
            'name' => 'Digital Books'
        ]);

        $mediaCategory = ProductCategory::firstOrCreate([
            'name' => 'Digital Media'
        ]);

        $templatesCategory = ProductCategory::firstOrCreate([
            'name' => 'Templates & Graphics'
        ]);

        // Get active product status
        $activeStatus = ProductStatus::where('name', 'active')->first();
        if (!$activeStatus) {
            $activeStatus = ProductStatus::create([
                'name' => 'active',
                'description' => 'Product is active and available for purchase'
            ]);
        }

        // Create a vendor user for digital products
        $vendorUser = User::firstOrCreate([
            'email' => 'digital.vendor@example.com'
        ], [
            'name' => 'Digital Products Vendor',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);

        // Assign vendor role
        $vendorRole = \App\Models\Role::where('name', 'vendor')->first();
        if ($vendorRole && !$vendorUser->hasRole('vendor')) {
            $vendorUser->roles()->attach($vendorRole);
        }

        // Create vendor profile
        $vendor = Vendor::firstOrCreate([
            'user_id' => $vendorUser->id
        ], [
            'name' => 'Digital Solutions Store',
            'description' => 'Premium digital products and software solutions for businesses and individuals.',
        ]);

        // Digital Products Data
        $digitalProducts = [
            // Software Products
            [
                'name' => 'ProjectManager Pro',
                'description' => 'Professional project management software with advanced features for teams and enterprises. Includes task tracking, time management, reporting, and collaboration tools.',
                'price' => 9999, // £99.99
                'category_id' => $softwareCategory->id,
                'product_type' => 'digital',
                'requires_license' => true,
                'download_limit' => 3,
                'download_expiry_days' => 365,
                'supported_platforms' => ['Windows', 'macOS', 'Linux'],
                'system_requirements' => [
                    'os' => 'Windows 10/11, macOS 10.14+, Ubuntu 18.04+',
                    'ram' => '4GB minimum, 8GB recommended',
                    'storage' => '2GB available space',
                    'processor' => 'Intel i5 or AMD Ryzen 5 equivalent'
                ],
                'latest_version' => '2.1.5',
                'version_control_enabled' => true,
            ],
            [
                'name' => 'WebDesign Studio',
                'description' => 'Complete web design toolkit with templates, graphics, and development resources. Perfect for designers and developers.',
                'price' => 4999, // £49.99
                'category_id' => $softwareCategory->id,
                'product_type' => 'digital',
                'requires_license' => true,
                'download_limit' => 5,
                'download_expiry_days' => 180,
                'supported_platforms' => ['Windows', 'macOS'],
                'latest_version' => '1.8.2',
            ],
            [
                'name' => 'Mobile App Starter Kit',
                'description' => 'Complete Flutter/React Native starter templates and components for rapid mobile app development.',
                'price' => 7999, // £79.99
                'category_id' => $softwareCategory->id,
                'product_type' => 'digital',
                'requires_license' => false,
                'download_limit' => 10,
                'download_expiry_days' => 90,
                'supported_platforms' => ['Cross-platform'],
                'latest_version' => '3.0.1',
                'version_control_enabled' => true,
            ],

            // E-books
            [
                'name' => 'The Complete Guide to Digital Marketing',
                'description' => 'Comprehensive 500-page guide covering SEO, social media marketing, content strategy, and analytics. Includes bonus templates and checklists.',
                'price' => 2999, // £29.99
                'category_id' => $ebooksCategory->id,
                'product_type' => 'digital',
                'requires_license' => false,
                'download_limit' => 5,
                'download_expiry_days' => 365,
                'supported_platforms' => ['PDF', 'EPUB', 'MOBI'],
                'latest_version' => '2nd Edition',
            ],
            [
                'name' => 'JavaScript Mastery: From Beginner to Expert',
                'description' => 'Learn JavaScript from basics to advanced concepts. 800+ pages with practical examples, exercises, and real-world projects.',
                'price' => 3999, // £39.99
                'category_id' => $ebooksCategory->id,
                'product_type' => 'digital',
                'requires_license' => false,
                'download_limit' => 3,
                'download_expiry_days' => 365,
                'supported_platforms' => ['PDF', 'EPUB'],
                'latest_version' => '4th Edition',
            ],

            // Digital Media
            [
                'name' => 'Professional Stock Photo Collection',
                'description' => 'High-quality stock photos for commercial use. 1000+ images in various categories including business, technology, and lifestyle.',
                'price' => 4999, // £49.99
                'category_id' => $mediaCategory->id,
                'product_type' => 'digital',
                'requires_license' => false,
                'download_limit' => 2,
                'download_expiry_days' => 60,
                'supported_platforms' => ['JPEG', 'PNG', 'RAW'],
                'latest_version' => 'Collection 2024',
            ],
            [
                'name' => 'Royalty-Free Music Pack',
                'description' => 'Professional music tracks for videos, podcasts, and presentations. 50 high-quality tracks in various genres.',
                'price' => 5999, // £59.99
                'category_id' => $mediaCategory->id,
                'product_type' => 'digital',
                'requires_license' => true,
                'download_limit' => 3,
                'download_expiry_days' => 90,
                'supported_platforms' => ['MP3', 'WAV', 'FLAC'],
                'latest_version' => 'Volume 3',
            ],

            // Templates & Graphics
            [
                'name' => 'Business Presentation Templates',
                'description' => 'Professional PowerPoint and Keynote templates for business presentations. 100+ slides with modern designs.',
                'price' => 1999, // £19.99
                'category_id' => $templatesCategory->id,
                'product_type' => 'digital',
                'requires_license' => false,
                'download_limit' => 10,
                'download_expiry_days' => 180,
                'supported_platforms' => ['PowerPoint', 'Keynote', 'Google Slides'],
                'latest_version' => '2024 Edition',
            ],
            [
                'name' => 'Logo Design Mega Pack',
                'description' => 'Professional logo templates and design elements. Vector files included for easy customization.',
                'price' => 3499, // £34.99
                'category_id' => $templatesCategory->id,
                'product_type' => 'digital',
                'requires_license' => false,
                'download_limit' => 5,
                'download_expiry_days' => 365,
                'supported_platforms' => ['AI', 'EPS', 'SVG', 'PNG'],
                'latest_version' => 'V2.0',
            ],

            // Hybrid Product Example
            [
                'name' => 'Complete Web Development Course',
                'description' => 'Physical book + digital resources including video tutorials, code examples, and exclusive online community access.',
                'price' => 7999, // £79.99
                'category_id' => $ebooksCategory->id,
                'product_type' => 'hybrid',
                'requires_shipping' => true,
                'requires_license' => false,
                'download_limit' => 5,
                'download_expiry_days' => 365,
                'supported_platforms' => ['Online Access', 'PDF', 'Video'],
                'latest_version' => '2024 Edition',
                'weight' => 1.2,
                'length' => 25.0,
                'width' => 20.0,
                'height' => 3.0,
            ]
        ];

        // Create the digital products
        foreach ($digitalProducts as $productData) {
            $product = Product::create(array_merge($productData, [
                'vendor_id' => $vendor->id,
                'product_status_id' => $activeStatus->id,
                'quantity' => 999999, // Digital products have unlimited stock
                'low_stock_threshold' => 0,
                'is_virtual' => $productData['product_type'] !== 'physical',
                'auto_deliver' => true,
            ]));

            // Add some sample reviews for digital products
            if (rand(1, 3) === 1) { // 33% chance of having reviews
                $this->createSampleReviews($product);
            }
        }

        $this->command->info('Digital products created successfully!');
        $this->command->info('Vendor created: ' . $vendor->name);
        $this->command->info('Total digital products: ' . count($digitalProducts));
    }

    private function createSampleReviews(Product $product): void
    {
        $sampleUsers = User::take(5)->get();

        if ($sampleUsers->isEmpty()) {
            return;
        }

        $reviews = [
            [
                'rating' => 5,
                'title' => 'Excellent digital product!',
                'content' => 'High quality files and great value for money. Download was instant after purchase.',
            ],
            [
                'rating' => 4,
                'title' => 'Very satisfied',
                'content' => 'Good product with useful content. Documentation could be better but overall happy with the purchase.',
            ],
            [
                'rating' => 5,
                'title' => 'Exactly what I needed',
                'content' => 'Perfect for my project. Files are well organized and the quality is professional.',
            ],
        ];

        foreach ($reviews as $index => $reviewData) {
            if ($index < $sampleUsers->count()) {
                \App\Models\Review::create(array_merge($reviewData, [
                    'user_id' => $sampleUsers[$index]->id,
                    'product_id' => $product->id,
                    'is_verified_purchase' => true,
                    'is_approved' => true,
                ]));
            }
        }

        $product->updateReviewStats();
    }
}
