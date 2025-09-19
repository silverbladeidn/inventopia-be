<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Superadmin User
        User::create([
            'name' => 'Super Administrator',
            'username' => 'superadmin',
            'email' => 'superadmin@example.com',
            'password' => Hash::make('password123'),
            'role' => 'Superadmin',
            'is_blocked' => false,
        ]);

        // Admin User 1
        User::create([
            'name' => 'Administrator',
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password123'),
            'role' => 'Admin',
            'is_blocked' => false,
        ]);

        // Admin User 2
        User::create([
            'name' => 'John Admin',
            'username' => 'johnadmin',
            'email' => 'johnadmin@example.com',
            'password' => Hash::make('password123'),
            'role' => 'Admin',
            'is_blocked' => false,
        ]);

        // Admin User 3
        User::create([
            'name' => 'Jane Admin',
            'username' => 'janeadmin',
            'email' => 'janeadmin@example.com',
            'password' => Hash::make('password123'),
            'role' => 'Admin',
            'is_blocked' => false,
        ]);

        // Blocked Admin (untuk testing)
        User::create([
            'name' => 'Blocked Admin',
            'username' => 'blockedadmin',
            'email' => 'blocked@example.com',
            'password' => Hash::make('password123'),
            'role' => 'Admin',
            'is_blocked' => true,
        ]);

        // Admin dengan profile photo
        User::create([
            'name' => 'Admin Photo',
            'username' => 'adminphoto',
            'email' => 'adminphoto@example.com',
            'password' => Hash::make('password123'),
            'role' => 'Admin',
            'is_blocked' => false,
        ]);

        // Test Admin
        User::create([
            'name' => 'Test Admin',
            'username' => 'testadmin',
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
            'role' => 'Admin',
            'is_blocked' => false,
        ]);
    }
}
