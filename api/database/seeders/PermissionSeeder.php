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

            // Consultation Management - User Level
            ['name' => 'create_consultations'],
            ['name' => 'view_own_consultations'],
            ['name' => 'edit_own_consultations'],
            ['name' => 'cancel_own_consultations'],
            ['name' => 'join_own_consultations'],
            ['name' => 'reschedule_own_consultations'],

            // Consultation Management - Admin Level
            ['name' => 'manage_consultations'], // Full admin access
            ['name' => 'view_all_consultations'],
            ['name' => 'create_consultations_for_users'],
            ['name' => 'edit_all_consultations'],
            ['name' => 'delete_all_consultations'],
            ['name' => 'cancel_all_consultations'],
            ['name' => 'assign_consultants'],
            ['name' => 'bulk_update_consultations'],

            // Consultation Session Management
            ['name' => 'host_consultations'],
            ['name' => 'start_consultations'],
            ['name' => 'complete_consultations'],
            ['name' => 'mark_consultations_no_show'],
            ['name' => 'view_consultation_notes'],
            ['name' => 'create_consultation_notes'],
            ['name' => 'edit_consultation_notes'],

            // Consultation Analytics and Reporting
            ['name' => 'view_consultation_statistics'],
            ['name' => 'view_consultation_dashboard'],
            ['name' => 'export_consultations'],
            ['name' => 'view_consultation_calendar'],

            // Consultation Workflow Management
            ['name' => 'manage_consultation_workflow'],
            ['name' => 'approve_consultation_outcomes'],
            ['name' => 'schedule_follow_up_consultations'],

            // Customer Service Permissions
            ['name' => 'view_customer_data'],
            ['name' => 'manage_refunds'],
            ['name' => 'manage_returns'],

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
