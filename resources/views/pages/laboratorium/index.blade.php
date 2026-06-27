@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb :pageTitle="$definition['label']" />

    <div class="space-y-6">
        @if (session('success'))
            <div class="rounded-lg border border-success-200 bg-success-50 p-4 text-sm text-success-700 dark:border-success-500/30 dark:bg-success-500/10 dark:text-success-400">
                {{ session('success') }}
            </div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg border border-error-200 bg-error-50 p-4 text-sm text-error-700 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-400">
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Style Medication Tab navigation -->
        @if ($type === 'simrs')
            <div class="flex flex-wrap gap-2">
                @foreach ([
                    'all' => 'Semua Pemeriksaan',
                    'pending' => 'Pending',
                    'hasil' => 'Hasil',
                    'biaya' => 'Manajemen Biaya / Billing'
                ] as $key => $label)
                    <a href="{{ route('lis.periksa.index', ['status' => $key]) }}"
                        class="rounded-lg border px-4 py-2 text-sm font-medium border-gray-200 text-gray-600 hover:border-brand-300 dark:border-gray-800 dark:text-gray-300 dark:hover:border-gray-700">
                        {{ $label }}
                    </a>
                @endforeach
                <a href="{{ route('lis.periksa.simrs') }}"
                    class="rounded-lg border px-4 py-2 text-sm font-medium border-gray-200 text-gray-600 hover:border-brand-300 dark:border-gray-800 dark:text-gray-300 dark:hover:border-brand-700">
                    Periksa SIMRS
                </a>
                <a href="{{ route('lis.result.index') }}"
                    class="rounded-lg border px-4 py-2 text-sm font-medium border-gray-200 text-gray-600 hover:border-brand-300 dark:border-gray-800 dark:text-gray-300 dark:hover:border-gray-700 flex items-center gap-1.5 ml-auto bg-gray-50 dark:bg-gray-900">
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" /></svg>
                    Hasil Raw Analyzer
                </a>
                <a href="{{ route('lis.referensi.index') }}"
                    class="rounded-lg border px-4 py-2 text-sm font-medium border-gray-200 text-gray-600 hover:border-brand-300 dark:border-gray-800 dark:text-gray-300 dark:hover:border-gray-700 flex items-center gap-1.5 bg-gray-50 dark:bg-gray-900">
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" /></svg>
                    Referensi LIS
                </a>
            </div>
        @else
            <div class="flex flex-wrap gap-2">
                @foreach (['permintaan' => 'Permintaan Lab', 'periksa' => 'Pemeriksaan Lab'] as $key => $label)
                    <a href="{{ route('laboratorium.index', array_merge(['type' => $key], request()->except(['type', 'page']))) }}"
                        class="rounded-lg border px-4 py-2 text-sm font-medium {{ $type === $key ? 'border-brand-500 bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400' : 'border-gray-200 text-gray-600 hover:border-brand-300 dark:border-gray-800 dark:text-gray-300 dark:hover:border-gray-700' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>
        @endif

        @if ($type === 'permintaan')
            <!-- ================= PERMINTAAN LAB ================= -->
            <!-- Filter Card -->
            <x-common.component-card :title="'Filter '.$definition['label']"
                desc="Cari data permintaan lab berdasarkan rentang tanggal, status rawat, dan kata kunci.">
                <form method="GET" action="{{ route('laboratorium.index', 'permintaan') }}" class="grid grid-cols-1 gap-4 lg:grid-cols-12">
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

                    <label class="lg:col-span-2">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Status Rawat</span>
                        <select name="status"
                            class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white/90">
                            <option value="all" {{ $filters['status'] === 'all' ? 'selected' : '' }}>Semua</option>
                            <option value="ralan" {{ $filters['status'] === 'ralan' ? 'selected' : '' }}>Rawat Jalan</option>
                            <option value="ranap" {{ $filters['status'] === 'ranap' ? 'selected' : '' }}>Rawat Inap</option>
                        </select>
                    </label>

                    <label class="lg:col-span-2">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Cari</span>
                        <input type="search" name="search" value="{{ $filters['search'] }}"
                            placeholder="No. Order, RM, nama..."
                            class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm text-gray-800 outline-none placeholder:text-gray-400 focus:border-brand-500 dark:border-gray-700 dark:text-white/90">
                    </label>

                    <div class="flex items-end gap-2 lg:col-span-2">
                        <button type="submit"
                            class="h-11 flex-1 rounded-lg bg-brand-500 px-4 text-sm font-medium text-white hover:bg-brand-600">
                            Terapkan
                        </button>
                        <a href="{{ route('laboratorium.index', 'permintaan') }}"
                            class="inline-flex h-11 items-center rounded-lg border border-gray-300 px-3 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.05]">
                            Reset
                        </a>
                    </div>
                </form>
                @error('to')
                    <p class="mt-2 text-sm text-error-500">{{ $message }}</p>
                @enderror
            </x-common.component-card>

            <!-- List Card (custom sticky header) -->
            <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <!-- Sticky Card Header -->
                <div class="sticky top-0 z-20 rounded-t-2xl bg-white dark:bg-gray-950 border-b border-gray-100 dark:border-gray-800 px-6 py-4 shadow-sm">
                    <h3 class="text-base font-medium text-gray-800 dark:text-white/90">Daftar {{ $definition['label'] }}</h3>
                </div>
                <div class="p-4 sm:p-6">
                @if ($connectionError)
                    <div class="rounded-lg border border-warning-200 bg-warning-50 p-4 text-sm text-warning-700 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-400">
                        {{ $connectionError }}
                    </div>
                @else
                    <div x-data="{
                        selectedOrder: '',
                        selectedNamaPasien: '',
                        hasSampel: false,
                        hasHasil: false,
                        tglSampel: '',
                        jamSampel: '',
                        tglHasil: '',
                        jamHasil: '',
                        sampelModal: false,
                        hasilModal: false,
                        submitSampelNow() {
                            if (!this.selectedOrder) return;
                            const now = new Date();
                            const tgl = now.getFullYear() + '-' + String(now.getMonth()+1).padStart(2,'0') + '-' + String(now.getDate()).padStart(2,'0');
                            const jam = String(now.getHours()).padStart(2,'0') + ':' + String(now.getMinutes()).padStart(2,'0');
                            const form = document.createElement('form');
                            form.method = 'POST';
                            form.action = '/laboratorium/permintaan/' + this.selectedOrder + '/sampel';
                            [['_token', '{{ csrf_token() }}'], ['tgl_sampel', tgl], ['jam_sampel', jam]].forEach(([n,v]) => {
                                const i = document.createElement('input');
                                i.type = 'hidden'; i.name = n; i.value = v;
                                form.appendChild(i);
                            });
                            document.body.appendChild(form);
                            form.submit();
                        }
                    }">
                        <!-- Top-Level Actions (Sticky Encounter Style) -->
                        <div class="sticky top-[61px] z-10 mb-4 flex flex-wrap items-center justify-between gap-3 bg-gray-50 dark:bg-gray-900/80 backdrop-blur p-4 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm">
                            <div class="flex flex-col sm:flex-row sm:items-center gap-2">
                                <span class="text-sm text-gray-500 dark:text-gray-400">Pasien Terpilih:</span>
                                <span x-show="selectedOrder" class="text-sm font-semibold text-brand-600 dark:text-brand-400" x-cloak>
                                    <span x-text="selectedNamaPasien"></span> (<span x-text="selectedOrder"></span>)
                                </span>
                                <span x-show="!selectedOrder" class="text-sm text-gray-400 italic">Belum ada pasien yang dipilih</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <!-- Sampel Button -->
                                <button type="button" @click="submitSampelNow()" :disabled="!selectedOrder"
                                    class="inline-flex items-center gap-2 rounded-lg bg-brand-500 px-5 py-2.5 text-sm font-medium text-white hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-50">
                                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                                    </svg>
                                    Set Waktu Sampel
                                </button>
                                <!-- Buka Pemeriksaan LIS Button -->
                                <button type="button" @click="if (selectedOrder) window.location.href = '{{ route('lis.periksa.tolis', ['noorder' => '__ORDER__']) }}'.replace('__ORDER__', selectedOrder)" :disabled="!selectedOrder"
                                    class="inline-flex items-center gap-2 rounded-lg bg-emerald-500 px-5 py-2.5 text-sm font-medium text-white hover:bg-emerald-600 disabled:cursor-not-allowed disabled:opacity-50">
                                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                                    </svg>
                                    Buka Pemeriksaan LIS
                                </button>
                            </div>
                        </div>

                        <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800">
                            <div class="max-w-full max-h-[600px] overflow-auto custom-scrollbar">
                                <table class="w-full min-w-[1200px] border-collapse">
                                    <thead class="bg-gray-50 dark:bg-gray-900 sticky top-0 z-10 shadow-sm">
                                        <tr class="border-b border-gray-200 dark:border-gray-800">
                                            <th class="w-10 px-5 py-3 text-left bg-gray-50 dark:bg-gray-900 sticky top-0 z-10"></th>
                                            <th class="w-10 px-5 py-3 text-left bg-gray-50 dark:bg-gray-900 sticky top-0 z-10"></th>
                                            <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-900 sticky top-0 z-10">No. Order / Rawat</th>
                                            <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-900 sticky top-0 z-10">Tgl Permintaan</th>
                                            <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-900 sticky top-0 z-10">Sampel / Hasil</th>
                                            <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-900 sticky top-0 z-10">Pasien</th>
                                            <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-900 sticky top-0 z-10">J.K.</th>
                                            <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-900 sticky top-0 z-10">Status / Dokter</th>
                                            <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-900 sticky top-0 z-10">Lokasi / Unit</th>
                                            <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-900 sticky top-0 z-10">Cara Bayar</th>
                                            <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-900 sticky top-0 z-10">Diagnosa</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                        @forelse ($rows as $req)
                                            @php
                                                $hasSampel = !empty($req->tgl_sampel) && $req->tgl_sampel !== '0000-00-00';
                                                $hasHasil = !empty($req->tgl_hasil) && $req->tgl_hasil !== '0000-00-00';
                                            @endphp
                                            <tr x-data="{ 
                                                expanded: false, 
                                                details: [], 
                                                loading: false, 
                                                loaded: false,
                                                toggle() {
                                                    this.expanded = !this.expanded;
                                                    if (this.expanded && !this.loaded) {
                                                        this.loading = true;
                                                        fetch('{{ route('laboratorium.index', 'permintaan') }}/' + '{{ $req->noorder }}' + '/details')
                                                            .then(res => res.json())
                                                            .then(data => {
                                                                 this.details = data;
                                                                 this.loading = false;
                                                                 this.loaded = true;
                                                            })
                                                            .catch(err => {
                                                                 console.error(err);
                                                                 this.loading = false;
                                                            });
                                                    }
                                                }
                                            }" 
                                            @click="
                                                selectedOrder = '{{ $req->noorder }}';
                                                selectedNamaPasien = '{{ addslashes($req->nm_pasien) }}';
                                                hasSampel = {{ $hasSampel ? 'true' : 'false' }};
                                                hasHasil = {{ $hasHasil ? 'true' : 'false' }};
                                                tglSampel = '{{ $hasSampel ? $req->tgl_sampel : now()->toDateString() }}';
                                                jamSampel = '{{ $hasSampel ? substr($req->jam_sampel, 0, 5) : now()->format('H:i') }}';
                                                tglHasil = '{{ $hasHasil ? $req->tgl_hasil : now()->toDateString() }}';
                                                jamHasil = '{{ $hasHasil ? substr($req->jam_hasil, 0, 5) : now()->format('H:i') }}';
                                            "
                                            class="cursor-pointer transition-colors duration-150"
                                            :class="selectedOrder === '{{ $req->noorder }}' ? 'bg-brand-50/70 dark:bg-brand-500/10' : 'hover:bg-gray-50/30 dark:hover:bg-gray-800/10'">
                                                <!-- Toggle Button -->
                                                <td class="px-5 py-4" @click.stop>
                                                    <button @click="toggle()" type="button" class="text-gray-400 hover:text-brand-500 focus:outline-none">
                                                        <svg class="h-5 w-5 transform transition-transform duration-200" :class="{ 'rotate-90 text-brand-500': expanded }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                                        </svg>
                                                    </button>
                                                </td>
                                                <!-- Radio Selector -->
                                                <td class="px-5 py-4">
                                                    <input type="radio" name="selected_order" value="{{ $req->noorder }}"
                                                        x-model="selectedOrder"
                                                        class="size-4 text-brand-600 focus:ring-brand-500 border-gray-300 dark:border-gray-700 dark:bg-gray-800 cursor-pointer">
                                                </td>
                                                <!-- No. Order / Rawat -->
                                                <td class="whitespace-nowrap px-5 py-4 text-sm font-medium">
                                                    <span class="text-gray-950 dark:text-white block font-semibold">{{ $req->noorder }}</span>
                                                    <span class="text-xs text-gray-400 font-normal">Rawat: {{ $req->no_rawat }}</span>
                                                </td>
                                                <!-- Tanggal & Jam -->
                                                <td class="whitespace-nowrap px-5 py-4 text-sm text-gray-600 dark:text-gray-300">
                                                    {{ \Illuminate\Support\Carbon::parse($req->tgl_permintaan)->format('d/m/Y') }}
                                                    <span class="block text-xs text-gray-400">{{ $req->jam_permintaan }}</span>
                                                </td>
                                                <!-- Sampel / Hasil (compact) -->
                                                <td class="whitespace-nowrap px-5 py-4 text-sm">
                                                    <div class="flex flex-col gap-1.5">
                                                        <div class="flex items-start gap-1.5">
                                                            <span class="mt-0.5 shrink-0 inline-flex items-center justify-center rounded px-1 py-0.5 text-[10px] font-semibold leading-none bg-blue-50 text-blue-600 dark:bg-blue-500/15 dark:text-blue-400">S</span>
                                                            @if($hasSampel)
                                                                <span class="text-gray-700 dark:text-gray-300">
                                                                    {{ \Illuminate\Support\Carbon::parse($req->tgl_sampel)->format('d/m/Y') }}
                                                                    <span class="text-gray-400 text-xs"> {{ substr($req->jam_sampel, 0, 5) }}</span>
                                                                </span>
                                                            @else
                                                                <span class="text-xs text-gray-400 italic">Belum Diambil</span>
                                                            @endif
                                                        </div>
                                                        <div class="flex items-start gap-1.5">
                                                            <span class="mt-0.5 shrink-0 inline-flex items-center justify-center rounded px-1 py-0.5 text-[10px] font-semibold leading-none bg-emerald-50 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400">H</span>
                                                            @if($hasHasil)
                                                                <span class="text-gray-700 dark:text-gray-300">
                                                                    {{ \Illuminate\Support\Carbon::parse($req->tgl_hasil)->format('d/m/Y') }}
                                                                    <span class="text-gray-400 text-xs"> {{ substr($req->jam_hasil, 0, 5) }}</span>
                                                                </span>
                                                            @else
                                                                <span class="text-xs text-gray-400 italic">Belum Ada</span>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </td>
                                                <!-- Pasien -->
                                                <td class="px-5 py-4 text-sm text-gray-700 dark:text-gray-300">
                                                    <span class="font-semibold text-gray-950 dark:text-white block">{{ $req->nm_pasien }}</span>
                                                    <span class="block text-xs text-gray-400">RM: {{ $req->no_rkm_medis }}</span>
                                                </td>
                                                <!-- J.K. -->
                                                <td class="whitespace-nowrap px-5 py-4 text-sm">
                                                    @if($req->jk === 'L')
                                                        <span class="inline-flex rounded-full bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700 dark:bg-blue-500/15 dark:text-blue-400">
                                                            L
                                                        </span>
                                                    @elseif($req->jk === 'P')
                                                        <span class="inline-flex rounded-full bg-rose-50 px-2 py-0.5 text-xs font-medium text-rose-700 dark:bg-rose-500/15 dark:text-rose-400">
                                                            P
                                                        </span>
                                                    @else
                                                        <span class="text-gray-400">-</span>
                                                    @endif
                                                </td>
                                                <!-- Status / Dokter (compact) -->
                                                <td class="px-5 py-4 text-sm">
                                                    <div class="flex flex-col gap-1">
                                                        @if(strtolower($req->ralan_or_ranap) === 'ralan')
                                                            <span class="inline-flex w-fit rounded-full bg-green-50 px-2.5 py-0.5 text-xs font-medium text-green-700 dark:bg-green-500/15 dark:text-green-400">Rawat Jalan</span>
                                                        @else
                                                            <span class="inline-flex w-fit rounded-full bg-orange-50 px-2.5 py-0.5 text-xs font-medium text-orange-700 dark:bg-orange-500/15 dark:text-orange-400">Rawat Inap</span>
                                                        @endif
                                                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ $req->dokter_rujuk_name }}</span>
                                                    </div>
                                                </td>
                                                <!-- Lokasi -->
                                                <td class="px-5 py-4 text-sm text-gray-700 dark:text-gray-300">
                                                    {{ $req->lokasi }}
                                                </td>
                                                <!-- Cara Bayar -->
                                                <td class="px-5 py-4 text-sm text-gray-700 dark:text-gray-300">
                                                    {{ $req->png_jawab }}
                                                </td>
                                                <!-- Diagnosa -->
                                                <td class="px-5 py-4 text-sm text-gray-500 dark:text-gray-400 truncate max-w-[150px]" title="{{ $req->diagnosa_klinis }}">
                                                    {{ $req->diagnosa_klinis ?: '-' }}
                                                </td>
                                            </tr>

                                            <!-- Collapsible Detail Row -->
                                            <tr x-show="expanded" x-cloak>
                                                <td colspan="11" class="px-5 py-4 bg-gray-50/50 dark:bg-gray-900/50 border-t border-gray-100 dark:border-gray-800">
                                                    <!-- Spinner loading -->
                                                    <div x-show="loading" class="flex items-center justify-center py-6">
                                                        <div class="h-6 w-6 animate-spin rounded-full border-2 border-brand-500 border-t-transparent"></div>
                                                        <span class="ml-3 text-sm text-gray-500">Memuat detail permintaan...</span>
                                                    </div>

                                                    <div x-show="!loading && details.length === 0" class="text-sm text-gray-500 text-center py-4">
                                                        Tidak ada detail pemeriksaan yang ditemukan.
                                                    </div>

                                                    <!-- Render Categories and Items -->
                                                    <div x-show="!loading && details.length > 0" class="space-y-4">
                                                        <template x-for="category in details" :key="category.kd_jenis_prw">
                                                            <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950 overflow-hidden shadow-sm">
                                                                <div class="bg-gray-50 dark:bg-gray-900/80 px-4 py-2.5 border-b border-gray-200 dark:border-gray-800 font-semibold text-sm text-gray-800 dark:text-gray-200" x-text="category.nm_perawatan"></div>
                                                                <table class="w-full text-xs">
                                                                    <thead class="bg-gray-50/40 dark:bg-gray-900/40 text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-800">
                                                                        <tr>
                                                                            <th class="px-4 py-2 text-left font-medium">Nama Pemeriksaan</th>
                                                                            <th class="px-4 py-2 text-center font-medium">Satuan</th>
                                                                            <th class="px-4 py-2 text-center font-medium">Rujukan L.D (Laki-laki Dewasa)</th>
                                                                            <th class="px-4 py-2 text-center font-medium">Rujukan L.A (Laki-laki Anak)</th>
                                                                            <th class="px-4 py-2 text-center font-medium">Rujukan P.D (Perempuan Dewasa)</th>
                                                                            <th class="px-4 py-2 text-center font-medium">Rujukan P.A (Perempuan Anak)</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800 text-gray-700 dark:text-gray-300">
                                                                        <template x-for="item in category.details">
                                                                            <tr class="hover:bg-gray-50/20 dark:hover:bg-gray-800/10">
                                                                                <td class="px-4 py-2.5 font-medium text-gray-950 dark:text-white" x-text="item.Pemeriksaan"></td>
                                                                                <td class="px-4 py-2.5 text-center" x-text="item.satuan || '-'"></td>
                                                                                <td class="px-4 py-2.5 text-center font-mono" x-text="item.nilai_rujukan_ld || '-'"></td>
                                                                                <td class="px-4 py-2.5 text-center font-mono" x-text="item.nilai_rujukan_la || '-'"></td>
                                                                                <td class="px-4 py-2.5 text-center font-mono" x-text="item.nilai_rujukan_pd || '-'"></td>
                                                                                <td class="px-4 py-2.5 text-center font-mono" x-text="item.nilai_rujukan_pa || '-'"></td>
                                                                            </tr>
                                                                        </template>
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        </template>
                                                    </div>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="11" class="px-5 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                                                    Tidak ada data permintaan laboratorium yang ditemukan.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Sampel Modal -->
                        <div x-show="sampelModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" x-cloak>
                            <div @click.away="sampelModal = false" class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl dark:bg-gray-900 border border-gray-100 dark:border-gray-800 text-left">
                                <h3 class="text-lg font-bold text-gray-950 dark:text-white mb-2">Set Waktu Ambil Sampel</h3>
                                <p class="text-sm text-gray-500 mb-4">No. Order: <span class="font-semibold text-gray-800 dark:text-gray-200" x-text="selectedOrder"></span></p>
                                <form method="POST" :action="'/laboratorium/permintaan/' + selectedOrder + '/sampel'" class="space-y-4">
                                    @csrf
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tanggal Sampel</label>
                                        <input type="date" name="tgl_sampel" x-model="tglSampel" required class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white/90">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Jam Sampel</label>
                                        <input type="time" name="jam_sampel" x-model="jamSampel" required class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white/90">
                                    </div>
                                    <div class="flex justify-end gap-2 mt-6">
                                        <button type="button" @click="sampelModal = false" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">Batal</button>
                                        <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-brand-500 hover:bg-brand-600 rounded-lg">Simpan</button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Hasil Modal -->
                        <div x-show="hasilModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" x-cloak>
                            <div @click.away="hasilModal = false" class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl dark:bg-gray-900 border border-gray-100 dark:border-gray-800 text-left">
                                <h3 class="text-lg font-bold text-gray-950 dark:text-white mb-2">Set Waktu Penyerahan Hasil</h3>
                                <p class="text-sm text-gray-500 mb-4">No. Order: <span class="font-semibold text-gray-800 dark:text-gray-200" x-text="selectedOrder"></span></p>
                                <form method="POST" :action="'/laboratorium/permintaan/' + selectedOrder + '/hasil'" class="space-y-4">
                                    @csrf
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tanggal Hasil</label>
                                        <input type="date" name="tgl_hasil" x-model="tglHasil" required class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white/90">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Jam Hasil</label>
                                        <input type="time" name="jam_hasil" x-model="jamHasil" required class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white/90">
                                    </div>
                                    <div class="flex justify-end gap-2 mt-6">
                                        <button type="button" @click="hasilModal = false" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">Batal</button>
                                        <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-brand-500 hover:bg-brand-600 rounded-lg">Simpan</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4">
                        {{ $rows->links() }}
                    </div>
                @endif
                </div>
            </div>

        @elseif ($type === 'periksa')
            <!-- ================= PEMERIKSAAN LAB ================= -->
            <!-- Filter Card -->
            <x-common.component-card :title="'Filter '.$definition['label']"
                desc="Cari data pemeriksaan lab berdasarkan rentang tanggal periksa dan kata kunci.">
                <form method="GET" action="{{ route('laboratorium.index', 'periksa') }}" class="grid grid-cols-1 gap-4 lg:grid-cols-12">
                    <label class="lg:col-span-4">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Dari Tanggal</span>
                        <input type="date" name="from" value="{{ $filters['from'] }}"
                            class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white/90">
                    </label>

                    <label class="lg:col-span-4">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Sampai Tanggal</span>
                        <input type="date" name="to" value="{{ $filters['to'] }}"
                            class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white/90">
                    </label>

                    <label class="lg:col-span-2">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Cari</span>
                        <input type="search" name="search" value="{{ $filters['search'] }}"
                            placeholder="No. Rawat, RM, nama..."
                            class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm text-gray-800 outline-none placeholder:text-gray-400 focus:border-brand-500 dark:border-gray-700 dark:text-white/90">
                    </label>

                    <div class="flex items-end gap-2 lg:col-span-2">
                        <button type="submit"
                            class="h-11 flex-1 rounded-lg bg-brand-500 px-4 text-sm font-medium text-white hover:bg-brand-600">
                            Terapkan
                        </button>
                        <a href="{{ route('laboratorium.index', 'periksa') }}"
                            class="inline-flex h-11 items-center rounded-lg border border-gray-300 px-3 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.05]">
                            Reset
                        </a>
                    </div>
                </form>
                @error('to')
                    <p class="mt-2 text-sm text-error-500">{{ $message }}</p>
                @enderror
            </x-common.component-card>

            <!-- List Card -->
            <x-common.component-card :title="'Daftar '.$definition['label']">
                @if ($connectionError)
                    <div class="rounded-lg border border-warning-200 bg-warning-50 p-4 text-sm text-warning-700 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-400">
                        {{ $connectionError }}
                    </div>
                @else
                    <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800">
                        <div class="max-w-full max-h-[600px] overflow-auto custom-scrollbar">
                            <table class="w-full min-w-[1200px] border-collapse">
                                <thead class="bg-gray-50 dark:bg-gray-900 sticky top-0 z-10 shadow-sm">
                                    <tr class="border-b border-gray-200 dark:border-gray-800">
                                        <th class="w-10 px-5 py-3 text-left bg-gray-50 dark:bg-gray-900 sticky top-0 z-10"></th>
                                        <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-900 sticky top-0 z-10">No. Rawat</th>
                                        <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-900 sticky top-0 z-10">Tanggal & Jam Periksa</th>
                                        <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-900 sticky top-0 z-10">Pasien</th>
                                        <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-900 sticky top-0 z-10">J.K.</th>
                                        <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-900 sticky top-0 z-10">Status Rawat</th>
                                        <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-900 sticky top-0 z-10">Petugas Lab</th>
                                        <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-900 sticky top-0 z-10">Dokter Penanggung Jawab</th>
                                        <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-900 sticky top-0 z-10">Lokasi / Kamar</th>
                                        <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-900 sticky top-0 z-10">Cara Bayar</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                    @forelse ($rows as $exam)
                                        <tr x-data="{ 
                                            expanded: false, 
                                            details: [], 
                                            notes: { saran: '', kesan: '' },
                                            loading: false, 
                                            loaded: false,
                                            toggle() {
                                                this.expanded = !this.expanded;
                                                if (this.expanded && !this.loaded) {
                                                    this.loading = true;
                                                    const params = new URLSearchParams({
                                                        no_rawat: '{{ $exam->no_rawat }}',
                                                        tgl_periksa: '{{ $exam->tgl_periksa }}',
                                                        jam: '{{ $exam->jam }}'
                                                    });
                                                    fetch('{{ route('laboratorium.index', 'periksa') }}/details?' + params.toString())
                                                        .then(res => res.json())
                                                        .then(data => {
                                                            this.details = data.categories;
                                                            this.notes = data.notes || { saran: '', kesan: '' };
                                                            this.loading = false;
                                                            this.loaded = true;
                                                        })
                                                        .catch(err => {
                                                            console.error(err);
                                                            this.loading = false;
                                                        });
                                                }
                                            }
                                        }" class="hover:bg-gray-50/30 dark:hover:bg-gray-800/10">
                                            <!-- Toggle Button -->
                                            <td class="px-5 py-4">
                                                <button @click="toggle()" type="button" class="text-gray-400 hover:text-brand-500 focus:outline-none">
                                                    <svg class="h-5 w-5 transform transition-transform duration-200" :class="{ 'rotate-90 text-brand-500': expanded }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                                    </svg>
                                                </button>
                                            </td>
                                            <!-- No. Rawat -->
                                            <td class="whitespace-nowrap px-5 py-4 text-sm font-semibold text-brand-600 dark:text-brand-400">
                                                {{ $exam->no_rawat }}
                                            </td>
                                            <!-- Tanggal & Jam -->
                                            <td class="whitespace-nowrap px-5 py-4 text-sm text-gray-600 dark:text-gray-300">
                                                {{ \Illuminate\Support\Carbon::parse($exam->tgl_periksa)->format('d/m/Y') }}
                                                <span class="block text-xs text-gray-400">{{ $exam->jam }}</span>
                                            </td>
                                            <!-- Pasien -->
                                            <td class="px-5 py-4 text-sm text-gray-700 dark:text-gray-300">
                                                <span class="font-semibold text-gray-950 dark:text-white block">{{ $exam->nm_pasien }}</span>
                                                <span class="block text-xs text-gray-400">RM: {{ $exam->no_rkm_medis }}</span>
                                            </td>
                                            <!-- J.K. -->
                                            <td class="whitespace-nowrap px-5 py-4 text-sm">
                                                @if($exam->jk === 'L')
                                                    <span class="inline-flex rounded-full bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700 dark:bg-blue-500/15 dark:text-blue-400">
                                                        L
                                                    </span>
                                                @elseif($exam->jk === 'P')
                                                    <span class="inline-flex rounded-full bg-rose-50 px-2 py-0.5 text-xs font-medium text-rose-700 dark:bg-rose-500/15 dark:text-rose-400">
                                                        P
                                                    </span>
                                                @else
                                                    <span class="text-gray-400">-</span>
                                                @endif
                                            </td>
                                            <!-- Status Rawat -->
                                            <td class="whitespace-nowrap px-5 py-4 text-sm">
                                                @if(strtolower($exam->ralan_or_ranap) === 'ralan')
                                                    <span class="inline-flex rounded-full bg-green-50 px-2.5 py-1 text-xs font-medium text-green-700 dark:bg-green-500/15 dark:text-green-400">
                                                        Rawat Jalan
                                                    </span>
                                                @else
                                                    <span class="inline-flex rounded-full bg-orange-50 px-2.5 py-1 text-xs font-medium text-orange-700 dark:bg-orange-500/15 dark:text-orange-400">
                                                        Rawat Inap
                                                    </span>
                                                @endif
                                            </td>
                                            <!-- Petugas Lab -->
                                            <td class="px-5 py-4 text-sm text-gray-700 dark:text-gray-300">
                                                {{ $exam->nama_petugas }}
                                            </td>
                                            <!-- Dokter PJ -->
                                            <td class="px-5 py-4 text-sm text-gray-700 dark:text-gray-300">
                                                {{ $exam->nama_dokter }}
                                            </td>
                                            <!-- Lokasi -->
                                            <td class="px-5 py-4 text-sm text-gray-700 dark:text-gray-300">
                                                {{ $exam->lokasi }}
                                            </td>
                                            <!-- Cara Bayar -->
                                            <td class="px-5 py-4 text-sm text-gray-700 dark:text-gray-300 font-semibold">
                                                {{ $exam->png_jawab }}
                                            </td>
                                        </tr>

                                        <!-- Collapsible Detail Row -->
                                        <tr x-show="expanded" x-cloak>
                                            <td colspan="10" class="px-5 py-4 bg-gray-50/50 dark:bg-gray-900/50 border-t border-gray-100 dark:border-gray-800">
                                                <!-- Spinner loading -->
                                                <div x-show="loading" class="flex items-center justify-center py-6">
                                                    <div class="h-6 w-6 animate-spin rounded-full border-2 border-brand-500 border-t-transparent"></div>
                                                    <span class="ml-3 text-sm text-gray-500">Memuat hasil pemeriksaan...</span>
                                                </div>

                                                <div x-show="!loading && details.length === 0" class="text-sm text-gray-500 text-center py-4">
                                                    Tidak ada hasil pemeriksaan yang ditemukan.
                                                </div>

                                                <!-- Render Results -->
                                                <div x-show="!loading && details.length > 0" class="space-y-4">
                                                    <template x-for="category in details" :key="category.kd_jenis_prw">
                                                        <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950 overflow-hidden shadow-sm">
                                                            <div class="bg-gray-50 dark:bg-gray-900/80 px-4 py-2.5 border-b border-gray-200 dark:border-gray-800 flex justify-between items-center">
                                                                <span class="font-semibold text-sm text-gray-800 dark:text-gray-200" x-text="category.nm_perawatan"></span>
                                                                <span class="text-xs font-medium text-brand-600 dark:text-brand-400 bg-brand-50 dark:bg-brand-500/10 px-2 py-0.5 rounded" x-text="'Biaya: Rp ' + Number(category.biaya).toLocaleString('id-ID')"></span>
                                                            </div>
                                                            <table class="w-full text-xs">
                                                                <thead class="bg-gray-50/40 dark:bg-gray-900/40 text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-800">
                                                                    <tr>
                                                                        <th class="px-4 py-2 text-left font-medium">Pemeriksaan</th>
                                                                        <th class="px-4 py-2 text-center font-medium w-48">Nilai / Hasil</th>
                                                                        <th class="px-4 py-2 text-center font-medium w-32">Satuan</th>
                                                                        <th class="px-4 py-2 text-center font-medium w-48">Nilai Rujukan</th>
                                                                        <th class="px-4 py-2 text-left font-medium">Keterangan</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody class="divide-y divide-gray-100 dark:divide-gray-800 text-gray-700 dark:text-gray-300">
                                                                    <template x-for="item in category.details">
                                                                        <tr class="hover:bg-gray-50/20 dark:hover:bg-gray-800/10">
                                                                            <td class="px-4 py-2.5 font-medium text-gray-900 dark:text-white" x-text="item.Pemeriksaan"></td>
                                                                            <td class="px-4 py-2.5 text-center font-semibold text-sm text-gray-950 dark:text-white" x-text="item.nilai"></td>
                                                                            <td class="px-4 py-2.5 text-center font-mono" x-text="item.satuan || '-'"></td>
                                                                            <td class="px-4 py-2.5 text-center font-mono" x-text="item.nilai_rujukan || '-'"></td>
                                                                            <td class="px-4 py-2.5 text-left text-gray-500" x-text="item.keterangan || '-'"></td>
                                                                        </tr>
                                                                    </template>
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    </template>

                                                    <!-- Notes (Kesan & Saran) -->
                                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-2">
                                                        <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950 p-4 shadow-sm" x-show="notes.kesan">
                                                            <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Kesan</h4>
                                                            <p class="text-sm text-gray-800 dark:text-gray-200" x-text="notes.kesan"></p>
                                                        </div>
                                                        <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950 p-4 shadow-sm" x-show="notes.saran">
                                                            <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Saran</h4>
                                                            <p class="text-sm text-gray-800 dark:text-gray-200" x-text="notes.saran"></p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="10" class="px-5 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                                                Tidak ada data pemeriksaan laboratorium yang ditemukan.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="mt-4">
                        {{ $rows->links() }}
                    </div>
                @endif
            </x-common.component-card>

        @else
            <!-- ================= PERIKSA SIMRS ================= -->
            <!-- Filter Card -->
            <x-common.component-card :title="'Filter '.$definition['label']"
                desc="Cari data registrasi berdasarkan rentang tanggal permintaan dan kata kunci.">
                <form method="GET" action="{{ route('laboratorium.index', 'simrs') }}" class="grid grid-cols-1 gap-4 lg:grid-cols-12">
                    <label class="lg:col-span-4">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Dari Tanggal</span>
                        <input type="date" name="from" value="{{ $filters['from'] }}"
                            class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white/90">
                    </label>

                    <label class="lg:col-span-4">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Sampai Tanggal</span>
                        <input type="date" name="to" value="{{ $filters['to'] }}"
                            class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white/90">
                    </label>

                    <label class="lg:col-span-2">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Cari</span>
                        <input type="search" name="search" value="{{ $filters['search'] }}"
                            placeholder="No. order, RM, nama..."
                            class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm text-gray-800 outline-none placeholder:text-gray-400 focus:border-brand-500 dark:border-gray-700 dark:text-white/90">
                    </label>

                    <div class="flex items-end gap-2 lg:col-span-2">
                        <button type="submit"
                            class="h-11 flex-1 rounded-lg bg-brand-500 px-4 text-sm font-medium text-white hover:bg-brand-600">
                            Terapkan
                        </button>
                        <a href="{{ route('laboratorium.index', 'simrs') }}"
                            class="inline-flex h-11 items-center rounded-lg border border-gray-300 px-3 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.05]">
                            Reset
                        </a>
                    </div>
                </form>
            </x-common.component-card>

            <!-- List Card -->
            <x-common.component-card :title="'Daftar '.$definition['label']">
                @if ($connectionError)
                    <div class="rounded-lg border border-warning-200 bg-warning-50 p-4 text-sm text-warning-700 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-400">
                        {{ $connectionError }}
                    </div>
                @else
                    <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800">
                        <div class="max-w-full overflow-x-auto custom-scrollbar">
                            <table class="w-full min-w-[1000px]">
                                <thead class="bg-gray-50 dark:bg-gray-900">
                                    <tr class="border-b border-gray-200 dark:border-gray-800">
                                        <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">No. Lab</th>
                                        <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Tgl Periksa</th>
                                        <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">No. RM</th>
                                        <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Nama Pasien</th>
                                        <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">J.K.</th>
                                        <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Dokter Pengirim</th>
                                        <th class="px-5 py-3 text-center text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                    @forelse ($rows as $row)
                                        <tr>
                                            <td class="whitespace-nowrap px-5 py-4 text-sm font-semibold text-brand-600 dark:text-brand-400">
                                                {{ $row->noorder }}
                                            </td>
                                            <td class="whitespace-nowrap px-5 py-4 text-sm text-gray-600 dark:text-gray-300">
                                                {{ \Illuminate\Support\Carbon::parse($row->tgl_permintaan)->format('d/m/Y') }}
                                                <span class="block text-xs text-gray-400">{{ substr($row->jam_permintaan, 0, 5) }}</span>
                                            </td>
                                            <td class="whitespace-nowrap px-5 py-4 text-sm text-gray-700 dark:text-gray-300">
                                                {{ $row->no_rkm_medis }}
                                            </td>
                                            <td class="px-5 py-4 text-sm font-medium text-gray-950 dark:text-white">
                                                {{ $row->nm_pasien }}
                                            </td>
                                            <td class="whitespace-nowrap px-5 py-4 text-sm">
                                                @if($row->jk === 'L')
                                                    <span class="inline-flex rounded-full bg-blue-50 px-2.5 py-1 text-xs font-medium text-blue-700 dark:bg-blue-500/15 dark:text-blue-400">
                                                        L
                                                    </span>
                                                @elseif($row->jk === 'P')
                                                    <span class="inline-flex rounded-full bg-rose-50 px-2.5 py-1 text-xs font-medium text-rose-700 dark:bg-rose-500/15 dark:text-rose-400">
                                                        P
                                                    </span>
                                                @else
                                                    <span class="text-gray-400">-</span>
                                                @endif
                                            </td>
                                            <td class="px-5 py-4 text-sm text-gray-700 dark:text-gray-300">
                                                {{ $row->dokter_perujuk }}
                                            </td>
                                            <td class="whitespace-nowrap px-5 py-4 text-center text-sm">
                                                <a href="{{ route('lis.periksa.tolis', $row->noorder) }}"
                                                    class="inline-flex items-center gap-1.5 rounded-lg bg-warning-500 px-3 py-1.5 text-xs font-medium text-white hover:bg-warning-600 transition">
                                                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                                                    </svg>
                                                    Simpan Ke LIS
                                                </a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="px-5 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                                                Tidak ada data permintaan laboratorium yang ditemukan.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="mt-4">
                        {{ $rows->links() }}
                    </div>
                @endif
            </x-common.component-card>
        @endif
    </div>
@endsection
