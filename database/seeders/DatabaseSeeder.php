<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    // 💡 REMOVED: "use WithoutModelEvents;" completely!

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleAndPermissionSeeder::class,
            CategorySeeder::class
        ]);

        $adminEmail = 'fracos2025@gmail.com';

        $adminUser = User::firstOrCreate(
            ['email' => $adminEmail],
            [
                'name' => 'System Administrator',
                'password' => Hash::make('Password123!'),
                'email_verified_at' => now(),
            ]
        );

        $role = DB::table('roles')
            ->where('name', 'super-admin')
            ->where('guard_name', 'sanctum')
            ->first();

        if ($role) {
            DB::table('model_has_roles')->insertOrIgnore([
                'role_id'    => $role->id,
                'model_type' => User::class,
                'model_id'   => $adminUser->id,
            ]);
        }

        $this->command->info('Master application seeding cascade complete!');
    }
}
