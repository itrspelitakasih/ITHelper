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
        ]);

        foreach (['password', 'satusehat_client_id', 'satusehat_client_secret'] as $secret) {
            if (($data[$secret] ?? '') === '' && $setting) {
                $data[$secret] = $setting->{$secret};
            }
        }

        $data['is_active'] = $request->boolean('is_active');

        try {
            $manager->test($manager->configuration($data));
        } catch (Throwable $exception) {
            return back()
                ->withInput($request->except('password'))
                ->withErrors(['connection' => 'Koneksi gagal: '.$exception->getMessage()]);
        }

        $data['last_tested_at'] = now();
        ExternalDatabaseSetting::query()->updateOrCreate(['id' => $setting?->id], $data);

        return back()->with('success', 'Koneksi berhasil diuji dan pengaturan database eksternal disimpan.');
    }
}
