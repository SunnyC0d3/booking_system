<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            PermissionSeeder::class,
            RolePermissionSeeder::class,

            PaymentMethodSeeder::class,

            UserSeeder::class,
            UserAddressSeeder::class,

            PaymentSeeder::class,

            ServiceSeeder::class,
            ServiceLocationSeeder::class,
            ServiceAddOnSeeder::class,
            ServiceAvailabilityWindowSeeder::class,
            ServicePackageSeeder::class,
            BookingSeeder::class,
            BookingNotificationSeeder::class,
            ServiceAvailabilityExceptionSeeder::class,
            VenueDetailsSeeder::class,
        ]);
    }
}
