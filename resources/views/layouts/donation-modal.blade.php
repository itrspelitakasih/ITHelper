<div x-show="$store.sidebar.surpriseOpen" x-cloak
    class="fixed inset-0 z-999999 flex items-center justify-center bg-gray-900/50 px-4 py-6"
    @keydown.escape.window="$store.sidebar.surpriseOpen = false">
    <button type="button" class="absolute inset-0 h-full w-full cursor-default" @click="$store.sidebar.surpriseOpen = false"
        aria-label="Tutup popup donasi"></button>

    <div class="relative w-full max-w-md rounded-lg bg-white p-6 shadow-theme-xl dark:bg-gray-900">
        <div class="mb-5 flex items-start justify-between gap-4">
            <div>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Donasi</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Dukungan untuk pengembangan {{ $appName }}.
                </p>
            </div>
            <button type="button" @click="$store.sidebar.surpriseOpen = false"
                class="flex size-9 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-white/[0.05]"
                aria-label="Tutup popup donasi">
                <svg width="18" height="18" viewBox="0 0 18 18" fill="none">
                    <path d="M4.5 4.5L13.5 13.5M13.5 4.5L4.5 13.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                </svg>
            </button>
        </div>

        <div class="space-y-3">
            <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-800" x-data="{ copied: false }">
                <div class="mb-3 flex h-10 items-center">
                    <img src="{{ asset('images/donation/dana-logo.svg') }}" alt="DANA" class="max-h-10 max-w-28 object-contain">
                </div>
                <p class="text-xs font-medium uppercase text-gray-400">Nomor DANA</p>
                <div class="mt-1 flex flex-wrap items-center justify-between gap-3">
                    <span class="text-xl font-bold tracking-wide text-gray-900 dark:text-white break-all">62853 8790 3054</span>
                    <button type="button" @click="navigator.clipboard.writeText('6285387903054').then(() => { copied = true; setTimeout(() => copied = false, 2000) })"
                        class="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-gray-200 bg-gray-50 px-2.5 py-1 text-xs font-medium text-gray-700 hover:bg-gray-100 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700 transition cursor-pointer"
                        :class="copied ? 'border-success-500 bg-success-50 text-success-700 hover:bg-success-50 dark:border-success-500/30 dark:bg-success-500/10 dark:text-success-400' : ''">
                        <svg x-show="!copied" class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                        <svg x-show="copied" class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" style="display: none;">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                        <span x-text="copied ? 'Disalin' : 'Salin'">Salin</span>
                    </button>
                </div>
            </div>

            <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-800" x-data="{ copied: false }">
                <div class="mb-3 flex h-10 items-center">
                    <img src="{{ asset('images/donation/seabank-logo.svg') }}" alt="SeaBank" class="max-h-10 max-w-32 object-contain">
                </div>
                <p class="text-xs font-medium uppercase text-gray-400">A/N Mahadil Amin Mahfullah</p>
                <div class="mt-1 flex flex-wrap items-center justify-between gap-3">
                    <span class="text-xl font-bold tracking-wide text-gray-900 dark:text-white break-all">9017 4888 6720</span>
                    <button type="button" @click="navigator.clipboard.writeText('901748886720').then(() => { copied = true; setTimeout(() => copied = false, 2000) })"
                        class="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-gray-200 bg-gray-50 px-2.5 py-1 text-xs font-medium text-gray-700 hover:bg-gray-100 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700 transition cursor-pointer"
                        :class="copied ? 'border-success-500 bg-success-50 text-success-700 hover:bg-success-50 dark:border-success-500/30 dark:bg-success-500/10 dark:text-success-400' : ''">
                        <svg x-show="!copied" class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                        <svg x-show="copied" class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" style="display: none;">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                        <span x-text="copied ? 'Disalin' : 'Salin'">Salin</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
