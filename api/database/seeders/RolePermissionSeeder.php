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
            ],

            'Customer Service' => [
                // User Management - View Only
                'view_all_users',

                // Customer Support
                'view_customer_data',
                'manage_refunds',
                'manage_returns',
            ],

            'Content Manager' => [
                // User Management - View Only
                'view_all_users',
            ],

            'User' => [
                // User Management - Own Profile Only
                'view_own_profile', 'edit_own_profile', 'delete_own_account',
                'create_user_account', // For self-registration
            ],

            'Guest' => [
                // User Registration
                'create_user_account',
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
