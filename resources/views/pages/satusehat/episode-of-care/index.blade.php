@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Episode of Care SATUSEHAT" />
    <div class="space-y-6">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            @foreach ([['key'=>'all','label'=>'Semua Episode'],['key'=>'pending','label'=>'Belum Terkirim'],['key'=>'sent','label'=>'Sudah Terkirim']] as $item)
                <a href="{{ route('satusehat.episode-of-care.index', array_merge(request()->except(['status','page']), ['status'=>$item['key']])) }}" class="rounded-2xl border bg-white p-5 transition hover:border-brand-300 dark:bg-white/[0.03] {{ $filters['status']===$item['key'] ? 'border-brand-500 ring-1 ring-brand-500' : 'border-gray-200 dark:border-gray-800' }}">
                    <p class="text-sm text-gray-500">{{ $item['label'] }}</p><p class="mt-2 text-3xl font-semibold text-gray-800 dark:text-white/90">{{ number_format($summary[$item['key']]) }}</p>
                </a>
            @endforeach
        </div>
        <x-common.component-card title="Filter Episode of Care" desc="Episode Antenatal Care berdasarkan diagnosis ICD-10 kelompok O dari rawat jalan dan rawat inap.">
            <form method="GET" action="{{ route('satusehat.episode-of-care.index') }}" class="grid grid-cols-1 gap-4 lg:grid-cols-12">
                <input type="hidden" name="status" value="{{ $filters['status'] }}">
                <label class="lg:col-span-3"><span class="form-label">Dari Tanggal</span><input type="date" name="from" value="{{ $filters['from'] }}" class="form-control"></label>
                <label class="lg:col-span-3"><span class="form-label">Sampai Tanggal</span><input type="date" name="to" value="{{ $filters['to'] }}" class="form-control"></label>
                <label class="lg:col-span-4"><span class="form-label">Cari</span><input type="search" name="search" value="{{ $filters['search'] }}" placeholder="No. rawat, RM, pasien, atau diagnosis" class="form-control"></label>
                <div class="flex items-end gap-2 lg:col-span-2"><button class="h-11 flex-1 rounded-lg bg-brand-500 px-4 text-sm font-medium text-white">Terapkan</button><a href="{{ route('satusehat.episode-of-care.index') }}" class="inline-flex h-11 items-center rounded-lg border border-gray-300 px-3 text-sm dark:border-gray-700">Reset</a></div>
            </form>
        </x-common.component-card>
        <x-common.component-card title="Daftar Episode of Care">
            <x-satusehat.send-tips resource="episode-of-care" />
            @if ($connectionError) <x-ui.alert variant="warning" title="Database eksternal tidak dapat diakses" :message="$connectionError" /> @endif
            @php
                $keyFor = fn($row) => implode('|', [$row->no_rawat,$row->kd_penyakit,$row->diagnosis_status,$row->care_type]);
                $selectable = $rows->filter(fn($row)=>blank($row->sent_id))->map($keyFor)->values();
            @endphp
            <form method="POST" action="{{ route('satusehat.episode-of-care.send') }}" x-data="{selected:[],selectable:{{ Js::from($selectable) }}}" @submit.prevent="$dispatch('satusehat-send',{form:$el,label:'Episode of Care',count:selected.length})">
                @csrf
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm text-gray-500">Data terbaru ditampilkan lebih dulu. Maksimal 20 data per pengiriman.</p>
                    @if(auth()->user()->hasPermission('episode-of-care.send')) <button :disabled="selected.length===0" class="rounded-lg bg-brand-500 px-5 py-2.5 text-sm font-medium text-white disabled:opacity-50">Kirim Terpilih (<span x-text="selected.length">0</span>)</button> @endif
                </div>
                <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800"><div class="max-w-full overflow-x-auto custom-scrollbar">
                    <table class="w-full min-w-[1350px]"><thead class="bg-gray-50 dark:bg-gray-900"><tr class="border-b border-gray-200 dark:border-gray-800">
                        <th class="px-5 py-3 text-left"><input type="checkbox" class="size-4 rounded" :checked="selectable.length>0&&selected.length===selectable.length" @change="selected=$event.target.checked?[...selectable]:[]"></th>
                        @foreach(['Tanggal Pulang','No. Rawat / RM','Pasien','Diagnosis ANC','Pelayanan','ID Encounter','Status','ID Episode of Care'] as $heading)<th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ $heading }}</th>@endforeach
                    </tr></thead><tbody>
                        @forelse($rows as $row)
                            @php($sent=filled($row->sent_id))
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <td class="px-5 py-4"><input type="checkbox" name="episodes[]" value="{{ $keyFor($row) }}" x-model="selected" @disabled($sent || !auth()->user()->hasPermission('episode-of-care.send')) class="size-4 rounded disabled:opacity-40"></td>
                                <td class="whitespace-nowrap px-5 py-4 text-sm">{{ \Illuminate\Support\Carbon::parse($row->discharge_date)->format('d/m/Y') }}<span class="block text-xs text-gray-400">{{ $row->discharge_time }}</span></td>
                                <td class="whitespace-nowrap px-5 py-4 text-sm font-medium">{{ $row->no_rawat }}<span class="block text-xs font-normal text-gray-400">RM {{ $row->no_rkm_medis }}</span></td>
                                <td class="px-5 py-4 text-sm">{{ $row->nm_pasien }}<span class="block text-xs text-gray-400">NIK {{ $row->no_ktp ?: '-' }}</span></td>
                                <td class="px-5 py-4 text-sm font-medium">{{ $row->kd_penyakit }}<span class="block text-xs font-normal text-gray-400">{{ $row->nm_penyakit }}</span></td>
                                <td class="px-5 py-4 text-sm">{{ $row->care_type }}<span class="block text-xs text-gray-400">{{ $row->status_lanjut }} / {{ $row->stts }}</span></td>
                                <td class="max-w-[190px] px-5 py-4 text-sm"><span class="block truncate" title="{{ $row->id_encounter }}">{{ $row->id_encounter }}</span></td>
                                <td class="px-5 py-4"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $sent?'bg-success-50 text-success-700':'bg-warning-50 text-warning-700' }}">{{ $sent?'Sudah Terkirim':'Belum Terkirim' }}</span></td>
                                <td class="max-w-[210px] px-5 py-4 text-sm"><span class="block truncate" title="{{ $row->sent_id }}">{{ $row->sent_id ?: '-' }}</span></td>
                            </tr>
                        @empty <tr><td colspan="9" class="px-5 py-12 text-center text-sm text-gray-500">Tidak ada Episode of Care yang sesuai dengan filter.</td></tr>@endforelse
                    </tbody></table>
                </div></div>
            </form>
            <div>{{ $rows->links() }}</div>
        </x-common.component-card>
        <x-satusehat.send-popup validation-key="episodes" />
    </div>
@endsection
