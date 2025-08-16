<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // User Management - Admin Level
            ['name' => 'view_all_users'],
            ['name' => 'create_all_users'],
            ['name' => 'edit_all_users'],
            ['name' => 'delete_all_users'],
            ['name' => 'restore_users'],
            ['name' => 'force_delete_users'],

            // User Management - User Level
            ['name' => 'view_own_profile'],
            ['name' => 'edit_own_profile'],
            ['name' => 'delete_own_account'],
            ['name' => 'create_user_account'], // For user registration

            // Role Management
            ['name' => 'view_roles'],
            ['name' => 'create_roles'],
            ['name' => 'edit_roles'],
            ['name' => 'delete_roles'],

            // Permission Management
            ['name' => 'view_permissions'],
            ['name' => 'create_permissions'],
            ['name' => 'edit_permissions'],
            ['name' => 'delete_permissions'],

            // Payment Methods
            ['name' => 'view_payment_methods'],
            ['name' => 'create_payment_methods'],
            ['name' => 'edit_payment_methods'],
            ['name' => 'delete_payment_methods'],

            // Payment
            ['name' => 'view_payments'],
            ['name' => 'create_payments'],
            ['name' => 'edit_payments'],
            ['name' => 'delete_payments'],

            // Booking Management - User Level
            ['name' => 'view_own_bookings'],
            ['name' => 'create_own_bookings'],
            ['name' => 'edit_own_bookings'],
            ['name' => 'delete_own_bookings'],

            // Booking Management - Admin Level
            ['name' => 'view_all_bookings'],
            ['name' => 'create_bookings_for_users'],
            ['name' => 'edit_all_bookings'],
            ['name' => 'delete_all_bookings'],
            ['name' => 'force_delete_bookings'],
            ['name' => 'restore_bookings'],

            // Service Management
            ['name' => 'view_services'],
            ['name' => 'create_services'],
            ['name' => 'edit_services'],
            ['name' => 'delete_services'],

            // Service Location Management
            ['name' => 'view_service_locations'],
            ['name' => 'create_service_locations'],
            ['name' => 'edit_service_locations'],
            ['name' => 'delete_service_locations'],

            // Service Availability Management
            ['name' => 'view_service_availability'],
            ['name' => 'create_service_availability'],
            ['name' => 'edit_service_availability'],
            ['name' => 'delete_service_availability'],

            // Service Add-on Management
            ['name' => 'view_service_addons'],
            ['name' => 'create_service_addons'],
            ['name' => 'edit_service_addons'],
            ['name' => 'delete_service_addons'],

            // Booking Status Management
            ['name' => 'confirm_bookings'],
            ['name' => 'mark_bookings_in_progress'],
            ['name' => 'mark_bookings_completed'],
            ['name' => 'mark_bookings_no_show'],

            // Analytics and Reporting
            ['name' => 'view_booking_statistics'],
            ['name' => 'view_booking_calendar'],
            ['name' => 'export_bookings'],
            ['name' => 'bulk_update_bookings'],

            // Consultation Management
            ['name' => 'manage_consultations'],
            ['name' => 'view_consultation_notes'],

            // Pricing Management
            ['name' => 'view_pricing_details'],
            ['name' => 'modify_pricing'],
        ];

        $permissionsToInsert = array_map(function ($permission) {
            return [
                'name'       => $permission['name'],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }, $permissions);

        Permission::insert($permissionsToInsert);
    }
}
