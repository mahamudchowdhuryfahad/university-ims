<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            // Dashboard
            'view_dashboard',

            // Users
            'view_users', 'create_users', 'edit_users', 'delete_users',

            // Products & Categories (Consumable)
            'view_products', 'create_products', 'edit_products', 'delete_products',
            'view_categories', 'create_categories', 'edit_categories', 'delete_categories',
            'view_brands', 'create_brands', 'edit_brands', 'delete_brands',

            // Suppliers
            'view_suppliers', 'create_suppliers', 'edit_suppliers', 'delete_suppliers',

            // Warehouses & Stock
            'view_warehouses', 'create_warehouses', 'edit_warehouses', 'delete_warehouses',
            'view_stock', 'adjust_stock',

            // Purchases
            'view_purchases', 'create_purchases', 'receive_purchases',

            // Reports
            'view_reports',

            // University Structure
            'view_schools', 'create_schools', 'edit_schools', 'delete_schools',
            'view_departments', 'create_departments', 'edit_departments', 'delete_departments',
            'view_buildings', 'create_buildings', 'edit_buildings', 'delete_buildings',
            'view_rooms', 'create_rooms', 'edit_rooms', 'delete_rooms',
            'view_employees', 'create_employees', 'edit_employees', 'delete_employees',

            // Fixed Assets
            'view_fixed_assets', 'create_fixed_assets', 'edit_fixed_assets', 'delete_fixed_assets',
            'assign_fixed_assets', 'transfer_fixed_assets', 'distribute_fixed_assets',
            'maintain_fixed_assets', 'dispose_fixed_assets',
            'view_asset_categories', 'create_asset_categories', 'edit_asset_categories', 'delete_asset_categories',

            // Requisitions
            'view_requisitions', 'create_requisitions',
            'approve_requisitions', 'reject_requisitions', 'fulfill_requisitions',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Roles
        $superAdmin       = Role::firstOrCreate(['name' => 'super-admin']);
        $fixedAssetAdmin  = Role::firstOrCreate(['name' => 'fixed-asset-admin']);
        $consumableAdmin  = Role::firstOrCreate(['name' => 'consumable-admin']);
        $requester        = Role::firstOrCreate(['name' => 'requester']);

        // Super Admin — সব কিছু
        $superAdmin->syncPermissions(Permission::all());

        // Fixed Asset Admin — Fixed asset সব + university structure view + requisition fulfill
        $fixedAssetAdmin->syncPermissions([
            'view_dashboard',
            'view_schools', 'view_departments', 'view_buildings', 'view_rooms', 'view_employees',
            'view_suppliers',
            'view_purchases', 'create_purchases', 'receive_purchases',
            'view_fixed_assets', 'create_fixed_assets', 'edit_fixed_assets', 'delete_fixed_assets',
            'assign_fixed_assets', 'transfer_fixed_assets', 'distribute_fixed_assets',
            'maintain_fixed_assets', 'dispose_fixed_assets',
            'view_asset_categories', 'create_asset_categories', 'edit_asset_categories', 'delete_asset_categories',
            'view_requisitions', 'approve_requisitions', 'reject_requisitions', 'fulfill_requisitions',
            'view_reports',
        ]);

        // Consumable Admin — Consumable সব + requisition approve/fulfill
        $consumableAdmin->syncPermissions([
            'view_dashboard',
            'view_products', 'create_products', 'edit_products', 'delete_products',
            'view_categories', 'create_categories', 'edit_categories', 'delete_categories',
            'view_brands', 'create_brands', 'edit_brands', 'delete_brands',
            'view_suppliers', 'create_suppliers', 'edit_suppliers', 'delete_suppliers',
            'view_warehouses', 'create_warehouses', 'edit_warehouses', 'delete_warehouses',
            'view_stock', 'adjust_stock',
            'view_purchases', 'create_purchases', 'receive_purchases',
            'view_requisitions', 'approve_requisitions', 'reject_requisitions', 'fulfill_requisitions',
            'view_reports',
        ]);

        // Requester — requisition create + view dashboard
        $requester->syncPermissions([
            'view_dashboard',
            'view_requisitions', 'create_requisitions',
        ]);
    }
}