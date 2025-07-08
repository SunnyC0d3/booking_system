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

                // Vendor Management - Full Access
                'view_all_vendors', 'create_vendors_for_users', 'edit_all_vendors', 'delete_all_vendors', 'restore_vendors', 'force_delete_vendors',

                // Product Management
                'view_products', 'create_products', 'edit_products', 'delete_products', 'restore_products', 'force_delete_products',
                'view_product_attributes', 'create_product_attributes', 'edit_product_attributes', 'delete_product_attributes',
                'view_product_categories', 'create_product_categories', 'edit_product_categories', 'delete_product_categories',
                'view_product_tags', 'create_product_tags', 'edit_product_tags', 'delete_product_tags',
                'view_product_statuses', 'create_product_statuses', 'edit_product_statuses', 'delete_product_statuses',

                // Category Management
                'view_categories', 'create_categories', 'edit_categories', 'delete_categories',

                // Order Management - Full Access
                'view_all_orders', 'create_orders_for_users', 'edit_all_orders', 'delete_all_orders', 'restore_orders', 'force_delete_orders',

                // Customer Service
                'view_customer_data', 'manage_refunds', 'manage_returns',

                // Payment Management
                'view_payments', 'create_payments', 'edit_payments', 'delete_payments',
                'view_payment_methods', 'create_payment_methods', 'edit_payment_methods', 'delete_payment_methods',

                // Inventory Management - Full Access
                'view_inventory', 'edit_inventory', 'manage_inventory', 'update_stock_thresholds', 'bulk_update_inventory', 'trigger_inventory_alerts', 'view_inventory_reports', 'manage_stock_alerts',
            ],

            'Vendor Manager' => [
                // User Management - Limited
                'view_all_users',

                // Vendor Management - Full Access
                'view_all_vendors', 'create_vendors_for_users', 'edit_all_vendors', 'delete_all_vendors',

                // Product Management
                'view_products', 'edit_products',

                // Order Management - View All Orders for Management
                'view_all_orders', 'edit_all_orders',

                // Customer Service
                'view_customer_data',

                // Inventory Management - Limited
                'view_inventory', 'edit_inventory', 'update_stock_thresholds', 'view_inventory_reports',
            ],

            'Vendor' => [
                // User Management - Own Profile Only
                'view_own_profile', 'edit_own_profile',

                // Vendor Management - Own Vendor Only
                'view_own_vendor', 'create_own_vendor', 'edit_own_vendor', 'delete_own_vendor',
                'view_vendors_public', // Can view other vendors publicly

                // Product Management (Own Products)
                'view_products', 'create_products', 'edit_products', 'delete_products',

                // Order Management - View Orders Related to Their Products
                'view_all_orders', // Note: You might want to create 'view_vendor_orders' for vendor-specific orders

                // Product Attributes/Categories (if they can manage these)
                'view_product_attributes', 'view_product_categories', 'view_product_tags', 'view_product_statuses',

                // Inventory Management - Own Products Only
                'view_inventory', 'edit_inventory', 'update_stock_thresholds',
            ],

            'Customer Service' => [
                // User Management - View Only
                'view_all_users',

                // Vendor Management - View Only
                'view_all_vendors',

                // Customer Support
                'view_customer_data',
                'manage_refunds',
                'manage_returns',

                // Order Management - View and Edit for Support
                'view_all_orders', 'edit_all_orders',

                // Product Viewing (for support purposes)
                'view_products', 'view_product_categories',

                // Inventory Management - View Only
                'view_inventory', 'view_inventory_reports',
            ],

            'Content Manager' => [
                // User Management - View Only
                'view_all_users',

                // Vendor Management - View Only
                'view_all_vendors',

                // Product Content Management
                'view_products', 'create_products', 'edit_products',
                'view_product_attributes', 'create_product_attributes', 'edit_product_attributes',
                'view_product_categories', 'create_product_categories', 'edit_product_categories',
                'view_product_tags', 'create_product_tags', 'edit_product_tags',
                'view_product_statuses', 'create_product_statuses', 'edit_product_statuses',

                // Category Management
                'view_categories', 'create_categories', 'edit_categories',

                // Inventory Management - View Only
                'view_inventory', 'view_inventory_reports',
            ],

            'User' => [
                // User Management - Own Profile Only
                'view_own_profile', 'edit_own_profile', 'delete_own_account',
                'create_user_account', // For self-registration

                // Vendor Management - Public View and Own Vendor
                'view_vendors_public', 'view_own_vendor', 'create_own_vendor', 'edit_own_vendor', 'delete_own_vendor',

                // Basic Customer Permissions
                'view_products',
                'view_product_categories',

                // Order Management - Own Orders Only
                'view_own_orders', 'create_own_orders', 'edit_own_orders', 'delete_own_orders',
            ],

            'Guest' => [
                // User Registration
                'create_user_account',

                // Browse Only
                'view_products',
                'view_product_categories',
                'view_vendors_public',
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
