@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Database & Integrasi" />

    @php
        $input = fn ($key, $default = '') => old($key, $setting?->{$key} ?? $default);
    @endphp

    <form method="POST" action="{{ route('settings.database.update') }}" class="space-y-6">
        @csrf
        @method('PUT')

        @if (session('success'))
            <div class="rounded-lg border border-success-200 bg-success-50 p-4 text-sm text-success-700 dark:border-success-800/60 dark:bg-success-500/10 dark:text-success-400">
                {{ session('success') }}
            </div>
        @endif
        
        @error('connection')
            <div class="rounded-lg border border-error-200 bg-error-50 p-4 text-sm text-error-700 dark:border-error-800/60 dark:bg-error-500/10 dark:text-error-400">
                {{ $message }}
            </div>
        @enderror

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2 items-start">
            
            <!-- Part 1: Database Connections (Left Side) -->
            <div class="space-y-6">
                
                <!-- SIMRS Connection Card -->
                <x-common.component-card title="Koneksi SIMRS Eksternal" desc="Kredensial database utama SIMRS Khanza untuk penarikan data medis.">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <label class="block sm:col-span-2">
                            <span class="form-label">Nama Koneksi</span>
                            <input name="name" value="{{ $input('name', 'SIMRS Khanza') }}" required class="form-control">
                        </label>
                        <label class="block">
                            <span class="form-label">Driver</span>
                            <select name="driver" class="form-control">
                                <option value="mysql" @selected($input('driver', 'mysql') === 'mysql')>MySQL</option>
                                <option value="mariadb" @selected($input('driver') === 'mariadb')>MariaDB</option>
                            </select>
                        </label>
                        <label class="block">
                            <span class="form-label">Charset</span>
                            <select name="charset" class="form-control">
                                <option value="latin1" @selected($input('charset', 'latin1') === 'latin1')>latin1 (Default Khanza)</option>
                                <option value="utf8mb4" @selected($input('charset') === 'utf8mb4')>utf8mb4</option>
                            </select>
                        </label>
                        <label class="block">
                            <span class="form-label">Host</span>
                            <input name="host" value="{{ $input('host', '127.0.0.1') }}" required class="form-control">
                        </label>
                        <label class="block">
                            <span class="form-label">Port</span>
                            <input type="number" name="port" value="{{ $input('port', 3306) }}" required class="form-control">
                        </label>
                        <label class="block">
                            <span class="form-label">Database</span>
                            <input name="database" value="{{ $input('database') }}" required class="form-control">
                        </label>
                        <label class="block">
                            <span class="form-label">Username</span>
                            <input name="username" value="{{ $input('username') }}" required class="form-control">
                        </label>
                        <label class="block sm:col-span-2">
                            <span class="form-label">Password</span>
                            <input type="password" name="password" class="form-control" placeholder="{{ $setting ? 'Kosongkan untuk mempertahankan password lama' : '' }}">
                        </label>
                        <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-400 sm:col-span-2">
                            <input type="checkbox" name="is_active" value="1" @checked($input('is_active', true)) class="size-4 rounded">
                            Gunakan koneksi ini untuk modul SATUSEHAT
                        </label>
                    </div>
                </x-common.component-card>

                <!-- LIS Connection Card -->
                <x-common.component-card title="Aplikasi LIS (Laboratory Info System)" desc="Kredensial database dan URL LIS untuk integrasi data laboratorium.">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <label class="block sm:col-span-2">
                            <span class="form-label">LIS URL</span>
                            <input type="url" name="lis_url" value="{{ $input('lis_url', 'http://localhost/lis') }}" class="form-control" placeholder="http://localhost/lis">
                            @error('lis_url')
                                <p class="mt-1.5 text-sm text-error-500">{{ $message }}</p>
                            @enderror
                        </label>
                        <label class="block">
                            <span class="form-label">LIS DB Host</span>
                            <input name="lis_db_host" value="{{ $input('lis_db_host') }}" class="form-control" placeholder="e.g. 127.0.0.1">
                        </label>
                        <label class="block">
                            <span class="form-label">LIS DB Port</span>
                            <input type="number" name="lis_db_port" value="{{ $input('lis_db_port', 3306) }}" class="form-control">
                        </label>
                        <label class="block">
                            <span class="form-label">LIS DB Database Name</span>
                            <input name="lis_db_database" value="{{ $input('lis_db_database') }}" class="form-control" placeholder="e.g. lisypm">
                        </label>
                        <label class="block">
                            <span class="form-label">LIS DB Username</span>
                            <input name="lis_db_username" value="{{ $input('lis_db_username') }}" class="form-control" placeholder="e.g. root">
                        </label>
                        <label class="block sm:col-span-2">
                            <span class="form-label">LIS DB Password</span>
                            <input type="password" name="lis_db_password" class="form-control" placeholder="{{ $setting?->lis_db_password ? 'Kosongkan untuk mempertahankan password LIS lama' : '' }}">
                        </label>
                    </div>
                </x-common.component-card>

                <!-- LIS Bridging / Antara Connection Card -->
                <x-common.component-card title="LIS Database Antara" desc="Kredensial database penampung bridging LIS (kosongkan jika menggunakan database SIMRS utama).">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <label class="block">
                            <span class="form-label">Antara DB Host</span>
                            <input name="lis_antara_db_host" value="{{ $input('lis_antara_db_host') }}" class="form-control" placeholder="e.g. 127.0.0.1">
                        </label>
                        <label class="block">
                            <span class="form-label">Antara DB Port</span>
                            <input type="number" name="lis_antara_db_port" value="{{ $input('lis_antara_db_port', 3306) }}" class="form-control">
                        </label>
                        <label class="block">
                            <span class="form-label">Antara DB Database Name</span>
                            <input name="lis_antara_db_database" value="{{ $input('lis_antara_db_database') }}" class="form-control" placeholder="e.g. sik_bridging_lab">
                        </label>
                        <label class="block">
                            <span class="form-label">Antara DB Username</span>
                            <input name="lis_antara_db_username" value="{{ $input('lis_antara_db_username') }}" class="form-control" placeholder="e.g. root">
                        </label>
                        <label class="block sm:col-span-2">
                            <span class="form-label">Antara DB Password</span>
                            <input type="password" name="lis_antara_db_password" class="form-control" placeholder="{{ $setting?->lis_antara_db_password ? 'Kosongkan untuk mempertahankan password Antara lama' : '' }}">
                        </label>
                    </div>
                </x-common.component-card>

            </div>

            <!-- Part 2: API & Gateway Services (Right Side) -->
            <div class="space-y-6">
                
                <!-- SATUSEHAT Credentials Card -->
                <x-common.component-card title="API SATUSEHAT Kemenkes" desc="Kredensial API SATUSEHAT dari Kemenkes RI untuk interoperabilitas RME.">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <label class="block">
                            <span class="form-label">Client ID</span>
                            <input type="password" name="satusehat_client_id" class="form-control"
                                placeholder="{{ $setting?->satusehat_client_id ? 'Kosongkan untuk mempertahankan Client ID lama' : '' }}">
                        </label>
                        <label class="block">
                            <span class="form-label">Client Secret</span>
                            <input type="password" name="satusehat_client_secret" class="form-control"
                                placeholder="{{ $setting?->satusehat_client_secret ? 'Kosongkan untuk mempertahankan Client Secret lama' : '' }}">
                        </label>
                        <label class="block sm:col-span-2">
                            <span class="form-label">Organization ID</span>
                            <input name="satusehat_organization_id" value="{{ $input('satusehat_organization_id') }}" class="form-control" placeholder="e.g. 1000252">
                        </label>
                        <label class="block sm:col-span-2">
                            <span class="form-label">URL Auth</span>
                            <input type="url" name="satusehat_auth_url" value="{{ $input('satusehat_auth_url', 'https://api-satusehat-stg.dto.kemkes.go.id/oauth2/v1') }}" class="form-control">
                        </label>
                        <label class="block sm:col-span-2">
                            <span class="form-label">URL FHIR</span>
                            <input type="url" name="satusehat_fhir_url" value="{{ $input('satusehat_fhir_url', 'https://api-satusehat-stg.dto.kemkes.go.id/fhir-r4/v1') }}" class="form-control">
                        </label>
                    </div>
                </x-common.component-card>


            </div>

        </div>

        @if ($errors->any() && ! $errors->has('connection') && ! $errors->has('lis_url') && ! $errors->has('whatsapp_api_url') && ! $errors->has('whatsapp_webhook_secret'))
            <div class="rounded-lg border border-error-200 bg-error-50 p-4 text-sm text-error-700">
                {{ $errors->first() }}
            </div>
        @endif

        <!-- Form Actions Footer (Sticked at bottom) -->
        <div class="flex items-center justify-between gap-3 rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900 transition">
            <p class="text-xs text-gray-500">
                @if ($setting?->last_tested_at)
                    Terakhir berhasil diuji: {{ $setting->last_tested_at->format('d/m/Y H:i') }}
                @else
                    Belum pernah diuji.
                @endif
            </p>
            <button type="submit" class="rounded-lg bg-brand-500 px-5 py-3 text-sm font-medium text-white hover:bg-brand-600 transition">
                Tes Koneksi & Simpan Pengaturan
            </button>
        </div>
    </form>
@endsection
