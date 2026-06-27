@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Aplikasi" />

    <x-common.component-card title="Identitas Aplikasi"
        desc="Nama ini akan tampil di judul halaman, sidebar, header mobile, dan halaman masuk.">
        @if (session('success'))
            <div class="mb-5 rounded-lg border border-success-200 bg-success-50 p-4 text-sm text-success-700">{{ session('success') }}</div>
        @endif

        <form method="POST" action="{{ route('settings.app.update') }}" enctype="multipart/form-data" class="space-y-5">
            @csrf
            @method('PUT')

            <label class="block">
                <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Nama Aplikasi</span>
                <input name="app_name" value="{{ old('app_name', $settingAppName) }}" required maxlength="100" class="form-control">
                @error('app_name')
                    <p class="mt-1.5 text-sm text-error-500">{{ $message }}</p>
                @enderror
            </label>

            <div class="grid grid-cols-1 gap-5 md:grid-cols-[220px_1fr]">
                <div>
                    <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Logo Saat Ini</span>
                    <div class="flex h-28 items-center justify-center rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-white/[0.03]">
                        @if ($settingLogoUrl)
                            <img src="{{ $settingLogoUrl }}" alt="{{ $settingAppName }}" class="max-h-20 max-w-full object-contain">
                        @else
                            <span class="flex size-14 items-center justify-center rounded-xl bg-brand-500 text-xl font-semibold text-white">
                                {{ strtoupper(mb_substr($settingAppName, 0, 1)) }}
                            </span>
                        @endif
                    </div>
                </div>

                <div>
                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Upload Logo</span>
                        <input type="file" name="logo" accept="image/png,image/jpeg,image/webp,image/svg+xml"
                            class="h-11 w-full overflow-hidden rounded-lg border border-gray-300 bg-transparent text-sm text-gray-500 file:mr-5 file:h-11 file:border-0 file:border-r file:border-gray-200 file:bg-gray-50 file:px-4 file:text-sm file:font-medium file:text-gray-700 hover:file:bg-gray-100 dark:border-gray-700 dark:text-gray-400 dark:file:border-gray-800 dark:file:bg-white/[0.03] dark:file:text-gray-400">
                    </label>
                    <p class="mt-2 text-xs text-gray-500">Format: JPG, PNG, WebP, atau SVG. Maksimal 2 MB.</p>
                    @error('logo')
                        <p class="mt-1.5 text-sm text-error-500">{{ $message }}</p>
                    @enderror

                    @if ($settingLogoUrl)
                        <label class="mt-4 flex items-center gap-2 text-sm text-gray-700 dark:text-gray-400">
                            <input type="checkbox" name="remove_logo" value="1" class="size-4 rounded">
                            Hapus logo custom
                        </label>
                    @endif
                </div>
            </div>

            <div class="flex justify-end">
                <button class="rounded-lg bg-brand-500 px-5 py-3 text-sm font-medium text-white hover:bg-brand-600">
                    Simpan
                </button>
            </div>
        </form>
    </x-common.component-card>
@endsection
