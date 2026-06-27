@extends('layouts.app')

@section('content')
    @php
        $umurStr = '-';
        if ($extPasien && !empty($extPasien->tgl_lahir)) {
            $diff = \Carbon\Carbon::parse($extPasien->tgl_lahir)->diff(now());
            $umurStr = "{$diff->y} Th {$diff->m} Bl {$diff->d} Hr";
        }
        
        $tanggalDate = date('Y-m-d', strtotime($model->tanggal));
        $tanggalTime = date('H:i', strtotime($model->tanggal));
    @endphp

    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-2.5">
            <a href="{{ route('lis.periksa.index') }}" class="inline-flex h-9 items-center justify-center rounded-lg border border-gray-300 bg-white px-4 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-950 dark:text-gray-300 dark:hover:bg-white/[0.05]">
                <svg class="mr-1.5 size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                Kembali
            </a>
            <h2 class="text-xl font-bold text-gray-900 dark:text-white">Input Data Hasil Periksa Laboratorium LIS / SIMRS</h2>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('lis.periksa.cetak-biaya', $model->id) }}" target="_blank" class="inline-flex h-9 items-center justify-center rounded-lg border border-gray-300 bg-white px-4 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-950 dark:text-gray-300">
                <svg class="mr-1.5 size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                Nota Biaya
            </a>
            @if ($model->no_reg && $model->no_reg !== '-')
                <a href="{{ route('lis.periksa.tolis', $model->no_reg) }}" class="inline-flex h-9 items-center justify-center rounded-lg border border-gray-300 bg-white px-4 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-950 dark:text-gray-300">
                    <svg class="mr-1.5 size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 1121.21 4.79M9 9h3.75" /></svg>
                    Resync SIMRS
                </a>
                <form method="POST" action="{{ route('lis.periksa.sync-simrs', $model->id) }}" class="inline-block">
                    @csrf
                    <button type="submit" class="inline-flex h-9 items-center justify-center rounded-lg bg-emerald-500 hover:bg-emerald-600 px-4 text-sm font-medium text-white shadow-sm transition">
                        <svg class="mr-1.5 size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" /></svg>
                        Kirim ke SIMRS
                    </button>
                </form>
            @endif
            <a href="{{ $lisUrl }}/index.php?r=periksa/view&id={{ $model->id }}" class="inline-flex h-9 items-center justify-center rounded-lg bg-brand-500 hover:bg-brand-600 px-4 text-sm font-medium text-white shadow-sm transition">
                <svg class="mr-1.5 size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                Input Parameter LIS
            </a>
            @if ($model->validasi == 1)
                <a href="{{ route('lis.periksa.cetak', $model->id) }}" target="_blank" class="inline-flex h-9 items-center justify-center rounded-lg bg-brand-500 px-4 text-sm font-medium text-white hover:bg-brand-600">
                    <svg class="mr-1.5 size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg>
                    Cetak Hasil
                </a>
            @endif
        </div>
    </div>

    @if (session('success'))
        <x-ui.alert variant="success" :message="session('success')" class="mb-6" />
    @endif

    <div x-data="{
        tglPeriksa: '{{ $tanggalDate }}',
        jamPeriksa: '{{ $tanggalTime }}',
        saveField(name, val) {
            fetch('{{ route('lis.periksa.update', $model->id) }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ field: name, value: val })
            })
            .then(res => res.json())
            .then(data => {
                if(data.status !== 'success') alert('Gagal memperbarui metadata');
            });
        },
        updateTanggal() {
            const combined = this.tglPeriksa + ' ' + this.jamPeriksa + ':00';
            this.saveField('tanggal', combined);
        }
    }">
    <div class="flex flex-col sm:flex-row items-start gap-6" style="display:flex !important; flex-direction:row !important; flex-wrap:nowrap !important; align-items:flex-start !important; gap:1.5rem !important;">

        <!-- LEFT COLUMN — Identity + Form -->
        <div class="w-full shrink-0" style="width:360px !important; max-width:360px !important; min-width:280px !important; flex-shrink:0 !important;">
            
            <!-- Patient & Exam Meta info Card (Consolidated) -->
            <x-common.component-card :title="'Input Data Hasil Periksa Laboratorium Patologi Klinis'">
                <div class="flex flex-col gap-6">

                    <!-- Identitas Pasien (read-only) -->
                    <div class="flex flex-col gap-4 border-b border-gray-100 dark:border-gray-800 pb-5">

                    {{-- Patient avatar + name --}}
                    <div class="flex items-center gap-3">
                        <div class="flex size-12 shrink-0 items-center justify-center rounded-full bg-brand-50 dark:bg-brand-500/10 border border-brand-100 dark:border-brand-500/20">
                            <svg class="size-6 text-brand-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-gray-900 dark:text-white leading-tight">
                                {{ $extPasien->nm_pasien ?? ($model->pasien->nama ?? '-') }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                {{ $umurStr }} &bull; {{ ($extPasien->jk ?? '') === 'L' ? 'Laki-laki' : 'Perempuan' }}
                            </p>
                        </div>
                    </div>

                    {{-- Identity badges row --}}
                    <div class="flex flex-wrap gap-2">
                        <span class="inline-flex h-7 items-center gap-1.5 rounded-full bg-gray-100 dark:bg-gray-800 px-3 text-xs font-semibold text-gray-600 dark:text-gray-300">
                            <svg class="size-3 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                            {{ $model->no_reg ?: '-' }}
                        </span>
                        <span class="inline-flex h-7 items-center gap-1.5 rounded-full bg-gray-100 dark:bg-gray-800 px-3 text-xs font-semibold text-gray-600 dark:text-gray-300">
                            <svg class="size-3 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0" /></svg>
                            RM: {{ $model->pasien->no_rm ?? '-' }}
                        </span>
                        @if($model->nomor)
                        <span class="inline-flex h-7 items-center gap-1.5 rounded-full bg-brand-50 dark:bg-brand-500/10 border border-brand-100 dark:border-brand-500/20 px-3 text-xs font-semibold text-brand-700 dark:text-brand-400">
                            <svg class="size-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" /></svg>
                            No. Lab: {{ $model->nomor }}
                        </span>
                        @endif
                    </div>

                    {{-- Divider --}}
                    <div class="border-t border-gray-100 dark:border-gray-800"></div>

                    {{-- Daftar Permintaan --}}
                    <div>
                        <span class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider block mb-2">
                            Daftar Permintaan
                        </span>
                        <div class="flex flex-wrap gap-2">
                            @forelse ($modelBiaya as $item)
                                @php $tarifDetail = $modelTarif->firstWhere('id', $item->tarif_id); @endphp
                                <span class="inline-flex h-7 items-center justify-center rounded-full bg-emerald-50 border border-emerald-100 px-3 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:border-emerald-500/20 dark:text-emerald-400">
                                    {{ $tarifDetail->nama ?? 'Tarif #' . $item->tarif_id }}
                                </span>
                            @empty
                                <span class="text-xs text-gray-400 italic">Tidak ada permintaan.</span>
                            @endforelse
                        </div>
                    </div>

                    </div>

                    <!-- Form Input Pemeriksaan -->
                    <div>
                        <p class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-4">
                            Data Pemeriksaan
                        </p>
                        <div class="grid grid-cols-1 gap-y-3" style="grid-template-columns: minmax(0, 1fr) !important;">

                        <!-- Dokter PJ -->
                        <div class="flex flex-col gap-1">
                            <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Dokter P.J.</span>
                            <div class="relative">
                                <select @change="saveField('id_dokter2', $event.target.value)" {{ $model->validasi == 1 ? 'disabled' : '' }}
                                    class="h-9 w-full rounded-lg border border-gray-300 bg-white dark:bg-gray-950 pl-3 pr-9 text-xs font-semibold text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white appearance-none">
                                    @foreach ($allDokters as $d)
                                        <option value="{{ $d->id }}" {{ $model->id_dokter2 == $d->id ? 'selected' : '' }}>{{ $d->nama }}</option>
                                    @endforeach
                                </select>
                                <span class="absolute inset-y-0 right-2.5 flex items-center pointer-events-none text-gray-400">
                                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                                </span>
                            </div>
                        </div>

                        <!-- Petugas Lab -->
                        <div class="flex flex-col gap-1">
                            <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Petugas</span>
                            <div class="relative">
                                <select @change="saveField('id_petugas', $event.target.value)" {{ $model->validasi == 1 ? 'disabled' : '' }}
                                    class="h-9 w-full rounded-lg border border-gray-300 bg-white dark:bg-gray-950 pl-3 pr-9 text-xs font-semibold text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white appearance-none">
                                    @foreach ($allPetugas as $p)
                                        <option value="{{ $p->id }}" {{ $model->id_petugas == $p->id ? 'selected' : '' }}>{{ $p->nama }}</option>
                                    @endforeach
                                </select>
                                <span class="absolute inset-y-0 right-2.5 flex items-center pointer-events-none text-gray-400">
                                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                                </span>
                            </div>
                        </div>

                        <!-- Dokter Perujuk -->
                        <div class="flex flex-col gap-1">
                            <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Dr. Perujuk</span>
                            <div class="relative">
                                <select @change="saveField('id_dokter', $event.target.value)" {{ $model->validasi == 1 ? 'disabled' : '' }}
                                    class="h-9 w-full rounded-lg border border-gray-300 bg-white dark:bg-gray-950 pl-3 pr-9 text-xs font-semibold text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white appearance-none">
                                    @foreach ($allDokters as $d)
                                        <option value="{{ $d->id }}" {{ $model->id_dokter == $d->id ? 'selected' : '' }}>{{ $d->nama }}</option>
                                    @endforeach
                                </select>
                                <span class="absolute inset-y-0 right-2.5 flex items-center pointer-events-none text-gray-400">
                                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                                </span>
                            </div>
                        </div>

                        <!-- Ruangan -->
                        <div class="flex flex-col gap-1">
                            <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Ruangan</span>
                            <div class="relative">
                                <select @change="saveField('id_ruang', $event.target.value)" {{ $model->validasi == 1 ? 'disabled' : '' }}
                                    class="h-9 w-full rounded-lg border border-gray-300 bg-white dark:bg-gray-950 pl-3 pr-9 text-xs font-semibold text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white appearance-none">
                                    @foreach ($allRuangs as $r)
                                        <option value="{{ $r->id }}" {{ $model->id_ruang == $r->id ? 'selected' : '' }}>{{ $r->nama }}</option>
                                    @endforeach
                                </select>
                                <span class="absolute inset-y-0 right-2.5 flex items-center pointer-events-none text-gray-400">
                                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                                </span>
                            </div>
                        </div>

                        <!-- Tanggal Periksa -->
                        <div class="flex flex-col gap-1">
                            <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Tanggal Periksa</span>
                            <input type="date" x-model="tglPeriksa" @change="updateTanggal()" {{ $model->validasi == 1 ? 'disabled' : '' }}
                                class="h-9 w-full rounded-lg border border-gray-300 bg-white dark:bg-gray-950 px-3 text-xs font-semibold text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white">
                        </div>

                        <!-- Jam Periksa -->
                        <div class="flex flex-col gap-1">
                            <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Jam Periksa</span>
                            <input type="time" x-model="jamPeriksa" @change="updateTanggal()" {{ $model->validasi == 1 ? 'disabled' : '' }}
                                class="h-9 w-full rounded-lg border border-gray-300 bg-white dark:bg-gray-950 px-3 text-xs font-semibold text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white">
                        </div>

                        <!-- Asuransi -->
                        <div class="flex flex-col gap-1">
                            <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Asuransi</span>
                            <select @change="saveField('penjamin', $event.target.value)" {{ $model->validasi == 1 ? 'disabled' : '' }}
                                class="h-9 w-full rounded-lg border border-gray-300 bg-white dark:bg-gray-950 px-3 text-xs text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white">
                                <option value="1" {{ $model->id_penjamin == 1 ? 'selected' : '' }}>UMUM</option>
                                <option value="2" {{ $model->id_penjamin == 2 ? 'selected' : '' }}>BPJS</option>
                                <option value="3" {{ $model->id_penjamin == 3 ? 'selected' : '' }}>{{ $extReg && $extReg->png_jawab ? $extReg->png_jawab : 'ASURANSI' }}</option>
                                <option value="4" {{ $model->id_penjamin == 4 ? 'selected' : '' }}>LAIN-LAIN</option>
                            </select>
                        </div>

                        <!-- Diagnosa Klinik -->
                        <div class="flex flex-col gap-1">
                            <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Diagnosa Klinik</span>
                            <input type="text" value="{{ $model->ket_klinik }}" @change="saveField('ket_klinik', $event.target.value)" {{ $model->validasi == 1 ? 'disabled' : '' }}
                                placeholder="Masukkan diagnosa..."
                                class="h-9 w-full rounded-lg border border-gray-300 bg-white dark:bg-gray-950 px-3 text-xs text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white">
                        </div>

                        <!-- Catatan -->
                        <div class="flex flex-col gap-1">
                            <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Catatan</span>
                            <input type="text" value="{{ $model->note }}" @change="saveField('note', $event.target.value)" {{ $model->validasi == 1 ? 'disabled' : '' }}
                                placeholder="Catatan tambahan..."
                                class="h-9 w-full rounded-lg border border-gray-300 bg-white dark:bg-gray-950 px-3 text-xs text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white">
                        </div>

                    </div>
                </div>
            </div>{{-- END SLOT WRAPPER flex-col gap-6 --}}
            </x-common.component-card>
        </div>{{-- END LEFT COLUMN --}}

        <!-- RIGHT COLUMN — Analyzer + Parameter Table -->
        <div class="flex-1 min-w-0" style="flex:1 1 0% !important; min-width:0 !important;">

        <!-- LIS Raw Analyzer Results (if exists) -->
        @if ($modelResult->count() > 0 && $model->validasi == 0)
            <div class="rounded-2xl border border-brand-200 bg-brand-50/50 p-6 dark:border-brand-500/30 dark:bg-brand-500/5">
                <h3 class="text-base font-bold text-brand-800 dark:text-brand-400 mb-2 flex items-center gap-1.5">
                    <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                    Hubungkan Hasil Analyzer (A 15)
                </h3>
                <p class="text-sm text-brand-700 dark:text-brand-300/80 mb-4">Ditemukan data hasil analyzer yang belum dihubungkan pada alat A 15 dengan kecocokan tanggal terdekat.</p>
                <form method="POST" action="{{ route('lis.periksa.alat', $model->id) }}" class="flex flex-wrap items-end gap-3.5">
                    @csrf
                    <input type="hidden" name="nomor" value="{{ $model->nomor }}">
                    <label class="flex-1 min-w-[200px]">
                        <span class="block text-xs font-semibold text-brand-800 dark:text-brand-400 mb-1">Pilih Data Analyzer (No. Sampel / Waktu)</span>
                        <select name="sampel" required
                            class="h-10 w-full rounded-lg border border-brand-300 bg-white px-3 text-sm text-gray-805 outline-none focus:border-brand-500 dark:border-brand-700 dark:bg-gray-950 dark:text-white/90">
                            @foreach ($modelResult as $res)
                                <option value="{{ $res->KodePatient }}">{{ $res->KodePatient }} (Alat: {{ $res->KodeAlat }}, Tanggal: {{ $res->tgl }})</option>
                            @endforeach
                        </select>
                    </label>
                    <input type="hidden" name="alat" value="A 15">
                    <button type="submit" class="h-10 rounded-lg bg-brand-500 hover:bg-brand-600 px-5 text-sm font-semibold text-white">
                        Tarik Data Analyzer
                    </button>
                </form>
            </div>
        @endif

        <!-- LIS Input Hasil Parameters Table Card -->
        <x-common.component-card :title="'Input Data Detail Hasil Pemeriksaan'">
            <div x-data='{
                selectedRows: [],
                selectAll: false,
                paramQuery: "",
                toggleSelectAll() {
                    if (this.selectAll) {
                        this.selectedRows = [@foreach($detail as $row) {{ $row->id }}, @endforeach];
                    } else {
                        this.selectedRows = [];
                    }
                },
                updateSelectAll() {
                    const totalRows = {{ count($detail) }};
                    this.selectAll = this.selectedRows.length === totalRows && totalRows > 0;
                },
                deleteSelected() {
                    if (this.selectedRows.length === 0) return;
                    if (confirm("Hapus " + this.selectedRows.length + " parameter terpilih?")) {
                        fetch("{{ route('lis.result.delete') }}", {
                            method: "POST",
                            headers: {
                                "Content-Type": "application/json",
                                "X-CSRF-TOKEN": "{{ csrf_token() }}"
                            },
                            body: JSON.stringify({ ids: this.selectedRows })
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.status === "success") {
                                window.location.reload();
                            } else {
                                alert("Gagal menghapus parameter");
                            }
                        });
                    }
                },
                updateValue(id, field, value) {
                    fetch("{{ route('lis.result.nilai') }}", {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json",
                            "X-CSRF-TOKEN": "{{ csrf_token() }}"
                        },
                        body: JSON.stringify({ id: id, field: field, value: value })
                    });
                },
                updateGeneric(url, payload) {
                    fetch(url, {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json",
                            "X-CSRF-TOKEN": "{{ csrf_token() }}"
                        },
                        body: JSON.stringify(payload)
                    });
                },
                deleteRow(id, btn) {
                    if(confirm("Hapus parameter ini dari periksa?")) {
                        fetch("{{ route('lis.result.delete') }}", {
                            method: "POST",
                            headers: {
                                "Content-Type": "application/json",
                                "X-CSRF-TOKEN": "{{ csrf_token() }}"
                            },
                            body: JSON.stringify({ id: id })
                        })
                        .then(() => {
                            btn.closest("tr").remove();
                            const idx = this.selectedRows.indexOf(String(id));
                            if (idx > -1) {
                                this.selectedRows.splice(idx, 1);
                            }
                            this.updateSelectAll();
                        });
                    }
                }
            }'>
                <!-- Parameter Search Box and Add Actions (SIMRS style) -->
                <div class="mb-4 flex flex-wrap items-center justify-between gap-4 border-b border-gray-100 pb-4 dark:border-gray-800">
                    <!-- Left: Search Box for Parameters -->
                    <div class="flex items-center gap-3 flex-1 min-w-[280px] max-w-md">
                        <span class="text-sm font-semibold text-gray-700 dark:text-gray-300 whitespace-nowrap">Detail Pemeriksaan :</span>
                        <div class="relative flex-1">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400 pointer-events-none">
                                <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                            </span>
                            <input type="text" x-model="paramQuery" placeholder="Cari nama parameter pemeriksaan..."
                                class="h-9 w-full rounded-full border border-gray-300 bg-white dark:bg-gray-950 pl-9 pr-4 text-xs text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white">
                        </div>
                    </div>

                    <!-- Right: Add Actions and Bulk Actions -->
                    <div class="flex flex-wrap items-center gap-2">
                        @if ($model->validasi == 0)
                            <button type="button" @click="$dispatch('open-add-test-modal', { type: 'manual' })"
                                class="inline-flex h-9 items-center gap-1.5 rounded-full bg-brand-50 hover:bg-brand-100 px-4 text-xs font-semibold text-brand-600 dark:bg-brand-500/10 dark:text-brand-400 transition">
                                <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                                + Parameter
                            </button>
                            <button type="button" @click="$dispatch('open-add-test-modal', { type: 'paket' })"
                                class="inline-flex h-9 items-center gap-1.5 rounded-full bg-brand-50 hover:bg-brand-100 px-4 text-xs font-semibold text-brand-600 dark:bg-brand-500/10 dark:text-brand-400 transition">
                                <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2" /></svg>
                                + Paket LIS
                            </button>
                            <button type="button" @click="$dispatch('open-add-test-modal', { type: 'formula' })"
                                class="inline-flex h-9 items-center gap-1.5 rounded-full bg-brand-50 hover:bg-brand-100 px-4 text-xs font-semibold text-brand-600 dark:bg-brand-500/10 dark:text-brand-400 transition">
                                <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>
                                + Formula
                            </button>
                            
                            <div x-show="selectedRows.length > 0" x-transition x-cloak class="inline-block">
                                <button type="button" @click="deleteSelected()"
                                    class="inline-flex h-9 items-center gap-1.5 rounded-full bg-error-50 hover:bg-error-100 px-4 text-xs font-semibold text-error-600 dark:bg-error-500/10 dark:text-error-400 transition">
                                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                    Hapus Terpilih (<span x-text="selectedRows.length"></span>)
                                </button>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800">
                    <div class="max-w-full overflow-auto custom-scrollbar" style="max-height: 450px;">
                        <table class="w-full min-w-[800px] border-collapse text-left">
                            <thead class="sticky top-0 z-10">
                                <tr class="border-b border-gray-200 dark:border-gray-800">
                                    @if($model->validasi == 0)
                                        <th class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-900 px-4 py-2.5 text-center text-xs font-medium uppercase text-gray-500 w-10">P</th>
                                    @endif
                                    <th class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-900 px-4 py-2.5 text-xs font-medium uppercase text-gray-500">Pemeriksaan</th>
                                    <th class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-900 px-4 py-2.5 text-xs font-medium uppercase text-gray-500 w-36">Hasil</th>
                                    <th class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-900 px-4 py-2.5 text-xs font-medium uppercase text-gray-500 w-24">Satuan</th>
                                    <th class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-900 px-4 py-2.5 text-xs font-medium uppercase text-gray-500 w-36">Nilai Rujukan</th>
                                    <th class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-900 px-4 py-2.5 text-xs font-medium uppercase text-gray-500 w-24">Tanda</th>
                                    @if($model->validasi == 0)
                                        <th class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-900 px-4 py-2.5 text-center text-xs font-medium uppercase text-gray-500 w-16">Aksi</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @php
                                    $groupedDetail = [];
                                    foreach ($detail as $row) {
                                        $g1 = $row->grup1 ?: 'LAIN-LAIN';
                                        $g2 = $row->grup2 ?: $g1;
                                        $g3 = $row->grup3 ?: $g2;
                                        $groupedDetail[$g1][$g2][$g3][] = $row;
                                    }
                                @endphp
                                @forelse ($groupedDetail as $grup1 => $g2s)
                                    @php
                                        $g1Params = [];
                                        foreach ($g2s as $g2 => $g3s) {
                                            foreach ($g3s as $g3 => $rows) {
                                                foreach ($rows as $row) {
                                                    $g1Params[] = addslashes(strtolower($row->nama ?? $row->KodeParamater));
                                                }
                                            }
                                        }
                                    @endphp
                                    <tr class="bg-slate-50 dark:bg-slate-800/40 border-b border-gray-200 dark:border-gray-800"
                                        x-show="paramQuery === '' || {{ json_encode($g1Params) }}.some(p => p.includes(paramQuery.toLowerCase()))">
                                        <td colspan="100" class="px-4 py-3 bg-slate-50 dark:bg-slate-800/40">
                                            <div class="flex items-center gap-2">
                                                <svg class="size-4 text-brand-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                                                </svg>
                                                <span class="text-xs font-bold tracking-wider text-slate-700 dark:text-slate-200 uppercase">
                                                    {{ $grup1 }}
                                                </span>
                                            </div>
                                        </td>
                                    </tr>

                                    @foreach ($g2s as $grup2 => $g3s)
                                        @php
                                            $g2Params = [];
                                            foreach ($g3s as $g3 => $rows) {
                                                foreach ($rows as $row) {
                                                    $g2Params[] = addslashes(strtolower($row->nama ?? $row->KodeParamater));
                                                }
                                            }
                                        @endphp
                                        @if ($grup2 !== $grup1)
                                            <tr class="bg-gray-50/40 dark:bg-gray-800/20 border-b border-gray-255 dark:border-gray-755"
                                                x-show="paramQuery === '' || {{ json_encode($g2Params) }}.some(p => p.includes(paramQuery.toLowerCase()))">
                                                <td colspan="100" class="px-4 py-2 pl-8">
                                                    <div class="flex items-center gap-1.5">
                                                        <svg class="size-3 text-gray-400 rotate-90" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                                        </svg>
                                                        <span class="text-xs font-bold text-gray-650 dark:text-gray-300">
                                                            {{ $grup2 }}
                                                        </span>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endif

                                        @foreach ($g3s as $grup3 => $rows)
                                            @php
                                                $g3Params = [];
                                                foreach ($rows as $row) {
                                                    $g3Params[] = addslashes(strtolower($row->nama ?? $row->KodeParamater));
                                                }
                                            @endphp
                                            @if ($grup3 !== $grup2)
                                                <tr class="bg-gray-50/10 dark:bg-gray-800/5 border-b border-gray-150 dark:border-gray-850"
                                                    x-show="paramQuery === '' || {{ json_encode($g3Params) }}.some(p => p.includes(paramQuery.toLowerCase()))">
                                                    <td colspan="100" class="px-4 py-1.5 pl-12">
                                                        <span class="text-[11px] font-semibold text-gray-550 dark:text-gray-400 italic">
                                                            {{ $grup3 }}
                                                        </span>
                                                    </td>
                                                </tr>
                                            @endif

                                            @foreach ($rows as $row)
                                                <tr class="hover:bg-gray-50/20 dark:hover:bg-gray-800/10"
                                                    x-show="paramQuery === '' || '{{ addslashes(strtolower($row->nama ?? $row->KodeParamater)) }}'.includes(paramQuery.toLowerCase())">
                                                    @if($model->validasi == 0)
                                                        <td class="px-4 py-3 text-center">
                                                            <input type="checkbox" value="{{ $row->id }}" x-model="selectedRows" @change="updateSelectAll()"
                                                                class="size-4 text-brand-600 focus:ring-brand-500 border-gray-300 dark:border-gray-700 dark:bg-gray-800 rounded cursor-pointer">
                                                        </td>
                                                    @endif
                                                    <td class="px-4 py-3 font-semibold text-gray-905 dark:text-white pl-8">
                                                        {{ $row->nama ?? $row->KodeParamater }}
                                                        <span class="block text-[10px] font-normal text-gray-400">LIS: {{ $row->KodeParamater }} / Alat: {{ $row->KodeAlat }}</span>
                                                    </td>
                                                    <td class="px-4 py-3">
                                                        <input type="text" value="{{ $row->Nilai }}" 
                                                            @change="updateGeneric('{{ route('lis.result.nilai') }}', { id: {{ $row->id }}, value: $event.target.value })"
                                                            {{ $model->validasi == 1 ? 'disabled' : '' }}
                                                            class="h-9 w-full rounded border border-gray-300 bg-transparent px-2 text-center text-sm font-semibold text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white/90">
                                                    </td>
                                                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300 font-mono">
                                                        {{ $row->satuan ?: '-' }}
                                                    </td>
                                                    <td class="px-4 py-3 text-sm text-gray-650 dark:text-gray-300 font-mono">
                                                        {{ $row->normal_range ?: '-' }}
                                                    </td>
                                                    <td class="px-4 py-3">
                                                        <select @change="updateGeneric('{{ route('lis.result.tanda') }}', { id: {{ $row->id }}, value: $event.target.value })"
                                                            {{ $model->validasi == 1 ? 'disabled' : '' }}
                                                            class="h-9 w-full rounded border border-gray-300 bg-transparent px-1 text-center text-xs font-semibold text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white/90">
                                                            <option value="" class="dark:bg-gray-950" {{ $row->tanda == '' ? 'selected' : '' }}>-</option>
                                                            <option value="L" class="dark:bg-gray-950" {{ $row->tanda == 'L' ? 'selected' : '' }}>L</option>
                                                            <option value="H" class="dark:bg-gray-950" {{ $row->tanda == 'H' ? 'selected' : '' }}>H</option>
                                                            <option value="#" class="dark:bg-gray-950" {{ $row->tanda == '#' ? 'selected' : '' }}>#</option>
                                                        </select>
                                                    </td>
                                                    @if($model->validasi == 0)
                                                        <td class="px-4 py-3 text-center">
                                                            <button type="button" @click="deleteRow({{ $row->id }}, $event.target)"
                                                                class="inline-flex size-7 items-center justify-center rounded bg-error-50 text-error-600 hover:bg-error-100 dark:bg-error-500/10 dark:text-error-400">
                                                                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                                            </button>
                                                        </td>
                                                    @endif
                                                </tr>
                                            @endforeach
                                        @endforeach
                                    @endforeach
                                @empty
                                    <tr>
                                        <td colspan="{{ $model->validasi == 0 ? 7 : 5 }}" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                            Belum ada parameter pemeriksaan. Tambahkan parameter menggunakan tombol di atas.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                @if ($model->validasi == 0 && count($detail) > 0)
                    <div class="mt-6 flex justify-end gap-3 border-t border-gray-100 pt-4 dark:border-gray-800">
                        <form method="POST" action="{{ route('lis.periksa.selesai', $model->id) }}">
                            @csrf
                            <button type="submit" class="inline-flex h-11 items-center justify-center rounded-lg bg-emerald-500 hover:bg-emerald-600 px-6 text-sm font-semibold text-white shadow-sm transition">
                                Validasi & Selesaikan Pemeriksaan
                            </button>
                        </form>
                    </div>
                @endif
            </div>
        </x-common.component-card>
    </div>

    <!-- Modals for Add Test (Manual/Paket/Formula) -->
    <div x-data="{ open: false, type: '', query: '', selected: [] }"
        @open-add-test-modal.window="open = true; type = $event.detail.type; selected = []; query = ''"
        class="fixed inset-0 z-999999 flex items-center justify-center bg-black/60 backdrop-blur-xs p-4" x-show="open" x-cloak>
        <div @click.away="open = false" 
            :class="type === 'manual' ? 'w-full max-w-4xl' : 'w-full max-w-xl'"
            class="rounded-2xl bg-white p-6 shadow-xl dark:bg-gray-900 border border-gray-100 dark:border-gray-800 text-left flex flex-col max-h-[90vh]">
            
            <div class="flex items-center justify-between mb-2">
                <h3 class="text-lg font-bold text-gray-950 dark:text-white" x-text="type === 'manual' ? 'Tambah Parameter Manual' : (type === 'paket' ? 'Tambah Paket LIS' : 'Tambah Formula')"></h3>
                <button type="button" @click="open = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                    <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <p class="text-sm text-gray-500 mb-4">Pilih parameter untuk dimasukkan ke LIS nomor: <span class="font-semibold text-gray-800 dark:text-gray-200">{{ $model->nomor }}</span></p>

            <div class="relative mb-4">
                <span class="absolute -translate-y-1/2 pointer-events-none left-4 top-1/2 text-gray-400 dark:text-gray-500">
                    <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </span>
                <input type="text" x-model="query" placeholder="Cari..." 
                    class="h-11 w-full rounded-lg border border-gray-300 bg-transparent pl-11 pr-4 text-sm text-gray-800 outline-none placeholder:text-gray-400 focus:border-brand-500 dark:border-gray-700 dark:text-white/90 focus:ring-3 focus:ring-brand-500/10 transition duration-150">
            </div>

            <div class="flex-1 overflow-hidden min-h-0 flex flex-col">
                <!-- Manual List Table -->
                <template x-if="type === 'manual'">
                    <div class="flex-1 overflow-auto border border-gray-100 dark:border-gray-800 rounded-lg custom-scrollbar min-h-0">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead class="bg-gray-50 dark:bg-gray-850 sticky top-0 z-10 border-b border-gray-200 dark:border-gray-700">
                                <tr>
                                    <th class="p-2.5 w-10 text-center"></th>
                                    <th class="p-2.5">Nama</th>
                                    <th class="p-2.5 w-24">LIS</th>
                                    <th class="p-2.5 w-32">Grup</th>
                                    <th class="p-2.5 w-32">Grup 2</th>
                                    <th class="p-2.5 w-28">Alat</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-850">
                                @foreach ($modelKode as $kode)
                                    <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/30"
                                        x-show="query.trim() === '' || '{{ addslashes(strtolower($kode->nama ?? '')) }}'.includes(query.toLowerCase()) || '{{ addslashes(strtolower($kode->lis ?? '')) }}'.includes(query.toLowerCase()) || '{{ addslashes(strtolower($kode->grup1 ?? '')) }}'.includes(query.toLowerCase()) || '{{ addslashes(strtolower($kode->grup2 ?? '')) }}'.includes(query.toLowerCase()) || '{{ addslashes(strtolower($kode->alat ?? '')) }}'.includes(query.toLowerCase())">
                                        <td class="p-2.5 text-center">
                                            <input type="checkbox" value="{{ $kode->id }}" x-model="selected" class="size-4 text-brand-600 rounded cursor-pointer">
                                        </td>
                                        <td class="p-2.5 font-semibold text-gray-800 dark:text-gray-200">{{ $kode->nama }}</td>
                                        <td class="p-2.5 font-mono text-gray-500">{{ $kode->lis }}</td>
                                        <td class="p-2.5 text-gray-400">{{ $kode->grup1 }}</td>
                                        <td class="p-2.5 text-gray-400">{{ $kode->grup2 }}</td>
                                        <td class="p-2.5 text-gray-400">{{ $kode->alat }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </template>

                <!-- Paket List -->
                <template x-if="type === 'paket'">
                    <div class="flex-1 overflow-y-auto border border-gray-100 dark:border-gray-800 rounded-lg p-2.5 space-y-1 custom-scrollbar min-h-0">
                        @foreach ($modelPaket as $paket)
                            <label class="flex items-center gap-2.5 p-2 hover:bg-gray-50 dark:hover:bg-gray-800/50 rounded cursor-pointer"
                                x-show="query.trim() === '' || '{{ addslashes(strtolower($paket->nama ?? '')) }}'.includes(query.toLowerCase())">
                                <input type="checkbox" value="{{ $paket->id }}" x-model="selected" class="size-4 text-brand-600 rounded">
                                <div class="text-sm">
                                    <span class="font-semibold text-gray-800 dark:text-gray-200">{{ $paket->nama }}</span>
                                </div>
                            </label>
                        @endforeach
                    </div>
                </template>

                <!-- Formula List -->
                <template x-if="type === 'formula'">
                    <div class="flex-1 overflow-y-auto border border-gray-100 dark:border-gray-800 rounded-lg p-2.5 space-y-1 custom-scrollbar min-h-0">
                        @foreach ($modelFormula as $formula)
                            <label class="flex items-center gap-2.5 p-2 hover:bg-gray-50 dark:hover:bg-gray-800/50 rounded cursor-pointer"
                                x-show="query.trim() === '' || '{{ addslashes(strtolower($formula->nama ?? '')) }}'.includes(query.toLowerCase())">
                                <input type="checkbox" value="{{ $formula->id }}" x-model="selected" class="size-4 text-brand-600 rounded">
                                <div class="text-sm">
                                    <span class="font-semibold text-gray-800 dark:text-gray-200">{{ $formula->nama }}</span>
                                </div>
                            </label>
                        @endforeach
                    </div>
                </template>
            </div>

            <form method="POST" :action="'/lis/periksa/{{ $model->id }}/' + type" class="mt-4 flex justify-end gap-2">
                @csrf
                <input type="hidden" name="kode" :value="selected.join(',')">
                <button type="button" @click="open = false" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg dark:bg-gray-800 dark:text-gray-300">Batal</button>
                <button type="submit" :disabled="selected.length === 0" class="px-4 py-2 text-sm font-medium text-white bg-brand-500 hover:bg-brand-600 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed">Tambahkan</button>
            </form>
        </div>
    </div>{{-- END LAYOUT WRAP --}}
    </div>{{-- END FLEX WRAPPER --}}
    </div>{{-- END X-DATA --}}
@endsection
