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
