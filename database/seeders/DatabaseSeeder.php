<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
      $this->call([
          RoleAndPermissionSeeder::class,
      ]);

    // Create a default Admin user (relies on the roles created above)
        $adminEmail = 'admin@marketplace.com';

        $adminUser = User::firstOrCreate(
            ['email' => $adminEmail],
            [
                'name' => 'System Administrator',
                'password' => Hash::make('Password123!'),
                'email_verified_at' => now(), // Auto-verified for local dev testing
            ]
        );

        if (!$adminUser->hasRole('admin')) {
            $adminUser->assignRole('admin');
        }

        $this->command->info('Master application seeding cascade complete!');

    }
}
