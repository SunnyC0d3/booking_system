<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        $serviceTypes = [
            [
                'name' => 'Personal Training Session',
                'description' => 'One-on-one personal training session tailored to your fitness goals. Includes fitness assessment, customized workout plan, and nutritional guidance.',
                'short_description' => 'Personalized fitness training with certified trainer',
                'base_price' => 8000, // £80.00
                'duration_minutes' => 60,
                'buffer_minutes' => 15,
                'requires_deposit' => true,
                'deposit_percentage' => 30.00,
            ],
            [
                'name' => 'Business Consultation',
                'description' => 'Strategic business consultation covering market analysis, growth planning, operational efficiency, and financial planning. Perfect for startups and established businesses.',
                'short_description' => 'Strategic business planning and consultation',
                'base_price' => 15000, // £150.00
                'duration_minutes' => 90,
                'buffer_minutes' => 30,
                'requires_deposit' => true,
                'deposit_percentage' => 50.00,
            ],
            [
                'name' => 'Hair Cut & Style',
                'description' => 'Professional hair cutting and styling service. Includes consultation, wash, cut, style, and finishing. Suitable for all hair types and ages.',
                'short_description' => 'Professional hair cutting and styling',
                'base_price' => 4500, // £45.00
                'duration_minutes' => 75,
                'buffer_minutes' => 15,
                'requires_deposit' => false,
            ],
            [
                'name' => 'Therapeutic Massage',
                'description' => 'Relaxing therapeutic massage designed to relieve stress, reduce muscle tension, and improve circulation. Choose from Swedish, deep tissue, or sports massage.',
                'short_description' => 'Professional therapeutic massage therapy',
                'base_price' => 7000, // £70.00
                'duration_minutes' => 60,
                'buffer_minutes' => 30,
                'requires_deposit' => true,
                'deposit_amount' => 2000, // £20.00 fixed
            ],
            [
                'name' => 'Legal Consultation',
                'description' => 'Professional legal advice and consultation on various matters including contracts, employment law, family law, and business legal issues.',
                'short_description' => 'Professional legal advice and consultation',
                'base_price' => 20000, // £200.00
                'duration_minutes' => 60,
                'buffer_minutes' => 0,
                'requires_deposit' => true,
                'deposit_percentage' => 100.00, // Full payment upfront
            ],
            [
                'name' => 'Photography Session',
                'description' => 'Professional photography session for portraits, events, or commercial purposes. Includes consultation, shooting, and basic photo editing.',
                'short_description' => 'Professional photography session',
                'base_price' => 12000, // £120.00
                'duration_minutes' => 120,
                'buffer_minutes' => 30,
                'requires_deposit' => true,
                'deposit_percentage' => 40.00,
            ],
            [
                'name' => 'Website Design Consultation',
                'description' => 'Comprehensive website design consultation including UX/UI review, design strategy, technical requirements assessment, and project planning.',
                'short_description' => 'Website design strategy and consultation',
                'base_price' => 18000, // £180.00
                'duration_minutes' => 90,
                'buffer_minutes' => 15,
                'requires_deposit' => true,
                'deposit_percentage' => 50.00,
            ],
        ];

        $servicesToCreate = $this->faker->numberBetween(2, 4);
        $selectedServices = $this->faker->randomElements($serviceTypes, $servicesToCreate);

        foreach ($selectedServices as $serviceData) {
            $service = Service::create(array_merge($serviceData, [
                'max_advance_booking_days' => $this->faker->randomElement([14, 30, 60]),
                'min_advance_booking_hours' => $this->faker->randomElement([24, 48]),
                'status' => 'active',
                'metadata' => [
                    'cancellation_policy' => '24 hours notice required',
                    'preparation_notes' => 'Please arrive 10 minutes early',
                ]
            ]));
        }

        Service::factory(10)->create();
    }
}
