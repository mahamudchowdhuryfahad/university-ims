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
            'view_dashboard',
            'view_users', 'create_users', 'edit_users', 'delete_users',
            'view_roles', 'create_roles', 'edit_roles', 'delete_roles',
            'view_products', 'create_products', 'edit_products', 'delete_products',
            'view_categories', 'create_categories', 'edit_categories', 'delete_categories',
            'view_brands', 'create_brands', 'edit_brands', 'delete_brands',
            'view_suppliers', 'create_suppliers', 'edit_suppliers', 'delete_suppliers',
            'view_warehouses', 'create_warehouses', 'edit_warehouses', 'delete_warehouses',
            'view_stock', 'adjust_stock',
            'view_purchases', 'create_purchases', 'edit_purchases', 'delete_purchases',
            'view_sales', 'create_sales', 'edit_sales', 'delete_sales',
            'view_reports',
            'view_schools', 'create_schools', 'edit_schools', 'delete_schools',
            'view_departments', 'create_departments', 'edit_departments', 'delete_departments',
            'view_buildings', 'create_buildings', 'edit_buildings', 'delete_buildings',
            'view_rooms', 'create_rooms', 'edit_rooms', 'delete_rooms',
            'view_employees', 'create_employees', 'edit_employees', 'delete_employees',
            'view_fixed_assets', 'create_fixed_assets', 'edit_fixed_assets', 'delete_fixed_assets',
            'assign_fixed_assets', 'transfer_fixed_assets', 'maintain_fixed_assets', 'dispose_fixed_assets',
            'view_requisitions', 'create_requisitions', 'approve_requisitions', 'reject_requisitions', 'fulfill_requisitions',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $superAdmin = Role::firstOrCreate(['name' => 'super-admin']);
        $admin      = Role::firstOrCreate(['name' => 'admin']);
        $manager    = Role::firstOrCreate(['name' => 'manager']);
        $staff      = Role::firstOrCreate(['name' => 'staff']);

        $superAdmin->givePermissionTo(Permission::all());
        $admin->givePermissionTo(Permission::all());
        $manager->givePermissionTo([
            'view_dashboard', 'view_products', 'view_categories', 'view_brands',
            'view_stock', 'adjust_stock', 'view_purchases', 'view_sales',
            'view_reports', 'view_fixed_assets', 'view_requisitions', 'approve_requisitions',
        ]);
        $staff->givePermissionTo([
            'view_dashboard', 'view_products', 'view_stock',
            'view_requisitions', 'create_requisitions',
        ]);
    }
}
