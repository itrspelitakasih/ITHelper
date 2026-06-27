<div class="mx-auto mb-10 w-full max-w-60">
    <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-5 text-center dark:border-gray-800 dark:bg-white/[0.03]">
        <h3 class="mb-2 font-semibold text-gray-900 dark:text-white">
            About {{ $appName }}
        </h3>
        <p class="mb-4 text-gray-500 text-theme-sm dark:text-gray-400">
            Portal integrasi SIMRS dan layanan SATUSEHAT.
        </p>
        <button type="button" @click="$store.sidebar.surpriseOpen = true"
            class="flex w-full items-center justify-center rounded-lg bg-brand-500 p-3 font-medium text-white text-theme-sm hover:bg-brand-600">
            Surprise Me!
        </button>
    </div>
</div>
