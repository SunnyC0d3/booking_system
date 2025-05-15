<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // User Management
            ['name' => 'view_users'],
            ['name' => 'create_users'],
            ['name' => 'edit_users'],
            ['name' => 'delete_users'],

            // Role Management
            ['name' => 'view_roles'],
            ['name' => 'create_roles'],
            ['name' => 'edit_roles'],
            ['name' => 'delete_roles'],

            // Permission Management
            ['name' => 'view_roles'],
            ['name' => 'create_permissions'],
            ['name' => 'edit_permissions'],
            ['name' => 'delete_permissions'],

            // Vendor Management
            ['name' => 'view_vendors'],
            ['name' => 'create_vendors'],
            ['name' => 'edit_vendors'],
            ['name' => 'delete_vendors'],

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

            // Order Management
            ['name' => 'view_orders'],
            ['name' => 'create_orders'],
            ['name' => 'edit_orders'],
            ['name' => 'delete_orders'],
            ['name' => 'restore_orders'],
            ['name' => 'force_delete_orders'],

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
