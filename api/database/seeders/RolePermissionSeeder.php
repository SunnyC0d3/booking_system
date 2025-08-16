<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $rolePermissions = [
            'Super Admin' => '*',

            'Admin' => [
                // User Management - Full Access
                'view_all_users', 'create_all_users', 'edit_all_users', 'delete_all_users', 'restore_users', 'force_delete_users',

                // Role & Permission Management
                'view_roles', 'create_roles', 'edit_roles', 'delete_roles',
                'view_permissions', 'create_permissions', 'edit_permissions', 'delete_permissions',

                // Customer Service
                'view_customer_data', 'manage_refunds', 'manage_returns',

                // Payment Management
                'view_payments', 'create_payments', 'edit_payments', 'delete_payments',
                'view_payment_methods', 'create_payment_methods', 'edit_payment_methods', 'delete_payment_methods',

                // Booking Management - Full Access
                'view_own_bookings', 'create_own_bookings', 'edit_own_bookings', 'delete_own_bookings',
                'view_all_bookings', 'create_bookings_for_users', 'edit_all_bookings', 'delete_all_bookings',

                // Service Management - Full Access
                'view_services', 'create_services', 'edit_services', 'delete_services',
                'view_service_locations', 'create_service_locations', 'edit_service_locations', 'delete_service_locations',
                'view_service_availability', 'create_service_availability', 'edit_service_availability', 'delete_service_availability',
                'view_service_addons', 'create_service_addons', 'edit_service_addons', 'delete_service_addons',

                // Booking Status Management
                'confirm_bookings', 'mark_bookings_in_progress', 'mark_bookings_completed', 'mark_bookings_no_show',

                // Analytics and Reporting
                'view_booking_statistics', 'view_booking_calendar', 'export_bookings', 'bulk_update_bookings',

                // Consultation and Pricing
                'manage_consultations', 'view_consultation_notes', 'view_pricing_details', 'modify_pricing',
            ],

            'Customer Service' => [
                // User Management - View Only
                'view_all_users',

                // Customer Support
                'view_customer_data', 'manage_refunds', 'manage_returns',

                // Booking Management - View and Status Updates
                'view_all_bookings', 'edit_all_bookings',
                'confirm_bookings', 'mark_bookings_in_progress', 'mark_bookings_completed', 'mark_bookings_no_show',

                // Service Information - View Only
                'view_services', 'view_service_locations', 'view_service_availability', 'view_service_addons',

                // Consultation Management
                'manage_consultations', 'view_consultation_notes',

                // Analytics - Limited
                'view_booking_statistics', 'view_booking_calendar',

                // Pricing - View Only
                'view_pricing_details',
            ],

            'Content Manager' => [
                // User Management - View Only
                'view_all_users',

                // Service Content Management
                'view_services', 'edit_services',
                'view_service_locations', 'edit_service_locations',
                'view_service_addons', 'edit_service_addons',

                // Booking Information - View Only
                'view_all_bookings',

                // Pricing - View Only
                'view_pricing_details',
            ],

            'User' => [
                // User Management - Own Profile Only
                'view_own_profile', 'edit_own_profile', 'delete_own_account',
                'create_user_account', // For self-registration

                // Booking Management - Own Bookings Only
                'view_own_bookings', 'create_own_bookings', 'edit_own_bookings', 'delete_own_bookings',

                // Service Information - View Only
                'view_services', 'view_service_locations', 'view_service_availability', 'view_service_addons',

                // Pricing - View Only
                'view_pricing_details',
            ],

            'Guest' => [
                // User Registration
                'create_user_account',

                // Service Information - View Only (Public)
                'view_services', 'view_service_locations', 'view_service_availability', 'view_service_addons',
            ],
        ];

        $rolePermissionsToInsert = [];
        $roles = Role::all()->keyBy('name');
        $permissions = Permission::all();

        foreach ($rolePermissions as $roleName => $rolePerms) {
            $roleId = $roles[$roleName]->id;

            if ($rolePerms === '*') {
                foreach ($permissions as $permission) {
                    $rolePermissionsToInsert[] = [
                        'role_id'       => $roleId,
                        'permission_id' => $permission->id,
                    ];
                }

                continue;
            }

            foreach ($rolePerms as $permName) {
                $permission = $permissions->firstWhere('name', $permName);
                if ($permission) {
                    $rolePermissionsToInsert[] = [
                        'role_id'       => $roleId,
                        'permission_id' => $permission->id,
                    ];
                }
            }
        }

        DB::table('role_permission')->insert($rolePermissionsToInsert);
    }
}
