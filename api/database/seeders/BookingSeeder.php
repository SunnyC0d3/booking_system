<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Service;
use App\Models\ServiceLocation;
use App\Models\ServiceAvailabilityWindow;
use App\Models\ServiceAddOn;
use App\Models\BookingAddOn;
use App\Models\User;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class BookingSeeder extends Seeder
{
    public function run(): void
    {
        // Ensure we have services with locations and availability windows
        $services = Service::with(['locations', 'availabilityWindows', 'addOns'])->get();

        if ($services->isEmpty()) {
            $this->command->info('No services found. Creating some services first...');
            // Create some services with full setup
            $this->createServicesWithFullSetup();
            $services = Service::with(['locations', 'availabilityWindows', 'addOns'])->get();
        }

        $users = User::where('role_id', '!=', 1)->take(10)->get(); // Get non-admin users
        if ($users->isEmpty()) {
            $this->command->info('Creating additional users for bookings...');
            $users = User::factory(10)->create();
        }

        $this->command->info('Creating bookings...');

        foreach ($services as $service) {
            // Create various types of bookings for each service
            $this->createBookingsForService($service, $users);
        }

        $this->command->info('Bookings created successfully!');
    }

    private function createServicesWithFullSetup(): void
    {
        $serviceData = [
            [
                'name' => 'Balloon Arch Design & Setup',
                'description' => 'Custom balloon arch design and professional setup for weddings, parties, and special events. Includes consultation, design, and on-site installation.',
                'short_description' => 'Professional balloon arch design and setup service',
                'base_price' => 15000, // £150
                'duration_minutes' => 120,
                'buffer_minutes' => 30,
                'requires_deposit' => true,
                'deposit_percentage' => 40.00,
            ],
            [
                'name' => 'Wedding Decoration Package',
                'description' => 'Complete wedding decoration service including balloon arrangements, table centerpieces, and venue styling. Full-day service with setup and breakdown.',
                'short_description' => 'Complete wedding decoration service',
                'base_price' => 35000, // £350
                'duration_minutes' => 480, // 8 hours
                'buffer_minutes' => 60,
                'requires_deposit' => true,
                'deposit_percentage' => 50.00,
            ],
            [
                'name' => 'Birthday Party Decorations',
                'description' => 'Fun and colorful birthday party decorations including balloons, banners, and themed arrangements. Perfect for children and adult parties.',
                'short_description' => 'Birthday party decoration service',
                'base_price' => 8000, // £80
                'duration_minutes' => 90,
                'buffer_minutes' => 15,
                'requires_deposit' => true,
                'deposit_amount' => 3000, // £30 fixed
            ],
        ];

        foreach ($serviceData as $data) {
            $service = Service::create($data);

            // Create locations for each service
            $this->createLocationsForService($service);

            // Create availability windows
            $this->createAvailabilityWindowsForService($service);

            // Create add-ons
            $this->createAddOnsForService($service);
        }
    }

    private function createLocationsForService(Service $service): void
    {
        // Client location (most balloon decorators go to client)
        ServiceLocation::create([
            'service_id' => $service->id,
            'name' => 'Client\'s Venue',
            'description' => 'We come to your location for setup and decoration',
            'type' => 'client_location',
            'max_capacity' => 1,
            'travel_time_minutes' => 30,
            'additional_charge' => 0, // Included in base price
            'is_active' => true,
        ]);

        // Studio/warehouse for pickup
        ServiceLocation::create([
            'service_id' => $service->id,
            'name' => 'Studio Pickup',
            'description' => 'Pickup decorations from our studio',
            'type' => 'business_premises',
            'address_line_1' => '123 Creative Lane',
            'city' => 'London',
            'postcode' => 'SW1A 1AA',
            'country' => 'GB',
            'max_capacity' => 3,
            'travel_time_minutes' => 0,
            'additional_charge' => -2000, // £20 discount for pickup
            'is_active' => true,
        ]);
    }

    private function createAvailabilityWindowsForService(Service $service): void
    {
        // Regular business hours (Tuesday to Saturday)
        for ($day = 2; $day <= 6; $day++) { // Tuesday to Saturday
            ServiceAvailabilityWindow::create([
                'service_id' => $service->id,
                'type' => 'regular',
                'pattern' => 'weekly',
                'day_of_week' => $day,
                'start_time' => '09:00:00',
                'end_time' => '17:00:00',
                'max_bookings' => 2,
                'break_duration_minutes' => 30,
                'is_active' => true,
                'is_bookable' => true,
                'title' => 'Business Hours',
            ]);
        }

        // Weekend premium hours (Saturday and Sunday)
        foreach ([6, 0] as $day) { // Saturday and Sunday
            ServiceAvailabilityWindow::create([
                'service_id' => $service->id,
                'type' => 'special_hours',
                'pattern' => 'weekly',
                'day_of_week' => $day,
                'start_time' => '08:00:00',
                'end_time' => '18:00:00',
                'max_bookings' => 1,
                'break_duration_minutes' => 60,
                'is_active' => true,
                'is_bookable' => true,
                'title' => 'Weekend Premium Hours',
                'price_modifier' => 5000, // £50 weekend surcharge
                'price_modifier_type' => 'fixed',
            ]);
        }
    }

    private function createAddOnsForService(Service $service): void
    {
        $addOns = [
            [
                'name' => 'Additional Color Theme',
                'description' => 'Add an extra color to your balloon arrangement',
                'price' => 1500, // £15
                'duration_minutes' => 15,
                'category' => 'service_enhancement',
                'max_quantity' => 3,
            ],
            [
                'name' => 'LED Light Balloons',
                'description' => 'Upgrade to LED light-up balloons for evening events',
                'price' => 3000, // £30
                'duration_minutes' => 0,
                'category' => 'equipment',
                'max_quantity' => 1,
            ],
            [
                'name' => 'Same Day Setup',
                'description' => 'Rush service for same-day event setup',
                'price' => 5000, // £50
                'duration_minutes' => 0,
                'category' => 'service_enhancement',
                'max_quantity' => 1,
            ],
            [
                'name' => 'Extended Travel (20+ miles)',
                'description' => 'Additional charge for venues over 20 miles away',
                'price' => 2500, // £25
                'duration_minutes' => 30,
                'category' => 'location',
                'max_quantity' => 1,
            ],
            [
                'name' => 'Photo Documentation',
                'description' => 'Professional photos of the finished decoration setup',
                'price' => 2000, // £20
                'duration_minutes' => 15,
                'category' => 'other',
                'max_quantity' => 1,
            ],
        ];

        foreach ($addOns as $addOnData) {
            ServiceAddOn::create(array_merge($addOnData, [
                'service_id' => $service->id,
                'is_active' => true,
                'is_required' => false,
                'sort_order' => 0,
            ]));
        }
    }

    private function createBookingsForService(Service $service, $users): void
    {
        $bookingCount = rand(5, 12);

        for ($i = 0; $i < $bookingCount; $i++) {
            $user = $users->random();
            $location = $service->locations->random();

            // Generate booking date (mix of past, present, and future)
            $scheduledAt = $this->generateBookingDateTime();
            $endTime = $scheduledAt->clone()->addMinutes($service->duration_minutes);

            // Determine status based on date
            $status = $this->determineBookingStatus($scheduledAt);
            $paymentStatus = $this->determinePaymentStatus($status, $service->requires_deposit);

            // Calculate pricing
            $basePrice = $service->base_price;
            $addOnsTotal = 0;
            $totalAmount = $basePrice;

            // Create the booking
            $booking = Booking::create([
                'user_id' => $user->id,
                'service_id' => $service->id,
                'service_location_id' => $location->id,
                'scheduled_at' => $scheduledAt,
                'ends_at' => $endTime,
                'duration_minutes' => $service->duration_minutes,
                'base_price' => $basePrice,
                'addons_total' => $addOnsTotal,
                'total_amount' => $totalAmount,
                'deposit_amount' => $service->requires_deposit ? $service->getDepositAmountAttribute() : null,
                'remaining_amount' => $service->requires_deposit ? ($totalAmount - $service->getDepositAmountAttribute()) : null,
                'status' => $status,
                'payment_status' => $paymentStatus,
                'client_name' => $user->name,
                'client_email' => $user->email,
                'client_phone' => fake()->phoneNumber(),
                'notes' => fake()->optional(0.6)->paragraph(),
                'special_requirements' => fake()->optional(0.3)->sentence(),
                'requires_consultation' => fake()->boolean(40),
                'consultation_completed_at' => $status !== 'pending' && fake()->boolean(70) ? fake()->dateTimeBetween('-7 days', 'now') : null,
                'metadata' => [
                    'source' => fake()->randomElement(['website', 'phone', 'email', 'referral']),
                    'event_type' => fake()->randomElement(['wedding', 'birthday', 'anniversary', 'corporate', 'baby_shower']),
                    'guest_count' => fake()->numberBetween(10, 200),
                ],
            ]);

            // Add some random add-ons (30% chance)
            if (fake()->boolean(30) && $service->addOns->isNotEmpty()) {
                $this->addRandomAddOns($booking, $service->addOns);
            }
        }
    }

    private function generateBookingDateTime(): Carbon
    {
        $scenarios = [
            'past_completed' => 30,     // 30% past completed bookings
            'past_cancelled' => 10,     // 10% past cancelled bookings
            'upcoming_confirmed' => 40, // 40% upcoming confirmed bookings
            'upcoming_pending' => 20,   // 20% upcoming pending bookings
        ];

        $scenario = fake()->randomElement(array_merge(
            ...array_map(fn($weight, $key) => array_fill(0, $weight, $key), $scenarios, array_keys($scenarios))
        ));

        return match($scenario) {
            'past_completed', 'past_cancelled' => fake()->dateTimeBetween('-60 days', '-1 day'),
            'upcoming_confirmed', 'upcoming_pending' => fake()->dateTimeBetween('+1 day', '+90 days'),
            default => fake()->dateTimeBetween('-30 days', '+30 days'),
        };
    }

    private function determineBookingStatus(Carbon $scheduledAt): string
    {
        if ($scheduledAt->isPast()) {
            return fake()->randomElement(['completed', 'completed', 'completed', 'cancelled', 'no_show']);
        } else {
            return fake()->randomElement(['pending', 'confirmed', 'confirmed', 'confirmed']);
        }
    }

    private function determinePaymentStatus(string $status, bool $requiresDeposit): string
    {
        if ($status === 'cancelled') {
            return fake()->randomElement(['refunded', 'partially_refunded', 'pending']);
        }

        if ($status === 'completed') {
            return 'fully_paid';
        }

        if ($requiresDeposit) {
            return fake()->randomElement(['deposit_paid', 'fully_paid', 'pending']);
        }

        return fake()->randomElement(['fully_paid', 'pending']);
    }

    private function addRandomAddOns(Booking $booking, $availableAddOns): void
    {
        $selectedAddOns = $availableAddOns->random(fake()->numberBetween(1, 3));
        $totalAddOnsCost = 0;
        $totalAddOnsDuration = 0;

        foreach ($selectedAddOns as $addOn) {
            $quantity = fake()->numberBetween(1, min(2, $addOn->max_quantity));
            $unitPrice = $addOn->price;
            $totalPrice = $unitPrice * $quantity;
            $duration = $addOn->duration_minutes * $quantity;

            BookingAddOn::create([
                'booking_id' => $booking->id,
                'service_add_on_id' => $addOn->id,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total_price' => $totalPrice,
                'duration_minutes' => $addOn->duration_minutes,
            ]);

            $totalAddOnsCost += $totalPrice;
            $totalAddOnsDuration += $duration;
        }

        // Update booking totals
        $newTotalAmount = $booking->base_price + $totalAddOnsCost;
        $booking->update([
            'addons_total' => $totalAddOnsCost,
            'total_amount' => $newTotalAmount,
            'duration_minutes' => $booking->duration_minutes + $totalAddOnsDuration,
            'ends_at' => $booking->scheduled_at->clone()->addMinutes($booking->duration_minutes + $totalAddOnsDuration),
            'remaining_amount' => $booking->deposit_amount ? ($newTotalAmount - $booking->deposit_amount) : null,
        ]);
    }
}
