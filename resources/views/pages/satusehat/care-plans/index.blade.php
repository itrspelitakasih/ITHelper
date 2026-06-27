@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Care Plan SATUSEHAT" />

    <div class="space-y-6">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            @foreach ([
                ['key' => 'all', 'label' => 'Semua Care Plan', 'color' => 'text-gray-800 dark:text-white/90'],
                ['key' => 'pending', 'label' => 'Belum Terkirim', 'color' => 'text-warning-600 dark:text-warning-400'],
                ['key' => 'sent', 'label' => 'Sudah Terkirim', 'color' => 'text-success-600 dark:text-success-400'],
            ] as $item)
                <a href="{{ route('satusehat.care-plans.index', array_merge(request()->except(['status', 'page']), ['status' => $item['key']])) }}"
                    class="rounded-2xl border bg-white p-5 transition hover:border-brand-300 dark:bg-white/[0.03]
                        {{ $filters['status'] === $item['key'] ? 'border-brand-500 ring-1 ring-brand-500' : 'border-gray-200 dark:border-gray-800' }}">
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $item['label'] }}</p>
                    <p class="mt-2 text-3xl font-semibold {{ $item['color'] }}">{{ number_format($summary[$item['key']]) }}</p>
                </a>
            @endforeach
        </div>

        <x-common.component-card title="Filter Care Plan"
            desc="Mengikuti SatuSehatKirimCarePlan: instruksi tindak lanjut (RTL) Ralan dan Ranap yang telah memiliki Encounter SATUSEHAT.">
            <form method="GET" action="{{ route('satusehat.care-plans.index') }}" class="grid grid-cols-1 gap-4 lg:grid-cols-12">
                <input type="hidden" name="status" value="{{ $filters['status'] }}">
                <label class="lg:col-span-3">
                    <span class="form-label">Dari Tanggal</span>
                    <input type="date" name="from" value="{{ $filters['from'] }}" class="form-control">
                </label>
                <label class="lg:col-span-3">
                    <span class="form-label">Sampai Tanggal</span>
                    <input type="date" name="to" value="{{ $filters['to'] }}" class="form-control">
                </label>
                <label class="lg:col-span-4">
                    <span class="form-label">Cari</span>
                    <input type="search" name="search" value="{{ $filters['search'] }}" placeholder="No. rawat, RM, pasien, praktisi, atau RTL" class="form-control">
                </label>
                <div class="flex items-end gap-2 lg:col-span-2">
                    <button class="h-11 flex-1 rounded-lg bg-brand-500 px-4 text-sm font-medium text-white hover:bg-brand-600">Terapkan</button>
                    <a href="{{ route('satusehat.care-plans.index') }}" class="inline-flex h-11 items-center rounded-lg border border-gray-300 px-3 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.05]">Reset</a>
                </div>
            </form>
            @error('to') <p class="text-sm text-error-500">{{ $message }}</p> @enderror
        </x-common.component-card>

        <x-common.component-card title="Daftar Care Plan">
            <x-satusehat.send-tips resource="care-plan" />
            @if ($connectionError)
                <div class="rounded-lg border border-warning-200 bg-warning-50 p-4 text-sm text-warning-700">{{ $connectionError }}</div>
            @endif

            @php
                $selectable = $carePlans->filter(fn ($row) => blank($row->id_careplan))
                    ->map(fn ($row) => implode('|', [$row->no_rawat, $row->tgl_perawatan, $row->jam_rawat, $row->source_status]))
                    ->values();
            @endphp
            <form method="POST" action="{{ route('satusehat.care-plans.send') }}"
                x-data="{ selected: [], selectable: {{ Js::from($selectable) }} }"
                @submit.prevent="$dispatch('satusehat-send', { form: $el, label: 'Care Plan', count: selected.length })">
                @csrf
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm text-gray-500">Care Plan terbaru ditampilkan lebih dulu. Maksimal 20 data per pengiriman.</p>
                    @if (auth()->user()->hasPermission('care-plans.send'))
                        <button :disabled="selected.length === 0" class="rounded-lg bg-brand-500 px-5 py-2.5 text-sm font-medium text-white disabled:opacity-50">
                            Kirim Care Plan Terpilih (<span x-text="selected.length">0</span>)
                        </button>
                    @endif
                </div>

                <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800">
                    <div class="max-w-full overflow-x-auto custom-scrollbar">
                        <table class="w-full min-w-[1380px]">
                            <thead class="bg-gray-50 dark:bg-gray-900">
                                <tr class="border-b border-gray-200 dark:border-gray-800">
                                    <th class="px-5 py-3 text-left">
                                        <input type="checkbox" class="size-4 rounded" :checked="selectable.length > 0 && selected.length === selectable.length"
                                            @change="selected = $event.target.checked ? [...selectable] : []" aria-label="Pilih semua Care Plan pending">
                                    </th>
                                    @foreach (['Waktu Pemeriksaan', 'No. Rawat / RM', 'Pasien', 'Praktisi', 'Rawat', 'Rencana Tindak Lanjut', 'Encounter', 'Status Care Plan', 'ID Care Plan'] as $heading)
                                        <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ $heading }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($carePlans as $row)
                                    @php
                                        $sent = filled($row->id_careplan);
                                        $key = implode('|', [$row->no_rawat, $row->tgl_perawatan, $row->jam_rawat, $row->source_status]);
                                    @endphp
                                    <tr class="border-b border-gray-100 dark:border-gray-800">
                                        <td class="px-5 py-4">
                                            <input type="checkbox" name="care_plans[]" value="{{ $key }}" x-model="selected"
                                                @disabled($sent || ! auth()->user()->hasPermission('care-plans.send')) class="size-4 rounded disabled:opacity-40">
                                        </td>
                                        <td class="whitespace-nowrap px-5 py-4 text-sm">{{ \Illuminate\Support\Carbon::parse($row->tgl_perawatan)->format('d/m/Y') }}<span class="block text-xs text-gray-400">{{ $row->jam_rawat }}</span></td>
                                        <td class="whitespace-nowrap px-5 py-4 text-sm font-medium">{{ $row->no_rawat }}<span class="block text-xs font-normal text-gray-400">RM {{ $row->no_rkm_medis }}</span></td>
                                        <td class="px-5 py-4 text-sm">{{ $row->nm_pasien }}<span class="block text-xs text-gray-400">NIK {{ $row->no_ktp ?: '-' }}</span></td>
                                        <td class="px-5 py-4 text-sm">{{ $row->nama_praktisi }}<span class="block text-xs text-gray-400">NIK {{ $row->ktp_praktisi ?: '-' }}</span></td>
                                        <td class="px-5 py-4 text-sm">{{ $row->source_status }}<span class="block text-xs text-gray-400">{{ $row->stts }}</span></td>
                                        <td class="max-w-[360px] px-5 py-4 text-sm"><span class="line-clamp-3" title="{{ $row->rtl }}">{{ $row->rtl }}</span></td>
                                        <td class="max-w-[180px] px-5 py-4 text-sm"><span class="block truncate" title="{{ $row->id_encounter }}">{{ $row->id_encounter }}</span></td>
                                        <td class="px-5 py-4"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $sent ? 'bg-success-50 text-success-700' : 'bg-warning-50 text-warning-700' }}">{{ $sent ? 'Sudah Terkirim' : 'Belum Terkirim' }}</span></td>
                                        <td class="max-w-[200px] px-5 py-4 text-sm"><span class="block truncate" title="{{ $row->id_careplan }}">{{ $row->id_careplan ?: '-' }}</span></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="10" class="px-5 py-12 text-center text-sm text-gray-500">Tidak ada Care Plan yang sesuai dengan filter.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </form>
            <div>{{ $carePlans->links() }}</div>
        </x-common.component-card>
        <x-satusehat.send-popup validation-key="care_plans" />
    </div>
@endsection
