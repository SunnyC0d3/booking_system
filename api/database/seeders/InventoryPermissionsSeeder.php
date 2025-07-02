<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Permission;
use App\Models\Role;

class InventoryPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'view_inventory',
            'edit_inventory',
            'manage_inventory'
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Assign to admin roles
        $adminRoles = Role::whereIn('name', ['super_admin', 'admin'])->get();

        foreach ($adminRoles as $role) {
            $permissionIds = Permission::whereIn('name', $permissions)->pluck('id');
            $role->permissions()->syncWithoutDetaching($permissionIds);
        }

        $this->command->info('Inventory permissions created and assigned to admin roles.');
    }
}
