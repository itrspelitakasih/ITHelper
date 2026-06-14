@props([
    'validationKey',
])

@php
    $failures = session('send_failures', []);
    $hasResult = session()->has('success')
        || count($failures) > 0
        || $errors->has('send')
        || $errors->has($validationKey);
@endphp

<div x-data="{
        open: @js($hasResult),
        mode: @js($hasResult ? 'result' : 'confirm'),
        form: null,
        label: '',
        count: 0,
        loading: false,
        prepare(detail) {
            this.form = detail.form;
            this.label = detail.label;
            this.count = detail.count;
            this.mode = 'confirm';
            this.open = true;
        },
        send() {
            if (!this.form || this.loading) return;
            this.loading = true;
            this.mode = 'loading';
            this.form.submit();
        },
        close() {
            if (!this.loading) this.open = false;
        }
    }"
    @satusehat-send.window="prepare($event.detail)"
    x-show="open"
    x-cloak
    @keydown.escape.window="close()"
    class="fixed inset-0 z-99999 flex items-center justify-center overflow-y-auto p-5">
    <div class="fixed inset-0 h-full w-full bg-gray-400/50 backdrop-blur-[32px]" @click="close()"></div>

    <div @click.stop
        class="relative w-full max-w-[640px] rounded-3xl bg-white p-6 shadow-theme-xl dark:bg-gray-900 sm:p-8">
        <button type="button" x-show="mode !== 'loading'" @click="close()"
            class="absolute right-4 top-4 flex h-10 w-10 items-center justify-center rounded-full bg-gray-100 text-gray-500 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-400">
            <span class="text-2xl leading-none">&times;</span>
        </button>

        <div x-show="mode === 'confirm'">
            <h3 class="pr-12 text-xl font-semibold text-gray-800 dark:text-white/90">Konfirmasi Pengiriman</h3>
            <div class="mt-5">
                <x-ui.alert variant="warning" title="Kirim data ke SATUSEHAT">
                    <p class="text-sm text-gray-600 dark:text-gray-300">
                        Anda akan mengirim <strong x-text="count"></strong> data
                        <strong x-text="label"></strong>. Proses dapat memerlukan beberapa saat.
                    </p>
                </x-ui.alert>
            </div>
            <div class="mt-6 flex justify-end gap-3">
                <button type="button" @click="close()"
                    class="rounded-lg border border-gray-300 px-5 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300">
                    Batal
                </button>
                <button type="button" @click="send()"
                    class="rounded-lg bg-brand-500 px-5 py-2.5 text-sm font-medium text-white hover:bg-brand-600">
                    Ya, Kirim Sekarang
                </button>
            </div>
        </div>

        <div x-show="mode === 'loading'" class="py-8 text-center">
            <div class="mx-auto h-14 w-14 animate-spin rounded-full border-4 border-gray-200 border-t-brand-500 dark:border-gray-700"></div>
            <h3 class="mt-5 text-xl font-semibold text-gray-800 dark:text-white/90">Sedang Mengirim ke SATUSEHAT</h3>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                Jangan tutup atau memuat ulang halaman sampai proses selesai.
            </p>
        </div>

        <div x-show="mode === 'result'">
            <h3 class="pr-12 text-xl font-semibold text-gray-800 dark:text-white/90">Hasil Pengiriman</h3>
            <div class="mt-5 max-h-[60vh] space-y-4 overflow-y-auto custom-scrollbar">
                @if (session('success'))
                    <x-ui.alert variant="success" title="Pengiriman selesai" :message="session('success')" />
                @endif

                @error('send')
                    <x-ui.alert variant="error" title="Pengiriman gagal" :message="$message" />
                @enderror

                @error($validationKey)
                    <x-ui.alert variant="error" title="Data belum dipilih" :message="$message" />
                @enderror

                @if (count($failures) > 0)
                    <x-ui.alert variant="warning" :title="count($failures).' data gagal dikirim'">
                        <div class="mt-2 space-y-2 text-sm text-gray-600 dark:text-gray-300">
                            @foreach ($failures as $key => $failure)
                                <div class="rounded-lg bg-white/70 p-3 dark:bg-gray-900/40">
                                    <p class="font-medium">{{ str_replace('|', ' / ', $key) }}</p>
                                    <p class="mt-1">{{ $failure }}</p>
                                </div>
                            @endforeach
                        </div>
                    </x-ui.alert>
                @endif
            </div>
            <div class="mt-6 flex justify-end">
                <button type="button" @click="close()"
                    class="rounded-lg bg-brand-500 px-5 py-2.5 text-sm font-medium text-white hover:bg-brand-600">
                    Tutup
                </button>
            </div>
        </div>
    </div>
</div>
