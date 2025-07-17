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

            OrderStatusSeeder::class,
            OrderRefundStatusSeeder::class,
            OrderReturnStatusSeeder::class,
            PaymentMethodSeeder::class,

            UserSeeder::class,
            UserAddressSeeder::class,
            VendorSeeder::class,

            ProductStatusSeeder::class,
            ProductCategorySeeder::class,
            ProductAttributeSeeder::class,
            ProductSeeder::class,

            OrderSeeder::class,

            PaymentSeeder::class,

            InventoryPermissionsSeeder::class,

            ShippingMethodSeeder::class,
            ShippingZoneSeeder::class,
            ShippingRateSeeder::class,
        ]);
    }
}
