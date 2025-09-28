<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        // Pisahkan menjadi approvalnote (admin) dan requestnote (user)
        $resources = [
            'dashboard'       => ['view'],
            'homeuser'        => ['view'],
            'inventaris'      => ['view', 'create', 'edit', 'delete'], // User pakai ini untuk action request
            'inventory'       => ['view', 'create', 'edit', 'delete'], // Management produk (admin)
            'stocknote'       => ['view'],
            'requestnote'     => ['view'], // ✅ User HANYA view history request sendiri
            'approvalnote'    => ['view', 'approve', 'partially_approve', 'reject', 'complete'], // ✅ Admin untuk approval
            'users'           => ['view', 'create', 'edit', 'delete'],
            'email_settings'  => ['view', 'edit'],
        ];

        $permissions = [];
        foreach ($resources as $res => $actions) {
            foreach ($actions as $act) {
                $permissions[] = [
                    'name'        => "{$act}_{$res}",
                    'description' => ucfirst($act) . ' ' . ucfirst(str_replace('_', ' ', $res)),
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ];
            }
        }

        DB::table('permissions')->insert($permissions);
        $allPermissionIds = DB::table('permissions')->pluck('id');

        // Superadmin -> semua permission
        if ($superadmin = DB::table('roles')->where('name', 'Superadmin')->first()) {
            foreach ($allPermissionIds as $pid) {
                DB::table('role_permission')->updateOrInsert([
                    'role_id'       => $superadmin->id,
                    'permission_id' => $pid,
                ]);
            }
        }

        // Admin -> bisa approval di approvalnote
        if ($admin = DB::table('roles')->where('name', 'Admin')->first()) {
            $adminPerms = DB::table('permissions')
                ->whereIn('name', [
                    // Inventory management
                    'view_dashboard',
                    'view_inventory',
                    'create_inventory',
                    'edit_inventory',
                    'delete_inventory',
                    'view_stocknote',

                    // Approvalnote - ADMIN BISA APPROVAL
                    'view_approvalnote',
                    'approve_approvalnote',
                    'partially_approve_approvalnote',
                    'reject_approvalnote',
                    'complete_approvalnote',

                    // Juga bisa view inventaris & requestnote
                    'view_inventaris',
                    'view_requestnote',
                ])->pluck('id');

            foreach ($adminPerms as $pid) {
                DB::table('role_permission')->updateOrInsert([
                    'role_id'       => $admin->id,
                    'permission_id' => $pid,
                ]);
            }
        }

        // User -> view requestnote saja, action pakai inventaris permissions
        if ($user = DB::table('roles')->where('name', 'User')->first()) {
            $userPerms = DB::table('permissions')
                ->whereIn('name', [
                    'view_homeuser',
                    'view_inventaris',     // ✅ Browse produk
                    'create_inventaris',   // ✅ Buat request dari inventaris
                    'edit_inventaris',     // ✅ Update request (draft/pending)
                    'delete_inventaris',   // ✅ Cancel/delete request
                    'view_requestnote',    // ✅ HANYA VIEW history request sendiri
                    // TIDAK ada akses approvalnote
                ])->pluck('id');

            foreach ($userPerms as $pid) {
                DB::table('role_permission')->updateOrInsert([
                    'role_id'       => $user->id,
                    'permission_id' => $pid,
                ]);
            }
        }
    }
}
