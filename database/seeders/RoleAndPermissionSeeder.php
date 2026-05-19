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

        // 2. Define Core Permissions explicitly for Sanctum tokens[cite: 1]
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
                'guard_name' => 'sanctum' // Changed to sanctum[cite: 1]
            ]);
        }

        // 3. Define and Create System Roles using the sanctum guard[cite: 1]
        // Swapped 'admin' to 'super-admin' to precisely match your API route middleware logic[cite: 1]
        $superAdmin = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'sanctum']);
        $vendor     = Role::firstOrCreate(['name' => 'vendor', 'guard_name' => 'sanctum']);
        $customer   = Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'sanctum']);

        // 4. Assign Permissions to Roles
        $superAdmin->syncPermissions(Permission::where('guard_name', 'sanctum')->get()); // Admin gets everything safely

        $vendor->syncPermissions([
            'manage_products',
            'manage_orders'
        ]); // Vendors get only store operations
    }
}
