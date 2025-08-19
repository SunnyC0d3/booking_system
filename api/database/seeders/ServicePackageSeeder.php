<?php

namespace Database\Seeders;

use App\Models\ServicePackage;
use App\Models\Service;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ServicePackageSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $services = Service::where('is_active', true)->get();

            if ($services->isEmpty()) {
                return;
            }

            $this->createWeddingPackage();
            $this->createBirthdayPackage();
            $this->createCorporatePackage();
            $this->createBabyShowerPackage();
            $this->createAnniversaryPackage();
            $this->createGraduationPackage();
            $this->createHolidayPackage();

            ServicePackage::factory()
                ->count(3)
                ->create()
                ->each(function ($package) use ($services) {
                    $this->attachRandomServices($package, $services);
                });
        });
    }

    private function createWeddingPackage(): void
    {
        $package = ServicePackage::create([
            'name' => 'Wedding Complete Package',
            'description' => 'Everything you need for your perfect wedding day. Our most comprehensive package includes stunning balloon arches, elegant table centerpieces, romantic photo backdrop, and complete venue decoration. Professional setup and breakdown included.',
            'short_description' => 'Complete wedding decoration package with everything included',
            'total_price' => 45000, // £450
            'is_active' => true,
            'requires_consultation' => true,
            'consultation_duration_minutes' => 60,
            'max_advance_booking_days' => 180,
            'min_advance_booking_hours' => 72,
            'cancellation_policy' => 'Wedding packages require 72 hours notice for cancellation. Cancellations within 72 hours will incur a 25% fee.',
            'sort_order' => 1,
            'metadata' => [
                'featured' => true,
                'popular' => true,
                'category' => 'wedding',
                'includes' => [
                    'Bridal arch',
                    'Table centerpieces (up to 10 tables)',
                    'Photo backdrop',
                    'Aisle decorations',
                    'Professional setup',
                    'Breakdown service',
                    'Consultation included'
                ],
                'wedding_styles' => ['Traditional', 'Modern', 'Rustic', 'Romantic']
            ]
        ]);

        // Add core services (required) - using actual service names from ServiceSeeder
        $this->attachServiceToPackage($package, 'Balloon Arch Design', 1, 1, false);
        $this->attachServiceToPackage($package, 'Table Centerpieces', 8, 2, false);
        $this->attachServiceToPackage($package, 'Event Backdrop Design', 1, 3, false);

        // Add optional services
        $this->attachServiceToPackage($package, 'Design Consultation', 1, 4, true);

        $this->calculatePackagePricing($package, 15); // 15% discount
    }

    private function createBirthdayPackage(): void
    {
        $package = ServicePackage::create([
            'name' => 'Birthday Party Deluxe',
            'description' => 'Make your birthday celebration unforgettable! Includes colorful balloon arrangements, themed party backdrop, and custom decorations to match your party theme.',
            'short_description' => 'Deluxe birthday party decoration package',
            'total_price' => 18000, // £180
            'is_active' => true,
            'requires_consultation' => false,
            'max_advance_booking_days' => 60,
            'min_advance_booking_hours' => 24,
            'sort_order' => 2,
            'metadata' => [
                'category' => 'birthday',
                'age_groups' => ['kids', 'teens', 'adults'],
                'themes_available' => ['cartoon', 'superhero', 'princess', 'sports', 'custom']
            ]
        ]);

        $this->attachServiceToPackage($package, 'Balloon Arch Design', 1, 1, false);
        $this->attachServiceToPackage($package, 'Table Centerpieces', 3, 2, false);
        $this->attachServiceToPackage($package, 'Balloon Garland Installation', 1, 3, true);

        $this->calculatePackagePricing($package, 10); // 10% discount
    }

    private function createCorporatePackage(): void
    {
        $package = ServicePackage::create([
            'name' => 'Corporate Event Package',
            'description' => 'Professional and elegant decoration solution for corporate gatherings, conferences, and business celebrations. Sophisticated designs that reflect your brand.',
            'short_description' => 'Professional corporate event decorations',
            'total_price' => 32000, // £320
            'is_active' => true,
            'requires_consultation' => true,
            'consultation_duration_minutes' => 45,
            'max_advance_booking_days' => 90,
            'min_advance_booking_hours' => 48,
            'sort_order' => 3,
            'metadata' => [
                'category' => 'corporate',
                'suitable_for' => ['conferences', 'product launches', 'corporate parties', 'award ceremonies'],
                'customizable' => true,
                'brand_colors' => true
            ]
        ]);

        $this->attachServiceToPackage($package, 'Design Consultation', 1, 1, false);
        $this->attachServiceToPackage($package, 'Table Centerpieces', 6, 2, false);
        $this->attachServiceToPackage($package, 'Event Backdrop Design', 1, 3, true);

        $this->calculatePackagePricing($package, 12); // 12% discount
    }

    private function createBabyShowerPackage(): void
    {
        $package = ServicePackage::create([
            'name' => 'Baby Shower Bliss',
            'description' => 'Celebrate the upcoming arrival with our beautiful baby shower decoration package. Featuring soft pastels, themed arrangements, and adorable details perfect for this special occasion.',
            'short_description' => 'Adorable baby shower decoration package',
            'total_price' => 15000, // £150
            'is_active' => true,
            'requires_consultation' => false,
            'max_advance_booking_days' => 45,
            'min_advance_booking_hours' => 24,
            'sort_order' => 4,
            'metadata' => [
                'category' => 'baby_shower',
                'themes' => ['boy', 'girl', 'neutral', 'safari', 'cloud', 'floral'],
                'color_schemes' => ['pink/gold', 'blue/silver', 'mint/gold', 'neutral/gold']
            ]
        ]);

        $this->attachServiceToPackage($package, 'Balloon Arch Design', 1, 1, false);
        $this->attachServiceToPackage($package, 'Table Centerpieces', 4, 2, false);
        $this->attachServiceToPackage($package, 'Balloon Bouquet Delivery', 1, 3, true);

        $this->calculatePackagePricing($package, 8); // 8% discount
    }

    private function createAnniversaryPackage(): void
    {
        $package = ServicePackage::create([
            'name' => 'Anniversary Elegance',
            'description' => 'Romantic and elegant decorations perfect for anniversary celebrations. Features sophisticated balloon arrangements, ambient lighting effects, and intimate table settings.',
            'short_description' => 'Elegant anniversary celebration decorations',
            'total_price' => 22000, // £220
            'is_active' => true,
            'requires_consultation' => false,
            'max_advance_booking_days' => 60,
            'min_advance_booking_hours' => 48,
            'sort_order' => 5,
            'metadata' => [
                'category' => 'anniversary',
                'romantic_themes' => ['classic', 'vintage', 'modern', 'garden'],
                'anniversary_milestones' => ['1st', '5th', '10th', '25th', '50th'],
                'special_touches' => ['candles', 'flowers', 'personalized elements']
            ]
        ]);

        $this->attachServiceToPackage($package, 'Table Centerpieces', 2, 1, false);
        $this->attachServiceToPackage($package, 'Balloon Garland Installation', 1, 2, false);
        $this->attachServiceToPackage($package, 'Balloon Arch Design', 1, 3, true);

        $this->calculatePackagePricing($package, 10); // 10% discount
    }

    private function createGraduationPackage(): void
    {
        $package = ServicePackage::create([
            'name' => 'Graduation Celebration',
            'description' => 'Honor this momentous achievement with our graduation celebration package. Features school colors, diploma-themed decorations, and celebratory balloon arrangements.',
            'short_description' => 'Academic achievement celebration decorations',
            'total_price' => 20000, // £200
            'is_active' => true,
            'requires_consultation' => false,
            'max_advance_booking_days' => 60,
            'min_advance_booking_hours' => 48,
            'sort_order' => 6,
            'metadata' => [
                'category' => 'graduation',
                'education_levels' => ['high_school', 'college', 'university', 'graduate'],
                'school_colors' => true,
                'diploma_themed' => true
            ]
        ]);

        $this->attachServiceToPackage($package, 'Balloon Arch Design', 1, 1, false);
        $this->attachServiceToPackage($package, 'Table Centerpieces', 3, 2, false);
        $this->attachServiceToPackage($package, 'Balloon Bouquet Delivery', 1, 3, true);

        $this->calculatePackagePricing($package, 8); // 8% discount
    }

    private function createHolidayPackage(): void
    {
        $package = ServicePackage::create([
            'name' => 'Holiday Spectacular',
            'description' => 'Transform your space for the holidays with seasonal decorations, festive balloon arrangements, and themed centerpieces that capture the spirit of the season.',
            'short_description' => 'Seasonal holiday decoration package',
            'total_price' => 28000, // £280
            'is_active' => true,
            'requires_consultation' => false,
            'max_advance_booking_days' => 90,
            'min_advance_booking_hours' => 72,
            'sort_order' => 7,
            'metadata' => [
                'category' => 'holiday',
                'seasonal_themes' => ['christmas', 'new_year', 'easter', 'halloween'],
                'seasonal_only' => true,
                'includes_lighting' => true
            ]
        ]);

        $this->attachServiceToPackage($package, 'Balloon Arch Design', 2, 1, false);
        $this->attachServiceToPackage($package, 'Table Centerpieces', 5, 2, false);
        $this->attachServiceToPackage($package, 'Balloon Garland Installation', 1, 3, true);

        $this->calculatePackagePricing($package, 12); // 12% discount
    }

    private function attachServiceToPackage($package, $serviceName, $quantity, $order, $isOptional): void
    {
        // Try exact match first
        $service = Service::where('name', $serviceName)->first();

        // If no exact match, try partial match
        if (!$service) {
            $service = Service::where('name', 'like', "%{$serviceName}%")->first();
        }

        if ($service) {
            try {
                $package->services()->attach($service->id, [
                    'quantity' => $quantity,
                    'order' => $order,
                    'is_optional' => $isOptional,
                    'notes' => $isOptional ? 'Optional upgrade' : null,
                ]);

                Log::info("Attached service to package", [
                    'package' => $package->name,
                    'service' => $service->name,
                    'quantity' => $quantity,
                    'is_optional' => $isOptional
                ]);
            } catch (\Exception $e) {
                $this->command->error("Failed to attach service '{$service->name}' to package '{$package->name}': " . $e->getMessage());
                Log::error("Service attachment failed", [
                    'package' => $package->name,
                    'service' => $service->name,
                    'error' => $e->getMessage()
                ]);
            }
        } else {
            $this->command->warn("Service '{$serviceName}' not found, skipping attachment to {$package->name}");

            // Log available services for debugging
            $availableServices = Service::pluck('name')->toArray();
            $this->command->info("Available services: " . implode(', ', $availableServices));
            Log::warning("Service not found", [
                'requested_service' => $serviceName,
                'available_services' => $availableServices
            ]);
        }
    }

    private function attachRandomServices($package, $services): void
    {
        $serviceCount = rand(2, 4);
        $selectedServices = $services->random($serviceCount);

        foreach ($selectedServices as $index => $service) {
            $isOptional = $index >= 2; // First 2 are required, rest optional
            $quantity = $isOptional ? 1 : rand(1, 3);

            $package->services()->attach($service->id, [
                'quantity' => $quantity,
                'order' => $index + 1,
                'is_optional' => $isOptional,
                'notes' => $isOptional ? 'Optional service' : null,
            ]);
        }

        // Apply random discount
        $discountPercentage = rand(5, 20);
        $this->calculatePackagePricing($package, $discountPercentage);
    }

    private function calculatePackagePricing($package, $discountPercentage = 0): void
    {
        // Refresh the package to get the latest relationships
        $package->refresh();

        $totalPrice = 0;
        $totalDuration = 0;

        // Load services with pivot data
        $services = $package->services()->get();

        if ($services->isEmpty()) {
            $this->command->warn("No services attached to package '{$package->name}', skipping pricing calculation");
            return;
        }

        foreach ($services as $service) {
            $quantity = $service->pivot->quantity ?? 1;
            $totalPrice += $service->base_price * $quantity;
            $totalDuration += $service->duration_minutes * $quantity;
        }

        $discountAmount = 0;
        $finalPrice = $totalPrice;

        if ($discountPercentage > 0 && $totalPrice > 0) {
            $discountAmount = (int) round($totalPrice * ($discountPercentage / 100));
            $finalPrice = $totalPrice - $discountAmount;
        }

        try {
            $package->update([
                'individual_price_total' => $totalPrice,
                'total_duration_minutes' => $totalDuration,
                'discount_percentage' => $discountPercentage > 0 ? $discountPercentage : null,
                'discount_amount' => $discountAmount,
                'total_price' => $finalPrice,
            ]);

            Log::info("Calculated package pricing", [
                'package' => $package->name,
                'individual_total' => $totalPrice,
                'discount_percentage' => $discountPercentage,
                'discount_amount' => $discountAmount,
                'final_price' => $finalPrice,
                'duration_minutes' => $totalDuration,
                'services_count' => $services->count()
            ]);
        } catch (\Exception $e) {
            $this->command->error("Failed to update pricing for package '{$package->name}': " . $e->getMessage());
            Log::error("Package pricing calculation failed", [
                'package' => $package->name,
                'error' => $e->getMessage()
            ]);
        }
    }
}
