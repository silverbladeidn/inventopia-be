<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // ambil role yang sudah ada dari RolePermissionSeeder
        $superadminRole = Role::where('name', 'Superadmin')->first();
        $adminRole      = Role::where('name', 'Admin')->first();
        $userRole       = Role::where('name', 'User')->first();

        // === Superadmin ===
        User::updateOrCreate(
            ['email' => 'superadmin@example.com'],
            [
                'name'       => 'Super Administrator',
                'username'   => 'superadmin',
                'password'   => Hash::make('password123'),
                'is_blocked' => false,
                'role_id'    => $superadminRole?->id,
            ]
        );

        // === Admins ===
        $adminUsers = [
            ['Administrator', 'admin',      'admin@example.com'],
            ['John Admin',    'johnadmin',  'johnadmin@example.com'],
            ['Admin Photo',   'adminphoto', 'adminphoto@example.com'],
            ['Test Admin',    'testadmin',  'test@example.com'],
        ];

        foreach ($adminUsers as [$name, $username, $email]) {
            User::updateOrCreate(
                ['email' => $email],
                [
                    'name'       => $name,
                    'username'   => $username,
                    'password'   => Hash::make('password123'),
                    'is_blocked' => false,
                    'role_id'    => $adminRole?->id,
                ]
            );
        }

        // === Admin yang diblokir ===
        User::updateOrCreate(
            ['email' => 'blocked@example.com'],
            [
                'name'       => 'Blocked Admin',
                'username'   => 'blockedadmin',
                'password'   => Hash::make('password123'),
                'is_blocked' => true,
                'role_id'    => $adminRole?->id,
            ]
        );

        // === User biasa ===
        User::updateOrCreate(
            ['email' => 'janeadmin@example.com'],
            [
                'name'       => 'Jane Admin',
                'username'   => 'janeadmin',
                'password'   => Hash::make('password123'),
                'is_blocked' => false,
                'role_id'    => $userRole?->id,
            ]
        );
    }
}
