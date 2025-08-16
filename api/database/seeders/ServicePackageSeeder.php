<?php

namespace Database\Seeders;

use App\Models\ServicePackage;
use App\Models\Service;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ServicePackageSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            // Get all active services
            $services = Service::active()->get();

            if ($services->isEmpty()) {
                $this->command->warn('No active services found. Please run ServiceSeeder first.');
                return;
            }

            // Create predefined packages
            $this->createWeddingPackage($services);
            $this->createBirthdayPackage($services);
            $this->createCorporatePackage($services);
            $this->createBabyShowerPackage($services);
            $this->createAnniversaryPackage($services);

            // Create additional random packages
            ServicePackage::factory()
                ->count(5)
                ->create()
                ->each(function ($package) use ($services) {
                    $this->attachRandomServices($package, $services);
                });

            $this->command->info('Service packages created successfully!');
        });
    }

    private function createWeddingPackage($services): void
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
                'includes' => [
                    'Bridal arch',
                    'Table centerpieces (up to 10 tables)',
                    'Photo backdrop',
                    'Aisle decorations',
                    'Professional setup',
                    'Breakdown service',
                    'Consultation included'
                ]
            ]
        ]);

        // Add core services (required)
        $this->attachServiceToPackage($package, 'Balloon Arch Design', 1, 1, false);
        $this->attachServiceToPackage($package, 'Table Centerpieces', 8, 2, false);
        $this->attachServiceToPackage($package, 'Photo Backdrop Setup', 1, 3, false);

        // Add optional services
        $this->attachServiceToPackage($package, 'Venue Consultation', 1, 4, true);

        $this->calculatePackagePricing($package, 15); // 15% discount
    }

    private function createBirthdayPackage($services): void
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
                'age_groups' => ['kids', 'teens', 'adults'],
                'themes_available' => ['cartoon', 'superhero', 'princess', 'sports', 'custom']
            ]
        ]);

        $this->attachServiceToPackage($package, 'Balloon Arch Design', 1, 1, false);
        $this->attachServiceToPackage($package, 'Table Centerpieces', 3, 2, false);
        $this->attachServiceToPackage($package, 'Photo Backdrop Setup', 1, 3, true);

        $this->calculatePackagePricing($package, 10); // 10% discount
    }

    private function createCorporatePackage($services): void
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
                'suitable_for' => ['conferences', 'product launches', 'corporate parties', 'award ceremonies'],
                'customizable' => true,
                'brand_colors' => true
            ]
        ]);

        $this->attachServiceToPackage($package, 'Venue Consultation', 1, 1, false);
        $this->attachServiceToPackage($package, 'Table Centerpieces', 6, 2, false);
        $this->attachServiceToPackage($package, 'Photo Backdrop Setup', 1, 3, true);

        $this->calculatePackagePricing($package, 12); // 12% discount
    }

    private function createBabyShowerPackage($services): void
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
                'themes' => ['boy', 'girl', 'neutral', 'safari', 'cloud', 'floral'],
                'color_schemes' => ['pink/gold', 'blue/silver', 'mint/gold', 'neutral/gold']
            ]
        ]);

        $this->attachServiceToPackage($package, 'Balloon Arch Design', 1, 1, false);
        $this->attachServiceToPackage($package, 'Table Centerpieces', 4, 2, false);
        $this->attachServiceToPackage($package, 'Photo Backdrop Setup', 1, 3, true);

        $this->calculatePackagePricing($package, 8); // 8% discount
    }

    private function createAnniversaryPackage($services): void
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
                'romantic_themes' => ['classic', 'vintage', 'modern', 'garden'],
                'anniversary_milestones' => ['1st', '5th', '10th', '25th', '50th'],
                'special_touches' => ['candles', 'flowers', 'personalized elements']
            ]
        ]);

        $this->attachServiceToPackage($package, 'Table Centerpieces', 2, 1, false);
        $this->attachServiceToPackage($package, 'Photo Backdrop Setup', 1, 2, false);
        $this->attachServiceToPackage($package, 'Balloon Arch Design', 1, 3, true);

        $this->calculatePackagePricing($package, 10); // 10% discount
    }

    private function attachServiceToPackage($package, $serviceName, $quantity, $order, $isOptional): void
    {
        $service = Service::where('name', 'like', "%{$serviceName}%")->first();

        if ($service) {
            $package->services()->attach($service->id, [
                'quantity' => $quantity,
                'order' => $order,
                'is_optional' => $isOptional,
                'notes' => $isOptional ? 'Optional upgrade' : null,
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
        $totalPrice = 0;
        $totalDuration = 0;

        foreach ($package->services as $service) {
            $quantity = $service->pivot->quantity;
            $totalPrice += $service->base_price * $quantity;
            $totalDuration += $service->duration_minutes * $quantity;
        }

        $discountAmount = 0;
        $finalPrice = $totalPrice;

        if ($discountPercentage > 0) {
            $discountAmount = (int) round($totalPrice * ($discountPercentage / 100));
            $finalPrice = $totalPrice - $discountAmount;
        }

        $package->update([
            'individual_price_total' => $totalPrice,
            'total_duration_minutes' => $totalDuration,
            'discount_percentage' => $discountPercentage > 0 ? $discountPercentage : null,
            'discount_amount' => $discountAmount,
            'total_price' => $finalPrice,
        ]);
    }
}
