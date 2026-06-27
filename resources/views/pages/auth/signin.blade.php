@extends('layouts.fullscreen-layout')

@section('content')
    <div class="relative z-1 bg-white p-6 sm:p-0 dark:bg-gray-900">
        <div class="flex min-h-screen w-full flex-col justify-center sm:p-0 lg:flex-row dark:bg-gray-900">
            <div class="flex w-full flex-1 flex-col lg:w-1/2">
                <div class="mx-auto flex w-full max-w-md flex-1 flex-col justify-center py-10">
                    <div class="mb-5 sm:mb-8">
                        <h1 class="mb-2 text-title-sm font-semibold text-gray-800 sm:text-title-md dark:text-white/90">
                            Masuk
                        </h1>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Masukkan email dan password untuk mengakses {{ $appName }}.
                        </p>
                    </div>

                    <form method="POST" action="{{ route('login.store') }}">
                        @csrf
                        <div class="space-y-6">
                            <div>
                                <label for="email" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                    Email<span class="text-error-500">*</span>
                                </label>
                                <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus
                                    autocomplete="email" placeholder="Masukkan email"
                                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
                                @error('email')
                                    <p class="mt-1.5 text-sm text-error-500">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="password" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                    Password<span class="text-error-500">*</span>
                                </label>
                                <div x-data="{ showPassword: false }" class="relative">
                                    <input :type="showPassword ? 'text' : 'password'" id="password" name="password" required
                                        autocomplete="current-password" placeholder="Masukkan password"
                                        class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent py-2.5 pr-11 pl-4 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
                                    <button type="button" @click="showPassword = !showPassword"
                                        class="absolute top-1/2 right-4 z-30 -translate-y-1/2 text-gray-500 dark:text-gray-400"
                                        aria-label="Tampilkan atau sembunyikan password">
                                        <svg x-show="!showPassword" class="fill-current" width="20" height="20" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" clip-rule="evenodd" d="M10 13.862c-2.767 0-5.132-1.725-6.077-4.16C4.868 7.268 7.233 5.543 10 5.543s5.132 1.725 6.077 4.16c-.945 2.434-3.31 4.159-6.077 4.159Zm0-9.819c-3.518 0-6.505 2.266-7.585 5.416a.75.75 0 0 0 0 .487c1.08 3.15 4.067 5.416 7.585 5.416s6.505-2.266 7.585-5.416a.75.75 0 0 0 0-.487C16.505 6.309 13.518 4.043 10 4.043Zm-.009 3.801a1.858 1.858 0 1 0 .015 3.717 1.858 1.858 0 0 0-.015-3.717Z"/>
                                        </svg>
                                        <svg x-show="showPassword" class="fill-current" width="20" height="20" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" clip-rule="evenodd" d="M4.638 3.577a.75.75 0 0 0-1.061 1.061l1.276 1.276a8.225 8.225 0 0 0-2.438 3.545.75.75 0 0 0 0 .487c1.08 3.15 4.067 5.416 7.585 5.416a8.14 8.14 0 0 0 3.499-.802l1.864 1.863a.75.75 0 0 0 1.06-1.06L4.638 3.577Zm7.723 9.844A6.49 6.49 0 0 1 10 13.862c-2.767 0-5.132-1.725-6.077-4.16a6.5 6.5 0 0 1 1.996-2.723l2.27 2.27a1.858 1.858 0 0 0 2.258 2.259l1.914 1.913Zm2.459-1.69a6.5 6.5 0 0 0 1.257-2.029C15.132 7.268 12.767 5.543 10 5.543c-.427 0-.845.041-1.249.12L7.522 4.434A8.17 8.17 0 0 1 10 4.043c3.518 0 6.505 2.266 7.585 5.416a.75.75 0 0 1 0 .487 8.217 8.217 0 0 1-1.704 2.847L14.82 11.73Z"/>
                                        </svg>
                                    </button>
                                </div>
                                @error('password')
                                    <p class="mt-1.5 text-sm text-error-500">{{ $message }}</p>
                                @enderror
                            </div>

                            <label class="flex cursor-pointer items-center text-sm font-normal text-gray-700 select-none dark:text-gray-400">
                                <input type="checkbox" name="remember" value="1" @checked(old('remember'))
                                    class="mr-3 size-5 rounded-md border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-900">
                                Ingat saya
                            </label>

                            <button type="submit"
                                class="flex w-full items-center justify-center rounded-lg bg-brand-500 px-4 py-3 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600">
                                Masuk
                            </button>
                        </div>
                    </form>

                    <p class="mt-5 text-center text-sm font-normal text-gray-500 sm:text-start dark:text-gray-400">
                        Akun dikelola oleh administrator {{ $appName }}.
                    </p>
                </div>
            </div>

            <div class="relative hidden min-h-screen w-full items-center bg-brand-950 lg:grid lg:w-1/2 dark:bg-white/5">
                <div class="z-1 flex items-center justify-center">
                    <x-common.common-grid-shape />
                    <div class="flex max-w-sm flex-col items-center px-6">
                        @if ($appLogoUrl)
                            <img src="{{ $appLogoUrl }}" alt="{{ $appName }}" class="mb-5 h-20 max-w-56 object-contain">
                        @else
                            <div class="mb-5 flex size-16 items-center justify-center rounded-2xl bg-brand-500 text-2xl font-semibold text-white">
                                {{ strtoupper(mb_substr($appName, 0, 1)) }}
                            </div>
                        @endif
                        <h2 class="mb-3 text-center text-2xl font-semibold text-white">{{ $appName }}</h2>
                        <p class="text-center text-gray-400 dark:text-white/60">
                            Portal integrasi SIMRS dan layanan SATUSEHAT.
                        </p>
                    </div>
                </div>
            </div>

            <div class="fixed right-6 bottom-6 z-50">
                <button type="button"
                    class="inline-flex size-14 items-center justify-center rounded-full bg-brand-500 text-white transition-colors hover:bg-brand-600"
                    @click.prevent="$store.theme.toggle()" aria-label="Ganti tema">
                    <svg class="hidden fill-current dark:block" width="20" height="20" viewBox="0 0 20 20">
                        <path d="M10 1.542a.75.75 0 0 1 .75.75v1.25a.75.75 0 0 1-1.5 0v-1.25a.75.75 0 0 1 .75-.75Zm0 5.251a3.207 3.207 0 1 0 0 6.414 3.207 3.207 0 0 0 0-6.414Zm0-1.5a4.707 4.707 0 1 1 0 9.414 4.707 4.707 0 0 1 0-9.414Zm8.458 4.707a.75.75 0 0 1-.75.75h-1.25a.75.75 0 0 1 0-1.5h1.25a.75.75 0 0 1 .75.75ZM4.292 10a.75.75 0 0 1-.75.75h-1.25a.75.75 0 0 1 0-1.5h1.25a.75.75 0 0 1 .75.75ZM10 15.708a.75.75 0 0 1 .75.75v1.25a.75.75 0 0 1-1.5 0v-1.25a.75.75 0 0 1 .75-.75Z"/>
                    </svg>
                    <svg class="fill-current dark:hidden" width="20" height="20" viewBox="0 0 20 20">
                        <path d="M17.455 11.97a.75.75 0 0 0-.51-.55 5.918 5.918 0 0 1-8.365-8.364.75.75 0 0 0-.74-1.235 8.458 8.458 0 1 0 10.34 10.34.75.75 0 0 0-.725-.191Zm-7.455 4.989A6.958 6.958 0 0 1 6.997 3.502a7.418 7.418 0 0 0 9.501 9.501A6.96 6.96 0 0 1 10 16.959Z"/>
                    </svg>
                </button>
            </div>
        </div>
    </div>
@endsection
