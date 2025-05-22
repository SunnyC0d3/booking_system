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
                'view_users', 'create_users', 'edit_users',
                'view_roles', 'manage_roles',
                'view_vendors', 'create_vendors', 'edit_vendors',
                'view_products', 'create_products', 'edit_products', 'delete_products',
                'view_product_attributes', 'create_product_attributes', 'edit_product_attributes', 'delete_product_attributes',
                'view_product_categories', 'create_product_categories', 'edit_product_categories', 'delete_product_categories',
                'view_product_tags', 'create_product_tags', 'edit_product_tags', 'delete_product_tags',
                'view_product_statuses', 'create_product_statuses', 'edit_product_statuses', 'delete_product_statuses',
                'view_categories', 'manage_categories',
                'view_orders', 'manage_orders', 'cancel_orders',
                'view_customer_data', 'manage_refunds',
            ],

            'Vendor Manager' => [
                'view_vendors', 'create_vendors', 'edit_vendors',
                'view_products', 'edit_products',
                'view_orders', 'manage_orders',
            ],

            'Vendor' => [
                'view_products', 'create_products', 'edit_products', 'delete_products',
                'view_orders', 'manage_orders',
            ],

            'Customer Service' => [
                'view_customer_data',
                'view_orders',
                'manage_support_tickets',
                'manage_refunds',
            ],

            'User' => [
                'view_products',
                'view_product_categories'
            ],

            'Guest' => [
                'view_products',
                'view_product_categories'
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