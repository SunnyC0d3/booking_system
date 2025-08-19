<?php

namespace Database\Seeders;

use App\Models\Service;
use App\Models\ServiceAvailabilityWindow;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class ServiceAvailabilityExceptionSeeder extends Seeder
{
    public function run(): void
    {
        $services = Service::where('is_active', true)->limit(5)->get();

        foreach ($services as $service) {
            $this->createExceptionsForService($service);
        }
    }

    private function createExceptionsForService(Service $service): void
    {
        // Block some random future dates (using availability windows with type = 'exception')
        for ($i = 0; $i < 3; $i++) {
            $blockDate = Carbon::now()->addDays(rand(10, 90));

            ServiceAvailabilityWindow::create([
                'service_id' => $service->id,
                'type' => 'blocked',
                'pattern' => 'specific_date',
                'start_date' => $blockDate->format('Y-m-d'),
                'end_date' => $blockDate->format('Y-m-d'),
                'start_time' => '00:00:00',
                'end_time' => '23:59:59',
                'max_bookings' => 0,
                'is_active' => true,
                'is_bookable' => false,
                'title' => 'Equipment Maintenance',
                'description' => 'Service unavailable due to equipment maintenance',
                'metadata' => [
                    'reason' => 'equipment_maintenance',
                    'auto_generated' => true,
                ],
            ]);
        }

        // Add some custom hours (shortened availability)
        for ($i = 0; $i < 2; $i++) {
            $customDate = Carbon::now()->addDays(rand(5, 60));

            ServiceAvailabilityWindow::create([
                'service_id' => $service->id,
                'type' => 'special_hours',
                'pattern' => 'specific_date',
                'start_date' => $customDate->format('Y-m-d'),
                'end_date' => $customDate->format('Y-m-d'),
                'start_time' => '10:00:00',
                'end_time' => '14:00:00',
                'max_bookings' => 1,
                'is_active' => true,
                'is_bookable' => true,
                'title' => 'Limited Availability',
                'description' => 'Reduced hours due to staff scheduling',
                'metadata' => [
                    'reason' => 'limited_availability',
                    'auto_generated' => true,
                ],
            ]);
        }

        // Add special pricing for holiday periods
        $holidayDate = Carbon::now()->addDays(rand(30, 60));

        ServiceAvailabilityWindow::create([
            'service_id' => $service->id,
            'type' => 'special_hours',
            'pattern' => 'specific_date',
            'start_date' => $holidayDate->format('Y-m-d'),
            'end_date' => $holidayDate->format('Y-m-d'),
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'max_bookings' => 1,
            'price_modifier' => 2000, // £20 extra
            'price_modifier_type' => 'fixed',
            'is_active' => true,
            'is_bookable' => true,
            'title' => 'Holiday Premium',
            'description' => 'Special holiday rates apply',
            'metadata' => [
                'reason' => 'holiday_premium',
                'auto_generated' => true,
                'holiday_type' => 'general',
            ],
        ]);

        // Add some weekend exception pricing
        $this->createWeekendExceptions($service);

        // Add Christmas/New Year blocked periods
        $this->createHolidayBlocks($service);
    }

    private function createWeekendExceptions(Service $service): void
    {
        // Create special weekend pricing for next few months
        $startDate = Carbon::now()->startOfMonth();
        $endDate = Carbon::now()->addMonths(3)->endOfMonth();

        // Create exception for Sundays (higher pricing)
        ServiceAvailabilityWindow::create([
            'service_id' => $service->id,
            'type' => 'special_hours',
            'pattern' => 'weekly',
            'day_of_week' => 0, // Sunday
            'start_time' => '10:00:00',
            'end_time' => '16:00:00',
            'max_bookings' => 1,
            'price_modifier' => 3000, // £30 extra for Sundays
            'price_modifier_type' => 'fixed',
            'is_active' => true,
            'is_bookable' => true,
            'title' => 'Sunday Premium',
            'description' => 'Premium rates apply for Sunday bookings',
            'metadata' => [
                'reason' => 'sunday_premium',
                'auto_generated' => true,
            ],
        ]);
    }

    private function createHolidayBlocks(Service $service): void
    {
        $currentYear = Carbon::now()->year;
        $nextYear = $currentYear + 1;

        // Block Christmas period (if in future)
        $christmasStart = Carbon::create($currentYear, 12, 24);
        $christmasEnd = Carbon::create($currentYear, 12, 26);

        if ($christmasStart->isFuture()) {
            ServiceAvailabilityWindow::create([
                'service_id' => $service->id,
                'type' => 'blocked',
                'pattern' => 'date_range',
                'start_date' => $christmasStart->format('Y-m-d'),
                'end_date' => $christmasEnd->format('Y-m-d'),
                'start_time' => '00:00:00',
                'end_time' => '23:59:59',
                'max_bookings' => 0,
                'is_active' => true,
                'is_bookable' => false,
                'title' => 'Christmas Holiday',
                'description' => 'Business closed for Christmas holidays',
                'metadata' => [
                    'reason' => 'christmas_holiday',
                    'auto_generated' => true,
                    'holiday_type' => 'christmas',
                ],
            ]);
        }

        // Block New Year's Day
        $newYearDate = Carbon::create($nextYear, 1, 1);

        ServiceAvailabilityWindow::create([
            'service_id' => $service->id,
            'type' => 'blocked',
            'pattern' => 'specific_date',
            'start_date' => $newYearDate->format('Y-m-d'),
            'end_date' => $newYearDate->format('Y-m-d'),
            'start_time' => '00:00:00',
            'end_time' => '23:59:59',
            'max_bookings' => 0,
            'is_active' => true,
            'is_bookable' => false,
            'title' => 'New Year\'s Day',
            'description' => 'Business closed for New Year\'s Day',
            'metadata' => [
                'reason' => 'new_year_holiday',
                'auto_generated' => true,
                'holiday_type' => 'new_year',
            ],
        ]);

        // Add Bank Holiday exceptions (UK specific for your balloon business)
        $this->createBankHolidayExceptions($service);
    }

    private function createBankHolidayExceptions(Service $service): void
    {
        // Common UK Bank Holidays for the current year
        $currentYear = Carbon::now()->year;
        $bankHolidays = [
            ['date' => Carbon::create($currentYear, 5, 1), 'name' => 'May Day Bank Holiday'],
            ['date' => Carbon::create($currentYear, 8, 26), 'name' => 'Summer Bank Holiday'],
        ];

        foreach ($bankHolidays as $holiday) {
            if ($holiday['date']->isFuture()) {
                // On bank holidays, either charge premium or reduce hours
                $premiumOrReduced = rand(0, 1);

                if ($premiumOrReduced) {
                    // Premium pricing
                    ServiceAvailabilityWindow::create([
                        'service_id' => $service->id,
                        'type' => 'special_hours',
                        'pattern' => 'specific_date',
                        'start_date' => $holiday['date']->format('Y-m-d'),
                        'end_date' => $holiday['date']->format('Y-m-d'),
                        'start_time' => '10:00:00',
                        'end_time' => '16:00:00',
                        'max_bookings' => 1,
                        'price_modifier' => 2500, // £25 extra
                        'price_modifier_type' => 'fixed',
                        'is_active' => true,
                        'is_bookable' => true,
                        'title' => $holiday['name'] . ' - Premium',
                        'description' => 'Bank holiday premium rates apply',
                        'metadata' => [
                            'reason' => 'bank_holiday_premium',
                            'holiday_name' => $holiday['name'],
                            'auto_generated' => true,
                        ],
                    ]);
                } else {
                    // Reduced hours
                    ServiceAvailabilityWindow::create([
                        'service_id' => $service->id,
                        'type' => 'special_hours',
                        'pattern' => 'specific_date',
                        'start_date' => $holiday['date']->format('Y-m-d'),
                        'end_date' => $holiday['date']->format('Y-m-d'),
                        'start_time' => '11:00:00',
                        'end_time' => '15:00:00',
                        'max_bookings' => 1,
                        'is_active' => true,
                        'is_bookable' => true,
                        'title' => $holiday['name'] . ' - Limited Hours',
                        'description' => 'Reduced hours due to bank holiday',
                        'metadata' => [
                            'reason' => 'bank_holiday_reduced',
                            'holiday_name' => $holiday['name'],
                            'auto_generated' => true,
                        ],
                    ]);
                }
            }
        }
    }
}
