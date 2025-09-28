<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            ['name' => 'Superadmin', 'description' => 'Full access'],
            ['name' => 'Admin',      'description' => 'Manage content'],
            ['name' => 'User',       'description' => 'Regular user'],
        ];

        foreach ($roles as $role) {
            // firstOrCreate mencegah duplikasi dan tetap bisa update kolom lain
            Role::updateOrCreate(
                ['name' => $role['name']],      // kondisi unik
                ['description' => $role['description']]
            );
        }
    }
}
