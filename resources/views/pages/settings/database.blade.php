@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Database Eksternal" />

    <x-common.component-card title="Koneksi SIMRS Eksternal"
        desc="Kredensial disimpan terenkripsi di database internal. Penyimpanan hanya dilakukan setelah koneksi berhasil diuji.">
        @if (session('success'))
            <div class="rounded-lg border border-success-200 bg-success-50 p-4 text-sm text-success-700">{{ session('success') }}</div>
        @endif
        @error('connection')
            <div class="rounded-lg border border-error-200 bg-error-50 p-4 text-sm text-error-700">{{ $message }}</div>
        @enderror

        <form method="POST" action="{{ route('settings.database.update') }}" class="grid grid-cols-1 gap-5 md:grid-cols-2">
            @csrf
            @method('PUT')
            @php
                $input = fn ($key, $default = '') => old($key, $setting?->{$key} ?? $default);
            @endphp

            <label class="block md:col-span-2">
                <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Nama Koneksi</span>
                <input name="name" value="{{ $input('name', 'SIMRS Khanza') }}" required class="form-control">
            </label>
            <label class="block">
                <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Driver</span>
                <select name="driver" class="form-control">
                    <option value="mysql" @selected($input('driver', 'mysql') === 'mysql')>MySQL</option>
                    <option value="mariadb" @selected($input('driver') === 'mariadb')>MariaDB</option>
                </select>
            </label>
            <label class="block">
                <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Charset</span>
                <select name="charset" class="form-control">
                    <option value="latin1" @selected($input('charset', 'latin1') === 'latin1')>latin1 (Khanza default)</option>
                    <option value="utf8mb4" @selected($input('charset') === 'utf8mb4')>utf8mb4</option>
                </select>
            </label>
            <label class="block">
                <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Host</span>
                <input name="host" value="{{ $input('host', '127.0.0.1') }}" required class="form-control">
            </label>
            <label class="block">
                <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Port</span>
                <input type="number" name="port" value="{{ $input('port', 3306) }}" required class="form-control">
            </label>
            <label class="block">
                <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Database</span>
                <input name="database" value="{{ $input('database') }}" required class="form-control">
            </label>
            <label class="block">
                <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Username</span>
                <input name="username" value="{{ $input('username') }}" required class="form-control">
            </label>
            <label class="block md:col-span-2">
                <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Password</span>
                <input type="password" name="password" class="form-control" placeholder="{{ $setting ? 'Kosongkan untuk mempertahankan password lama' : '' }}">
            </label>
            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-400 md:col-span-2">
                <input type="checkbox" name="is_active" value="1" @checked($input('is_active', true)) class="size-4 rounded">
                Gunakan koneksi ini untuk mengambil data Encounter
            </label>

            <div class="border-t border-gray-200 pt-5 dark:border-gray-800 md:col-span-2">
                <h3 class="font-medium text-gray-800 dark:text-white/90">Aplikasi LIS</h3>
                <p class="mt-1 text-xs text-gray-500">Kebutuhan integrasi dengan Laboratory Information System.</p>
            </div>
            <label class="block md:col-span-2">
                <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">LIS URL</span>
                <input type="url" name="lis_url" value="{{ $input('lis_url', 'http://localhost/lis') }}" class="form-control" placeholder="http://localhost/lis">
                @error('lis_url')
                    <p class="mt-1.5 text-sm text-error-500">{{ $message }}</p>
                @enderror
            </label>
            <label class="block">
                <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">LIS DB Host</span>
                <input name="lis_db_host" value="{{ $input('lis_db_host') }}" class="form-control" placeholder="e.g. 127.0.0.1">
            </label>
            <label class="block">
                <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">LIS DB Port</span>
                <input type="number" name="lis_db_port" value="{{ $input('lis_db_port', 3306) }}" class="form-control">
            </label>
            <label class="block">
                <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">LIS DB Database Name</span>
                <input name="lis_db_database" value="{{ $input('lis_db_database') }}" class="form-control" placeholder="e.g. lisypm">
            </label>
            <label class="block">
                <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">LIS DB Username</span>
                <input name="lis_db_username" value="{{ $input('lis_db_username') }}" class="form-control" placeholder="e.g. root">
            </label>
            <label class="block md:col-span-2">
                <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">LIS DB Password</span>
                <input type="password" name="lis_db_password" class="form-control" placeholder="{{ $setting?->lis_db_password ? 'Kosongkan untuk mempertahankan password LIS lama' : '' }}">
            </label>

            <div class="border-t border-gray-200 pt-5 dark:border-gray-800 md:col-span-2">
                <h3 class="font-medium text-gray-800 dark:text-white/90">LIS Database Antara / Bridging</h3>
                <p class="mt-1 text-xs text-gray-500">Kredensial database bridging LIS (misal: sik_bridging_lab). Kosongkan jika menggunakan database SIMRS utama.</p>
            </div>
            <label class="block">
                <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Antara DB Host</span>
                <input name="lis_antara_db_host" value="{{ $input('lis_antara_db_host') }}" class="form-control" placeholder="e.g. 127.0.0.1">
            </label>
            <label class="block">
                <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Antara DB Port</span>
                <input type="number" name="lis_antara_db_port" value="{{ $input('lis_antara_db_port', 3306) }}" class="form-control">
            </label>
            <label class="block">
                <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Antara DB Database Name</span>
                <input name="lis_antara_db_database" value="{{ $input('lis_antara_db_database') }}" class="form-control" placeholder="e.g. sik_bridging_lab">
            </label>
            <label class="block">
                <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Antara DB Username</span>
                <input name="lis_antara_db_username" value="{{ $input('lis_antara_db_username') }}" class="form-control" placeholder="e.g. root">
            </label>
            <label class="block md:col-span-2">
                <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Antara DB Password</span>
                <input type="password" name="lis_antara_db_password" class="form-control" placeholder="{{ $setting?->lis_antara_db_password ? 'Kosongkan untuk mempertahankan password Antara lama' : '' }}">
            </label>

            <div class="border-t border-gray-200 pt-5 dark:border-gray-800 md:col-span-2">
                <h3 class="font-medium text-gray-800 dark:text-white/90">API SATUSEHAT</h3>
                <p class="mt-1 text-xs text-gray-500">Diperlukan untuk tombol kirim Encounter. Client ID dan Client Secret disimpan terenkripsi.</p>
            </div>
            <label class="block">
                <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Client ID</span>
                <input type="password" name="satusehat_client_id" class="form-control"
                    placeholder="{{ $setting?->satusehat_client_id ? 'Kosongkan untuk mempertahankan Client ID lama' : '' }}">
            </label>
            <label class="block">
                <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Client Secret</span>
                <input type="password" name="satusehat_client_secret" class="form-control"
                    placeholder="{{ $setting?->satusehat_client_secret ? 'Kosongkan untuk mempertahankan Client Secret lama' : '' }}">
            </label>
            <label class="block md:col-span-2">
                <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Organization ID</span>
                <input name="satusehat_organization_id" value="{{ $input('satusehat_organization_id') }}" class="form-control">
            </label>
            <label class="block">
                <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">URL Auth</span>
                <input type="url" name="satusehat_auth_url" value="{{ $input('satusehat_auth_url', 'https://api-satusehat-stg.dto.kemkes.go.id/oauth2/v1') }}"
                    class="form-control">
            </label>
            <label class="block">
                <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">URL FHIR</span>
                <input type="url" name="satusehat_fhir_url" value="{{ $input('satusehat_fhir_url', 'https://api-satusehat-stg.dto.kemkes.go.id/fhir-r4/v1') }}"
                    class="form-control">
            </label>

            @if ($errors->any() && ! $errors->has('connection'))
                <div class="rounded-lg border border-error-200 bg-error-50 p-4 text-sm text-error-700 md:col-span-2">
                    {{ $errors->first() }}
                </div>
            @endif

            <div class="flex items-center justify-between gap-3 md:col-span-2">
                <p class="text-xs text-gray-500">
                    @if ($setting?->last_tested_at)
                        Terakhir berhasil diuji: {{ $setting->last_tested_at->format('d/m/Y H:i') }}
                    @else
                        Belum pernah diuji.
                    @endif
                </p>
                <button class="rounded-lg bg-brand-500 px-5 py-3 text-sm font-medium text-white hover:bg-brand-600">
                    Tes Koneksi & Simpan
                </button>
            </div>
        </form>
    </x-common.component-card>
@endsection
