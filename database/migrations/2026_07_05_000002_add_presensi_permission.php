<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Tambah permission presensi.view ke tabel permissions.
     */
    public function up(): void
    {
        // Insert permission jika belum ada
        if (!DB::table('permissions')->where('slug', 'presensi.view')->exists()) {
            DB::table('permissions')->insert([
                'name'       => 'Lihat Presensi',
                'slug'       => 'presensi.view',
                'group'      => 'Presensi',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Tambahkan permission ini ke semua role yang memiliki slug 'super-admin'
        $superAdminRole = DB::table('roles')->where('slug', 'super-admin')->first();
        if ($superAdminRole) {
            $permission = DB::table('permissions')->where('slug', 'presensi.view')->first();
            if ($permission) {
                $exists = DB::table('permission_role')
                    ->where('permission_id', $permission->id)
                    ->where('role_id', $superAdminRole->id)
                    ->exists();
                if (!$exists) {
                    DB::table('permission_role')->insert([
                        'permission_id' => $permission->id,
                        'role_id'       => $superAdminRole->id,
                    ]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $permission = DB::table('permissions')->where('slug', 'presensi.view')->first();
        if ($permission) {
            DB::table('permission_role')->where('permission_id', $permission->id)->delete();
            DB::table('permissions')->where('id', $permission->id)->delete();
        }
    }
};
