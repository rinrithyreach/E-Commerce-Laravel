<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleAndPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Reset cached roles and permissions (Spatie Best Practice)
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // 2. Define Core Permissions
        $permissions = [
            'manage_vendors',
            'manage_categories',
            'view_analytics',
            'manage_products',
            'manage_orders',
            'process_payouts',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web'
            ]);
        }

        // 3. Define and Create System Roles
        $superAdmin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $vendor     = Role::firstOrCreate(['name' => 'vendor', 'guard_name' => 'web']);
        $customer   = Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);

        // 4. Assign Permissions to Roles
        $superAdmin->syncPermissions(Permission::all()); // Admin gets everything

        $vendor->syncPermissions([
            'manage_products',
            'manage_orders'
        ]); // Vendors get only store operations
    }
}
