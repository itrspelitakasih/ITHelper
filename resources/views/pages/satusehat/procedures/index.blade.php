@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Procedure SATUSEHAT" />
    <div class="space-y-6">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            @foreach ([['key' => 'all', 'label' => 'Semua Procedure'], ['key' => 'pending', 'label' => 'Belum Terkirim'], ['key' => 'sent', 'label' => 'Sudah Terkirim']] as $item)
                <a href="{{ route('satusehat.procedures.index', array_merge(request()->except(['status', 'page']), ['status' => $item['key']])) }}" class="rounded-2xl border bg-white p-5 transition hover:border-brand-300 dark:bg-white/[0.03] {{ $filters['status'] === $item['key'] ? 'border-brand-500 ring-1 ring-brand-500' : 'border-gray-200 dark:border-gray-800' }}">
                    <p class="text-sm text-gray-500">{{ $item['label'] }}</p><p class="mt-2 text-3xl font-semibold text-gray-800 dark:text-white/90">{{ number_format($summary[$item['key']]) }}</p>
                </a>
            @endforeach
        </div>

        <x-common.component-card title="Filter Procedure" desc="Mengikuti SatuSehatKirimProcedure: prosedur ICD-9 pasien yang telah memiliki Encounter SATUSEHAT.">
            <form method="GET" action="{{ route('satusehat.procedures.index') }}" class="grid grid-cols-1 gap-4 lg:grid-cols-12">
                <input type="hidden" name="status" value="{{ $filters['status'] }}">
                <label class="lg:col-span-3"><span class="form-label">Dari Tanggal</span><input type="date" name="from" value="{{ $filters['from'] }}" class="form-control"></label>
                <label class="lg:col-span-3"><span class="form-label">Sampai Tanggal</span><input type="date" name="to" value="{{ $filters['to'] }}" class="form-control"></label>
                <label class="lg:col-span-4"><span class="form-label">Cari</span><input type="search" name="search" value="{{ $filters['search'] }}" placeholder="No. rawat, RM, pasien, ICD-9, atau prosedur" class="form-control"></label>
                <div class="flex items-end gap-2 lg:col-span-2"><button class="h-11 flex-1 rounded-lg bg-brand-500 px-4 text-sm font-medium text-white hover:bg-brand-600">Terapkan</button><a href="{{ route('satusehat.procedures.index') }}" class="inline-flex h-11 items-center rounded-lg border border-gray-300 px-3 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.05]">Reset</a></div>
            </form>
        </x-common.component-card>

        <x-common.component-card title="Daftar Procedure">
            <x-satusehat.send-tips resource="procedure" />
            @if ($connectionError) <x-ui.alert variant="warning" title="Database eksternal tidak dapat diakses" :message="$connectionError" /> @endif
            @php
                $selectable = $procedures->filter(fn ($row) => blank($row->id_procedure))->map(fn ($row) => implode('|', [$row->no_rawat, $row->kode, $row->status_prosedur]))->values();
            @endphp
            <form method="POST" action="{{ route('satusehat.procedures.send') }}" x-data="{ selected: [], selectable: {{ Js::from($selectable) }} }" @submit.prevent="$dispatch('satusehat-send', { form: $el, label: 'Procedure', count: selected.length })">
                @csrf
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm text-gray-500">Data terbaru ditampilkan lebih dulu. Maksimal 20 Procedure per pengiriman.</p>
                    @if (auth()->user()->hasPermission('procedures.send')) <button :disabled="selected.length === 0" class="rounded-lg bg-brand-500 px-5 py-2.5 text-sm font-medium text-white disabled:opacity-50">Kirim Procedure Terpilih (<span x-text="selected.length">0</span>)</button> @endif
                </div>
                <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800"><div class="max-w-full overflow-x-auto custom-scrollbar">
                    <table class="w-full min-w-[1380px]"><thead class="bg-gray-50 dark:bg-gray-900"><tr class="border-b border-gray-200 dark:border-gray-800">
                        <th class="px-5 py-3 text-left"><input type="checkbox" class="size-4 rounded" :checked="selectable.length > 0 && selected.length === selectable.length" @change="selected = $event.target.checked ? [...selectable] : []"></th>
                        @foreach (['Tanggal', 'No. Rawat / RM', 'Pasien', 'ICD-9 / Procedure', 'Detail', 'Pelayanan', 'ID Encounter', 'Status Procedure', 'ID Procedure'] as $heading) <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ $heading }}</th> @endforeach
                    </tr></thead><tbody>
                        @forelse ($procedures as $row)
                            @php($sent = filled($row->id_procedure))
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <td class="px-5 py-4"><input type="checkbox" name="procedures[]" value="{{ implode('|', [$row->no_rawat, $row->kode, $row->status_prosedur]) }}" x-model="selected" @disabled($sent || ! auth()->user()->hasPermission('procedures.send')) class="size-4 rounded disabled:opacity-40"></td>
                                <td class="whitespace-nowrap px-5 py-4 text-sm">{{ \Illuminate\Support\Carbon::parse($row->tgl_registrasi)->format('d/m/Y') }}<span class="block text-xs text-gray-400">{{ $row->jam_reg }}</span></td>
                                <td class="whitespace-nowrap px-5 py-4 text-sm font-medium">{{ $row->no_rawat }}<span class="block text-xs font-normal text-gray-400">RM {{ $row->no_rkm_medis }}</span></td>
                                <td class="px-5 py-4 text-sm">{{ $row->nm_pasien }}<span class="block text-xs text-gray-400">NIK {{ $row->no_ktp ?: '-' }}</span></td>
                                <td class="px-5 py-4 text-sm font-medium">{{ $row->kode }}<span class="block max-w-[300px] text-xs font-normal text-gray-400">{{ $row->deskripsi_panjang }}</span></td>
                                <td class="px-5 py-4 text-sm">{{ $row->status_prosedur }}<span class="block text-xs text-gray-400">Prioritas {{ $row->prioritas }} / Jumlah {{ $row->jumlah }}</span></td>
                                <td class="px-5 py-4 text-sm">{{ $row->status_lanjut }}<span class="block text-xs text-gray-400">{{ $row->stts }}</span></td>
                                <td class="max-w-[190px] px-5 py-4 text-sm"><span class="block truncate" title="{{ $row->id_encounter }}">{{ $row->id_encounter }}</span></td>
                                <td class="px-5 py-4"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $sent ? 'bg-success-50 text-success-700' : 'bg-warning-50 text-warning-700' }}">{{ $sent ? 'Sudah Terkirim' : 'Belum Terkirim' }}</span></td>
                                <td class="max-w-[210px] px-5 py-4 text-sm"><span class="block truncate" title="{{ $row->id_procedure }}">{{ $row->id_procedure ?: '-' }}</span></td>
                            </tr>
                        @empty <tr><td colspan="10" class="px-5 py-12 text-center text-sm text-gray-500">Tidak ada Procedure yang sesuai dengan filter.</td></tr> @endforelse
                    </tbody></table>
                </div></div>
            </form>
            <div>{{ $procedures->links() }}</div>
        </x-common.component-card>
        <x-satusehat.send-popup validation-key="procedures" />
    </div>
@endsection
