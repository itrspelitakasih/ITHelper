<div class="relative" x-data="{ dropdownOpen: false }" @click.away="dropdownOpen = false">
    <button class="flex items-center text-gray-700 dark:text-gray-400" @click.prevent="dropdownOpen = !dropdownOpen" type="button">
        <span class="mr-3 flex h-11 w-11 items-center justify-center rounded-full bg-brand-50 font-semibold text-brand-600 dark:bg-brand-500/15">
            {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
        </span>
        <span class="mr-1 hidden font-medium text-theme-sm sm:block">{{ auth()->user()->name }}</span>
        <svg class="h-5 w-5 transition-transform" :class="{ 'rotate-180': dropdownOpen }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
        </svg>
    </button>

    <div x-show="dropdownOpen" x-transition style="display: none;"
        class="absolute right-0 z-50 mt-4 w-[260px] rounded-2xl border border-gray-200 bg-white p-3 shadow-theme-lg dark:border-gray-800 dark:bg-gray-dark">
        <div class="border-b border-gray-200 pb-3 dark:border-gray-800">
            <span class="block font-medium text-gray-700 dark:text-gray-300">{{ auth()->user()->name }}</span>
            <span class="mt-0.5 block text-xs text-gray-500">{{ auth()->user()->email }}</span>
        </div>
        <a href="{{ route('profile') }}" class="mt-3 flex rounded-lg px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-white/5">Profil</a>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="flex w-full rounded-lg px-3 py-2 text-left text-sm text-error-600 hover:bg-error-50 dark:hover:bg-error-500/10">Keluar</button>
        </form>
    </div>
</div>
