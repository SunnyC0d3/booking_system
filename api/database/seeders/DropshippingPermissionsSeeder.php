<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class DropshippingPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = $this->getDropshippingPermissions();

        foreach ($permissions as $permissionName) {
            Permission::firstOrCreate(['name' => $permissionName]);
        }

        $this->assignPermissionsToRoles();
    }

    private function getDropshippingPermissions(): array
    {
        return [
            'view_suppliers',
            'create_suppliers',
            'edit_suppliers',
            'delete_suppliers',
            'approve_suppliers',
            'manage_supplier_integrations',
            'test_supplier_connections',

            'view_supplier_products',
            'sync_supplier_products',
            'map_supplier_products',
            'bulk_update_supplier_products',
            'manage_product_mappings',

            'view_dropship_orders',
            'create_dropship_orders',
            'edit_dropship_orders',
            'cancel_dropship_orders',
            'retry_dropship_orders',
            'bulk_manage_dropship_orders',

            'view_supplier_integrations',
            'create_supplier_integrations',
            'edit_supplier_integrations',
            'delete_supplier_integrations',
            'test_integrations',
            'view_integration_logs',

            'view_dropshipping_analytics',
            'view_supplier_performance',
            'view_profit_margins',
            'export_dropshipping_data',

            'manage_automated_fulfillment',
            'configure_sync_settings',
            'manage_markup_rules',
            'override_supplier_prices',

            'view_dropshipping_settings',
            'edit_dropshipping_settings',
            'manage_default_markups',
            'configure_automation_rules',
        ];
    }

    private function assignPermissionsToRoles(): void
    {
        $superAdminRole = Role::where('name', 'super admin')->first();
        $adminRole = Role::where('name', 'admin')->first();

        if ($superAdminRole) {
            $allPermissions = Permission::whereIn('name', $this->getDropshippingPermissions())->get();
            $superAdminRole->permissions()->syncWithoutDetaching($allPermissions);
        }

        if ($adminRole) {
            $adminPermissions = Permission::whereIn('name', [
                'view_suppliers',
                'edit_suppliers',
                'view_supplier_products',
                'sync_supplier_products',
                'map_supplier_products',
                'view_dropship_orders',
                'create_dropship_orders',
                'edit_dropship_orders',
                'cancel_dropship_orders',
                'view_supplier_integrations',
                'view_dropshipping_analytics',
                'view_supplier_performance',
                'view_profit_margins',
                'manage_automated_fulfillment',
                'configure_sync_settings',
                'manage_markup_rules',
                'view_dropshipping_settings',
            ])->get();

            $adminRole->permissions()->syncWithoutDetaching($adminPermissions);
        }

        $vendorRole = Role::where('name', 'vendor')->first();
        if ($vendorRole) {
            $vendorPermissions = Permission::whereIn('name', [
                'view_suppliers',
                'view_supplier_products',
                'view_dropship_orders',
                'view_supplier_performance',
                'view_profit_margins',
            ])->get();

            $vendorRole->permissions()->syncWithoutDetaching($vendorPermissions);
        }
    }
}
