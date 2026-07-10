<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Permission;
use App\Models\Role;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $permissionsData = collect([
            ['name' => 'Lihat Encounter', 'slug' => 'encounters.view', 'group' => 'SATUSEHAT'],
            ['name' => 'Kirim Encounter', 'slug' => 'encounters.send', 'group' => 'SATUSEHAT'],
            ['name' => 'Lihat Episode of Care', 'slug' => 'episode-of-care.view', 'group' => 'SATUSEHAT'],
            ['name' => 'Kirim Episode of Care', 'slug' => 'episode-of-care.send', 'group' => 'SATUSEHAT'],
            ['name' => 'Lihat Observation TTV', 'slug' => 'ttv.view', 'group' => 'SATUSEHAT'],
            ['name' => 'Kirim Observation TTV', 'slug' => 'ttv.send', 'group' => 'SATUSEHAT'],
            ['name' => 'Lihat Observation', 'slug' => 'observations.view', 'group' => 'SATUSEHAT'],
            ['name' => 'Kirim Observation', 'slug' => 'observations.send', 'group' => 'SATUSEHAT'],
            ['name' => 'Lihat Condition', 'slug' => 'conditions.view', 'group' => 'SATUSEHAT'],
            ['name' => 'Kirim Condition', 'slug' => 'conditions.send', 'group' => 'SATUSEHAT'],
            ['name' => 'Lihat Procedure', 'slug' => 'procedures.view', 'group' => 'SATUSEHAT'],
            ['name' => 'Kirim Procedure', 'slug' => 'procedures.send', 'group' => 'SATUSEHAT'],
            ['name' => 'Lihat Care Plan', 'slug' => 'care-plans.view', 'group' => 'SATUSEHAT'],
            ['name' => 'Kirim Care Plan', 'slug' => 'care-plans.send', 'group' => 'SATUSEHAT'],
            ['name' => 'Lihat Clinical Impression', 'slug' => 'clinical-impressions.view', 'group' => 'SATUSEHAT'],
            ['name' => 'Kirim Clinical Impression', 'slug' => 'clinical-impressions.send', 'group' => 'SATUSEHAT'],
            ['name' => 'Lihat Diagnostic Report', 'slug' => 'diagnostic-reports.view', 'group' => 'SATUSEHAT'],
            ['name' => 'Kirim Diagnostic Report', 'slug' => 'diagnostic-reports.send', 'group' => 'SATUSEHAT'],
            ['name' => 'Lihat Medication', 'slug' => 'medications.view', 'group' => 'SATUSEHAT'],
            ['name' => 'Kirim Medication', 'slug' => 'medications.send', 'group' => 'SATUSEHAT'],
            ['name' => 'Lihat Questionnaire', 'slug' => 'questionnaires.view', 'group' => 'SATUSEHAT'],
            ['name' => 'Kirim Questionnaire', 'slug' => 'questionnaires.send', 'group' => 'SATUSEHAT'],
            ['name' => 'Lihat Rekam Medis', 'slug' => 'rme.view', 'group' => 'SATUSEHAT'],
            ['name' => 'Kirim Rekam Medis', 'slug' => 'rme.send', 'group' => 'SATUSEHAT'],
            ['name' => 'Lihat Risk Assessment', 'slug' => 'risk-assessments.view', 'group' => 'SATUSEHAT'],
            ['name' => 'Kirim Risk Assessment', 'slug' => 'risk-assessments.send', 'group' => 'SATUSEHAT'],
            ['name' => 'Lihat Service Request', 'slug' => 'service-requests.view', 'group' => 'SATUSEHAT'],
            ['name' => 'Kirim Service Request', 'slug' => 'service-requests.send', 'group' => 'SATUSEHAT'],
            ['name' => 'Lihat Specimen', 'slug' => 'specimens.view', 'group' => 'SATUSEHAT'],
            ['name' => 'Kirim Specimen', 'slug' => 'specimens.send', 'group' => 'SATUSEHAT'],
            ['name' => 'Lihat Registrasi Rawat Jalan', 'slug' => 'rawat-jalan.registrasi.view', 'group' => 'Rawat Jalan'],
            ['name' => 'Lihat Registrasi IGD', 'slug' => 'rawat-jalan.igd.view', 'group' => 'Rawat Jalan'],
            ['name' => 'Lihat Laboratorium', 'slug' => 'laboratorium.view', 'group' => 'Pemeriksaan Penunjang'],
            ['name' => 'Kelola Aplikasi', 'slug' => 'settings.app', 'group' => 'Pengaturan'],
            ['name' => 'Kelola Database Eksternal', 'slug' => 'settings.database', 'group' => 'Pengaturan'],
            ['name' => 'Kelola User', 'slug' => 'users.manage', 'group' => 'Pengaturan'],
            ['name' => 'Kelola Role', 'slug' => 'roles.manage', 'group' => 'Pengaturan'],
            ['name' => 'Import Database', 'slug' => 'update-data.import', 'group' => 'Update Data'],
        ]);

        // Clean up deleted permissions (WhatsApp & Presensi)
        Permission::query()->whereIn('slug', [
            'whatsapp.view',
            'whatsapp.send',
            'whatsapp.settings',
            'presensi.view',
        ])->delete();

        $permissions = $permissionsData->map(fn ($permission) => Permission::query()->updateOrCreate(
            ['slug' => $permission['slug']],
            $permission
        ));

        $role = Role::query()->updateOrCreate(
            ['slug' => 'super-admin'],
            ['name' => 'Super Admin', 'description' => 'Akses penuh ke seluruh aplikasi']
        );
        $role->permissions()->sync($permissions->pluck('id'));

        $user = User::query()->updateOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'Administrator', 'password' => 'password']
        );
        $user->roles()->syncWithoutDetaching([$role->id]);
    }
}
