<?php

namespace App\Http\Controllers;

use App\Models\ExternalDatabaseSetting;
use App\Services\ExternalDatabaseManager;
use Illuminate\Http\Request;
use Throwable;

class ExternalDatabaseSettingController extends Controller
{
    public function edit()
    {
        return view('pages.settings.database', [
            'title' => 'Database Eksternal',
            'setting' => ExternalDatabaseSetting::query()->latest()->first(),
            'whatsappApiUrl' => \App\Models\ApplicationSetting::query()->where('key', 'whatsapp_api_url')->value('value') ?: 'http://localhost:3000',
            'whatsappBasicAuthUser' => \App\Models\ApplicationSetting::query()->where('key', 'whatsapp_basic_auth_user')->value('value') ?: '',
            'whatsappBasicAuthPassword' => \App\Models\ApplicationSetting::query()->where('key', 'whatsapp_basic_auth_password')->value('value') ?: '',
            'whatsappWebhookSecret' => \App\Models\ApplicationSetting::query()->where('key', 'whatsapp_webhook_secret')->value('value') ?: 'secret',
        ]);
    }

    public function update(Request $request, ExternalDatabaseManager $manager)
    {
        $setting = ExternalDatabaseSetting::query()->latest()->first();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'driver' => ['required', 'in:mysql,mariadb'],
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'database' => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'max:100'],
            'password' => ['nullable', 'string', 'max:255'],
            'charset' => ['required', 'in:latin1,utf8mb4'],
            'is_active' => ['nullable', 'boolean'],
            'satusehat_client_id' => ['nullable', 'string', 'max:255'],
            'satusehat_client_secret' => ['nullable', 'string', 'max:255'],
            'satusehat_organization_id' => ['nullable', 'string', 'max:255'],
            'satusehat_auth_url' => ['nullable', 'url', 'max:255'],
            'satusehat_fhir_url' => ['nullable', 'url', 'max:255'],
            'lis_url' => ['nullable', 'string', 'max:255'],
            'lis_db_host' => ['nullable', 'string', 'max:255'],
            'lis_db_port' => ['nullable', 'integer', 'between:1,65535'],
            'lis_db_database' => ['nullable', 'string', 'max:100'],
            'lis_db_username' => ['nullable', 'string', 'max:100'],
            'lis_db_password' => ['nullable', 'string', 'max:255'],
            'lis_antara_db_host' => ['nullable', 'string', 'max:255'],
            'lis_antara_db_port' => ['nullable', 'integer', 'between:1,65535'],
            'lis_antara_db_database' => ['nullable', 'string', 'max:100'],
            'lis_antara_db_username' => ['nullable', 'string', 'max:100'],
            'lis_antara_db_password' => ['nullable', 'string', 'max:255'],
            'whatsapp_api_url' => ['nullable', 'url', 'max:255'],
            'whatsapp_basic_auth_user' => ['nullable', 'string', 'max:100'],
            'whatsapp_basic_auth_password' => ['nullable', 'string', 'max:100'],
            'whatsapp_webhook_secret' => ['nullable', 'string', 'max:100'],
        ]);

        // Save WhatsApp settings
        $whatsappPassword = $data['whatsapp_basic_auth_password'] ?? '';
        if ($whatsappPassword === '') {
            $whatsappPassword = \App\Models\ApplicationSetting::query()->where('key', 'whatsapp_basic_auth_password')->value('value') ?: '';
        }

        \App\Models\ApplicationSetting::query()->updateOrCreate(['key' => 'whatsapp_api_url'], ['value' => $data['whatsapp_api_url'] ?? '']);
        \App\Models\ApplicationSetting::query()->updateOrCreate(['key' => 'whatsapp_basic_auth_user'], ['value' => $data['whatsapp_basic_auth_user'] ?? '']);
        \App\Models\ApplicationSetting::query()->updateOrCreate(['key' => 'whatsapp_basic_auth_password'], ['value' => $whatsappPassword]);
        \App\Models\ApplicationSetting::query()->updateOrCreate(['key' => 'whatsapp_webhook_secret'], ['value' => $data['whatsapp_webhook_secret'] ?? '']);

        foreach (['password', 'satusehat_client_id', 'satusehat_client_secret', 'lis_db_password', 'lis_antara_db_password'] as $secret) {
            if (($data[$secret] ?? '') === '' && $setting) {
                $data[$secret] = $setting->{$secret};
            }
        }

        $data['is_active'] = $request->boolean('is_active');

        // Test SIMRS connection
        try {
            $manager->test($manager->configuration($data));
        } catch (Throwable $exception) {
            return back()
                ->withInput($request->except(['password', 'lis_db_password', 'lis_antara_db_password']))
                ->withErrors(['connection' => 'Koneksi SIMRS Eksternal gagal: '.$exception->getMessage()]);
        }

        // Test LIS connection
        if (!empty($data['lis_db_host'])) {
            try {
                $lisConfig = [
                    'driver' => 'mysql',
                    'host' => $data['lis_db_host'],
                    'port' => $data['lis_db_port'] ?? 3306,
                    'database' => $data['lis_db_database'],
                    'username' => $data['lis_db_username'],
                    'password' => $data['lis_db_password'],
                    'charset' => 'utf8',
                    'collation' => 'utf8_general_ci',
                    'strict' => false,
                ];
                
                config(['database.connections.lis_test' => $lisConfig]);
                \Illuminate\Support\Facades\DB::purge('lis_test');
                \Illuminate\Support\Facades\DB::connection('lis_test')->select('select 1');
                \Illuminate\Support\Facades\DB::purge('lis_test');
            } catch (Throwable $exception) {
                return back()
                    ->withInput($request->except(['password', 'lis_db_password', 'lis_antara_db_password']))
                    ->withErrors(['connection' => 'Koneksi LIS gagal: '.$exception->getMessage()]);
            }
        }

        // Test LIS Antara connection
        if (!empty($data['lis_antara_db_host'])) {
            try {
                $antaraConfig = [
                    'driver' => 'mysql',
                    'host' => $data['lis_antara_db_host'],
                    'port' => $data['lis_antara_db_port'] ?? 3306,
                    'database' => $data['lis_antara_db_database'],
                    'username' => $data['lis_antara_db_username'],
                    'password' => $data['lis_antara_db_password'],
                    'charset' => 'utf8',
                    'collation' => 'utf8_general_ci',
                    'strict' => false,
                ];
                
                config(['database.connections.lis_antara_test' => $antaraConfig]);
                \Illuminate\Support\Facades\DB::purge('lis_antara_test');
                \Illuminate\Support\Facades\DB::connection('lis_antara_test')->select('select 1');
                \Illuminate\Support\Facades\DB::purge('lis_antara_test');
            } catch (Throwable $exception) {
                return back()
                    ->withInput($request->except(['password', 'lis_db_password', 'lis_antara_db_password']))
                    ->withErrors(['connection' => 'Koneksi Database Antara gagal: '.$exception->getMessage()]);
            }
        }

        $data['last_tested_at'] = now();
        ExternalDatabaseSetting::query()->updateOrCreate(['id' => $setting?->id], $data);

        return back()->with('success', 'Koneksi berhasil diuji dan semua pengaturan disimpan.');
    }
}
