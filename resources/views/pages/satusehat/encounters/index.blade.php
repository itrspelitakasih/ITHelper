@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Encounter SATUSEHAT" />

    <div class="space-y-6">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            @foreach ([
                ['key' => 'all', 'label' => 'Semua Encounter', 'color' => 'text-gray-800 dark:text-white/90'],
                ['key' => 'pending', 'label' => 'Belum Terkirim', 'color' => 'text-warning-600 dark:text-warning-400'],
                ['key' => 'sent', 'label' => 'Sudah Terkirim', 'color' => 'text-success-600 dark:text-success-400'],
            ] as $item)
                <a href="{{ route('satusehat.encounters.index', array_merge(request()->except(['status', 'page']), ['status' => $item['key']])) }}"
                    class="rounded-2xl border bg-white p-5 transition hover:border-brand-300 dark:bg-white/[0.03]
                        {{ $filters['status'] === $item['key'] ? 'border-brand-500 ring-1 ring-brand-500' : 'border-gray-200 dark:border-gray-800' }}">
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $item['label'] }}</p>
                    <p class="mt-2 text-3xl font-semibold {{ $item['color'] }}">{{ number_format($summary[$item['key']]) }}</p>
                </a>
            @endforeach
        </div>

        <x-common.component-card title="Filter Encounter"
            desc="Data mengikuti referensi query SatuSehatKirimEncounter: kunjungan sudah bayar dan poli telah memiliki mapping lokasi SATUSEHAT.">
            <form method="GET" action="{{ route('satusehat.encounters.index') }}" class="grid grid-cols-1 gap-4 lg:grid-cols-12">
                <input type="hidden" name="status" value="{{ $filters['status'] }}">

                <label class="lg:col-span-3">
                    <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Dari Tanggal</span>
                    <input type="date" name="from" value="{{ $filters['from'] }}"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white/90">
                </label>

                <label class="lg:col-span-3">
                    <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Sampai Tanggal</span>
                    <input type="date" name="to" value="{{ $filters['to'] }}"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white/90">
                </label>

                <label class="lg:col-span-4">
                    <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Cari</span>
                    <input type="search" name="search" value="{{ $filters['search'] }}"
                        placeholder="No. rawat, RM, pasien, dokter, atau poli"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm text-gray-800 outline-none placeholder:text-gray-400 focus:border-brand-500 dark:border-gray-700 dark:text-white/90">
                </label>

                <div class="flex items-end gap-2 lg:col-span-2">
                    <button type="submit"
                        class="h-11 flex-1 rounded-lg bg-brand-500 px-4 text-sm font-medium text-white hover:bg-brand-600">
                        Terapkan
                    </button>
                    <a href="{{ route('satusehat.encounters.index') }}"
                        class="inline-flex h-11 items-center rounded-lg border border-gray-300 px-3 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.05]">
                        Reset
                    </a>
                </div>
            </form>

            @error('to')
                <p class="text-sm text-error-500">{{ $message }}</p>
            @enderror
        </x-common.component-card>

        <x-common.component-card title="Daftar Encounter">
            <x-satusehat.send-tips resource="encounter" />
            @if ($connectionError)
                <div class="rounded-lg border border-warning-200 bg-warning-50 p-4 text-sm text-warning-700 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-400">
                    {{ $connectionError }}
                </div>
            @endif

            <form method="POST" action="{{ route('satusehat.encounters.send') }}"
                x-data="{ selected: [], selectable: {{ Js::from($encounters->filter(fn ($encounter) => blank($encounter->id_encounter))->pluck('no_rawat')->values()) }} }"
                @submit.prevent="$dispatch('satusehat-send', { form: $el, label: 'Encounter', count: selected.length })">
                @csrf
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Data terbaru ditampilkan lebih dulu. Maksimal 20 encounter per pengiriman.
                    </p>
                    @if (auth()->user()->hasPermission('encounters.send'))
                        <button type="submit" :disabled="selected.length === 0"
                            class="rounded-lg bg-brand-500 px-5 py-2.5 text-sm font-medium text-white hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-50">
                            Kirim Encounter Terpilih (<span x-text="selected.length">0</span>)
                        </button>
                    @endif
                </div>

                <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800">
                    <div class="max-w-full overflow-x-auto custom-scrollbar">
                        <table class="w-full min-w-[1410px]">
                        <thead class="bg-gray-50 dark:bg-gray-900">
                            <tr class="border-b border-gray-200 dark:border-gray-800">
                                <th class="px-5 py-3 text-left">
                                    <input type="checkbox" class="size-4 rounded"
                                        :checked="selectable.length > 0 && selected.length === selectable.length"
                                        @change="selected = $event.target.checked ? [...selectable] : []"
                                        aria-label="Pilih semua encounter pending pada halaman ini">
                                </th>
                                @foreach (['Tanggal', 'No. Rawat / RM', 'Pasien', 'Dokter', 'Poli / Lokasi', 'Pelayanan', 'Status Encounter', 'ID Encounter'] as $heading)
                                    <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">{{ $heading }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($encounters as $encounter)
                                @php($sent = filled($encounter->id_encounter))
                                <tr class="border-b border-gray-100 dark:border-gray-800">
                                    <td class="px-5 py-4">
                                        <input type="checkbox" name="encounters[]" value="{{ $encounter->no_rawat }}"
                                            x-model="selected" @disabled($sent || ! auth()->user()->hasPermission('encounters.send'))
                                            class="size-4 rounded disabled:cursor-not-allowed disabled:opacity-40"
                                            aria-label="Pilih encounter {{ $encounter->no_rawat }}">
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-4 text-sm text-gray-600 dark:text-gray-300">
                                        {{ \Illuminate\Support\Carbon::parse($encounter->tgl_registrasi)->format('d/m/Y') }}
                                        <span class="block text-xs text-gray-400">{{ $encounter->jam_reg }}</span>
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-4 text-sm text-gray-700 dark:text-gray-300">
                                        <span class="font-medium text-gray-800 dark:text-white/90">{{ $encounter->no_rawat }}</span>
                                        <span class="block text-xs text-gray-400">RM {{ $encounter->no_rkm_medis }}</span>
                                    </td>
                                    <td class="px-5 py-4 text-sm text-gray-700 dark:text-gray-300">
                                        <span class="font-medium text-gray-800 dark:text-white/90">{{ $encounter->nm_pasien }}</span>
                                        <span class="block text-xs text-gray-400">NIK {{ $encounter->no_ktp ?: '-' }}</span>
                                    </td>
                                    <td class="px-5 py-4 text-sm text-gray-700 dark:text-gray-300">
                                        {{ $encounter->nama_dokter }}
                                        <span class="block text-xs text-gray-400">NIK {{ $encounter->ktp_dokter ?: '-' }}</span>
                                    </td>
                                    <td class="px-5 py-4 text-sm text-gray-700 dark:text-gray-300">
                                        {{ $encounter->nm_poli }}
                                        <span class="block text-xs text-gray-400">Location {{ $encounter->id_lokasi_satusehat }}</span>
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-4 text-sm text-gray-600 dark:text-gray-300">
                                        {{ $encounter->status_lanjut }}
                                        <span class="block text-xs text-gray-400">{{ $encounter->stts }}</span>
                                    </td>
                                    <td class="px-5 py-4">
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium
                                            {{ $sent ? 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-400' : 'bg-warning-50 text-warning-700 dark:bg-warning-500/15 dark:text-warning-400' }}">
                                            {{ $sent ? 'Sudah Terkirim' : 'Belum Terkirim' }}
                                        </span>
                                    </td>
                                    <td class="max-w-[260px] px-5 py-4 text-sm text-gray-600 dark:text-gray-300">
                                        <span class="block truncate" title="{{ $encounter->id_encounter }}">{{ $encounter->id_encounter ?: '-' }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="px-5 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                                        Tidak ada encounter yang sesuai dengan filter.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                        </table>
                    </div>
                </div>
            </form>

            <div>
                {{ $encounters->links() }}
            </div>
        </x-common.component-card>
        <x-satusehat.send-popup validation-key="encounters" />
    </div>
@endsection
