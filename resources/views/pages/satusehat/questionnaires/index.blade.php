@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb :pageTitle="$definition['label'].' SATUSEHAT'" />
    <div class="space-y-6">
        <div class="flex flex-wrap gap-2">
            @foreach (['request' => 'Questionnaire Request', 'response' => 'Questionnaire Response'] as $key => $label)
                <a href="{{ route('satusehat.questionnaires.index', $key) }}" class="rounded-lg border px-4 py-2 text-sm font-medium {{ $type === $key ? 'border-brand-500 bg-brand-50 text-brand-600' : 'border-gray-200 text-gray-600 dark:border-gray-800 dark:text-gray-300' }}">{{ $label }}</a>
            @endforeach
        </div>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            @foreach ([['key' => 'all', 'label' => 'Semua Data'], ['key' => 'pending', 'label' => 'Belum Terkirim'], ['key' => 'sent', 'label' => 'Sudah Terkirim']] as $item)
                <a href="{{ route('satusehat.questionnaires.index', array_merge(['type' => $type], request()->except(['status', 'page']), ['status' => $item['key']])) }}" class="rounded-2xl border bg-white p-5 transition hover:border-brand-300 dark:bg-white/[0.03] {{ $filters['status'] === $item['key'] ? 'border-brand-500 ring-1 ring-brand-500' : 'border-gray-200 dark:border-gray-800' }}">
                    <p class="text-sm text-gray-500">{{ $item['label'] }}</p><p class="mt-2 text-3xl font-semibold text-gray-800 dark:text-white/90">{{ number_format($summary[$item['key']]) }}</p>
                </a>
            @endforeach
        </div>
        <x-common.component-card :title="'Filter '.$definition['label']">
            <form method="GET" action="{{ route('satusehat.questionnaires.index', $type) }}" class="grid grid-cols-1 gap-4 lg:grid-cols-12">
                <input type="hidden" name="status" value="{{ $filters['status'] }}">
                <label class="lg:col-span-3"><span class="form-label">Dari Tanggal</span><input type="date" name="from" value="{{ $filters['from'] }}" class="form-control"></label>
                <label class="lg:col-span-3"><span class="form-label">Sampai Tanggal</span><input type="date" name="to" value="{{ $filters['to'] }}" class="form-control"></label>
                <label class="lg:col-span-4"><span class="form-label">Cari</span><input type="search" name="search" value="{{ $filters['search'] }}" placeholder="No. resep, rawat, RM, pasien, atau apoteker" class="form-control"></label>
                <div class="flex items-end gap-2 lg:col-span-2"><button class="h-11 flex-1 rounded-lg bg-brand-500 px-4 text-sm font-medium text-white">Terapkan</button><a href="{{ route('satusehat.questionnaires.index', $type) }}" class="inline-flex h-11 items-center rounded-lg border border-gray-300 px-3 text-sm dark:border-gray-700">Reset</a></div>
            </form>
        </x-common.component-card>
        <x-common.component-card :title="'Daftar '.$definition['label']">
            <x-satusehat.send-tips resource="questionnaire" />
            @if ($connectionError) <x-ui.alert variant="warning" title="Database eksternal tidak dapat diakses" :message="$connectionError" /> @endif
            @php
                $keyFor = fn ($row) => implode('|', [$row->no_rawat, $row->event_date, $row->event_time, $row->source_status, $row->no_resep]);
                $selectable = $rows->filter(fn ($row) => blank($row->sent_id))->map($keyFor)->values();
                $checks = ['resep_identifikasi_pasien','resep_tepat_obat','resep_tepat_dosis','resep_tepat_cara_pemberian','resep_tepat_waktu_pemberian','resep_ada_tidak_duplikasi_obat','resep_interaksi_obat','resep_kontra_indikasi_obat','obat_tepat_pasien','obat_tepat_obat','obat_tepat_dosis','obat_tepat_cara_pemberian','obat_tepat_waktu_pemberian'];
            @endphp
            <form method="POST" action="{{ route('satusehat.questionnaires.send', $type) }}" x-data="{ selected: [], selectable: {{ Js::from($selectable) }} }" @submit.prevent="$dispatch('satusehat-send', { form: $el, label: '{{ $definition['label'] }}', count: selected.length })">
                @csrf
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm text-gray-500">Data telaah farmasi terbaru ditampilkan lebih dulu. Maksimal 20 data per pengiriman.</p>
                    @if (auth()->user()->hasPermission('questionnaires.send')) <button :disabled="selected.length === 0" class="rounded-lg bg-brand-500 px-5 py-2.5 text-sm font-medium text-white disabled:opacity-50">Kirim Terpilih (<span x-text="selected.length">0</span>)</button> @endif
                </div>
                <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800"><div class="max-w-full overflow-x-auto custom-scrollbar">
                    <table class="w-full min-w-[1550px]"><thead class="bg-gray-50 dark:bg-gray-900"><tr class="border-b border-gray-200 dark:border-gray-800">
                        <th class="px-5 py-3 text-left"><input type="checkbox" class="size-4 rounded" :checked="selectable.length > 0 && selected.length === selectable.length" @change="selected = $event.target.checked ? [...selectable] : []"></th>
                        @foreach (['Waktu', 'No. Resep / Rawat', 'Pasien', 'Apoteker', 'Pelayanan', 'Hasil Telaah', 'ID Encounter', 'Status', 'ID Questionnaire'] as $heading) <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ $heading }}</th> @endforeach
                    </tr></thead><tbody>
                        @forelse ($rows as $row)
                            @php
                                $sent = filled($row->sent_id);
                                $passed = collect($checks)->filter(fn ($field) => strcasecmp((string) $row->{$field}, 'Ya') === 0)->count();
                            @endphp
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <td class="px-5 py-4"><input type="checkbox" name="questionnaires[]" value="{{ $keyFor($row) }}" x-model="selected" @disabled($sent || ! auth()->user()->hasPermission('questionnaires.send')) class="size-4 rounded disabled:opacity-40"></td>
                                <td class="whitespace-nowrap px-5 py-4 text-sm">{{ \Illuminate\Support\Carbon::parse($row->event_date)->format('d/m/Y') }}<span class="block text-xs text-gray-400">{{ $row->event_time }}</span></td>
                                <td class="whitespace-nowrap px-5 py-4 text-sm font-medium">{{ $row->no_resep }}<span class="block text-xs font-normal text-gray-400">{{ $row->no_rawat }} / RM {{ $row->no_rkm_medis }}</span></td>
                                <td class="px-5 py-4 text-sm">{{ $row->nm_pasien }}<span class="block text-xs text-gray-400">NIK {{ $row->no_ktp ?: '-' }}</span></td>
                                <td class="px-5 py-4 text-sm">{{ $row->practitioner_name }}<span class="block text-xs text-gray-400">NIK {{ $row->practitioner_nik ?: '-' }}</span></td>
                                <td class="px-5 py-4 text-sm">{{ $row->source_status }}<span class="block text-xs text-gray-400">{{ $row->stts }}</span></td>
                                <td class="px-5 py-4 text-sm font-medium">{{ $passed }}/{{ count($checks) }} sesuai<span class="block text-xs font-normal text-gray-400">{{ count($checks) - $passed }} perlu perhatian</span></td>
                                <td class="max-w-[190px] px-5 py-4 text-sm"><span class="block truncate" title="{{ $row->id_encounter }}">{{ $row->id_encounter }}</span></td>
                                <td class="px-5 py-4"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $sent ? 'bg-success-50 text-success-700' : 'bg-warning-50 text-warning-700' }}">{{ $sent ? 'Sudah Terkirim' : 'Belum Terkirim' }}</span></td>
                                <td class="max-w-[210px] px-5 py-4 text-sm"><span class="block truncate" title="{{ $row->sent_id }}">{{ $row->sent_id ?: '-' }}</span></td>
                            </tr>
                        @empty <tr><td colspan="10" class="px-5 py-12 text-center text-sm text-gray-500">Tidak ada {{ $definition['label'] }} yang sesuai dengan filter.</td></tr> @endforelse
                    </tbody></table>
                </div></div>
            </form>
            <div>{{ $rows->links() }}</div>
        </x-common.component-card>
        <x-satusehat.send-popup validation-key="questionnaires" />
    </div>
@endsection
