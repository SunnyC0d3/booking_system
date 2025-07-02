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
                // User Management
                'view_users', 'create_users', 'edit_users',

                // Role & Permission Management
                'view_roles', 'view_permissions',

                // Vendor Management
                'view_vendors', 'create_vendors', 'edit_vendors',

                // Product Management
                'view_products', 'create_products', 'edit_products', 'delete_products',
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
            ],

            'Vendor Manager' => [
                // Vendor Management
                'view_vendors', 'create_vendors', 'edit_vendors',

                // Product Management
                'view_products', 'edit_products',

                // Order Management - View All Orders for Management
                'view_all_orders', 'edit_all_orders',

                // Customer Service
                'view_customer_data',
            ],

            'Vendor' => [
                // Product Management (Own Products)
                'view_products', 'create_products', 'edit_products', 'delete_products',

                // Order Management - View Orders Related to Their Products
                'view_all_orders', // Note: You might want to create 'view_vendor_orders' for vendor-specific orders

                // Product Attributes/Categories (if they can manage these)
                'view_product_attributes', 'view_product_categories', 'view_product_tags', 'view_product_statuses',
            ],

            'Customer Service' => [
                // Customer Support
                'view_customer_data',
                'manage_refunds',
                'manage_returns',

                // Order Management - View and Edit for Support
                'view_all_orders', 'edit_all_orders',

                // Product Viewing (for support purposes)
                'view_products', 'view_product_categories',
            ],

            'Content Manager' => [
                // Product Content Management
                'view_products', 'create_products', 'edit_products',
                'view_product_attributes', 'create_product_attributes', 'edit_product_attributes',
                'view_product_categories', 'create_product_categories', 'edit_product_categories',
                'view_product_tags', 'create_product_tags', 'edit_product_tags',
                'view_product_statuses', 'create_product_statuses', 'edit_product_statuses',

                // Category Management
                'view_categories', 'create_categories', 'edit_categories',
            ],

            'User' => [
                // Basic Customer Permissions
                'view_products',
                'view_product_categories',

                // Order Management - Own Orders Only
                'view_own_orders', 'create_own_orders', 'edit_own_orders', 'delete_own_orders',
            ],

            'Guest' => [
                // Browse Only
                'view_products',
                'view_product_categories',
            ],
        ];

        $rolePermissionsToInsert = [];
        $roles = Role::all()->keyBy('name');
        $permissions = Permission::all();

        foreach ($rolePermissions as $roleName => $rolePerms) {
            $roleId = $roles[$roleName]->id;

            // If role should have all permissions
            if ($rolePerms === '*') {
                foreach ($permissions as $permission) {
                    $rolePermissionsToInsert[] = [
                        'role_id'       => $roleId,
                        'permission_id' => $permission->id,
                    ];
                }

                continue;
            }

            // Specific permissions for role
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
