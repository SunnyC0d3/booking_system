<?php

namespace Database\Seeders;

use App\Models\Service;
use App\Models\ServiceAvailabilityException;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class ServiceAvailabilityExceptionSeeder extends Seeder
{
    public function run(): void
    {
        $services = Service::active()->limit(5)->get();

        foreach ($services as $service) {
            $this->createExceptionsForService($service);
        }

        $this->command->info('Service availability exceptions seeded successfully!');
    }

    private function createExceptionsForService(Service $service): void
    {
        // Block some random future dates
        for ($i = 0; $i < 3; $i++) {
            $blockDate = Carbon::now()->addDays(rand(10, 90));

            ServiceAvailabilityException::create([
                'service_id' => $service->id,
                'exception_date' => $blockDate,
                'exception_type' => 'blocked',
                'reason' => 'Equipment maintenance',
                'is_active' => true,
            ]);
        }

        // Add some custom hours
        for ($i = 0; $i < 2; $i++) {
            $customDate = Carbon::now()->addDays(rand(5, 60));

            ServiceAvailabilityException::create([
                'service_id' => $service->id,
                'exception_date' => $customDate,
                'exception_type' => 'custom_hours',
                'start_time' => '10:00',
                'end_time' => '14:00',
                'reason' => 'Limited availability',
                'is_active' => true,
            ]);
        }

        // Add special pricing for holiday periods
        $holidayDate = Carbon::now()->addDays(rand(30, 60));

        ServiceAvailabilityException::create([
            'service_id' => $service->id,
            'exception_date' => $holidayDate,
            'exception_type' => 'special_pricing',
            'price_modifier' => 2000, // £20 extra
            'price_modifier_type' => 'fixed',
            'reason' => 'Holiday premium',
            'is_active' => true,
        ]);
    }
}
