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

            // Vendor Management - Admin Level
            ['name' => 'view_all_vendors'],
            ['name' => 'create_vendors_for_users'],
            ['name' => 'edit_all_vendors'],
            ['name' => 'delete_all_vendors'],
            ['name' => 'restore_vendors'],
            ['name' => 'force_delete_vendors'],

            // Vendor Management - Vendor Level
            ['name' => 'view_own_vendor'],
            ['name' => 'create_own_vendor'],
            ['name' => 'edit_own_vendor'],
            ['name' => 'delete_own_vendor'],

            // Vendor Management - Public Level
            ['name' => 'view_vendors_public'],

            // Product Management
            ['name' => 'view_products'],
            ['name' => 'create_products'],
            ['name' => 'edit_products'],
            ['name' => 'delete_products'],
            ['name' => 'restore_products'],
            ['name' => 'force_delete_products'],

            // Product Attribute
            ['name' => 'view_product_attributes'],
            ['name' => 'create_product_attributes'],
            ['name' => 'edit_product_attributes'],
            ['name' => 'delete_product_attributes'],

            // Product Category
            ['name' => 'view_product_categories'],
            ['name' => 'create_product_categories'],
            ['name' => 'edit_product_categories'],
            ['name' => 'delete_product_categories'],

            // Product Status
            ['name' => 'view_product_statuses'],
            ['name' => 'create_product_statuses'],
            ['name' => 'edit_product_statuses'],
            ['name' => 'delete_product_statuses'],

            // Product Tag
            ['name' => 'view_product_tags'],
            ['name' => 'create_product_tags'],
            ['name' => 'edit_product_tags'],
            ['name' => 'delete_product_tags'],

            // Category Management
            ['name' => 'view_categories'],
            ['name' => 'create_categories'],
            ['name' => 'edit_categories'],
            ['name' => 'delete_categories'],

            // Order Management - Admin/Staff Level
            ['name' => 'view_all_orders'],
            ['name' => 'create_orders_for_users'],
            ['name' => 'edit_all_orders'],
            ['name' => 'delete_all_orders'],
            ['name' => 'restore_orders'],
            ['name' => 'force_delete_orders'],

            // Order Management - User Level
            ['name' => 'view_own_orders'],
            ['name' => 'create_own_orders'],
            ['name' => 'edit_own_orders'],
            ['name' => 'delete_own_orders'],

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

            // Customer Service
            ['name' => 'view_customer_data'],
            ['name' => 'manage_refunds'],
            ['name' => 'manage_returns']
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
