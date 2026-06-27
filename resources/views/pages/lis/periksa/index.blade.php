@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb :pageTitle="$title" />

    <div class="space-y-6" x-data="{ 
        deleteId: null,
        deleteNomor: '',
        showDeleteModal: false,
        confirmDelete(id, nomor) { this.deleteId = id; this.deleteNomor = nomor; this.showDeleteModal = true; },
        openAddModal: {{ $errors->any() ? 'true' : 'false' }}, 
        searchResults: [],
        isSearching: false,
        selectedPatient: null,
        isManualPatient: false,
        selectedDate: '{{ date('Y-m-d') }}',
        manualRM: '',
        manualNama: '',
        manualTempatLahir: '',
        manualTglLahir: '',
        manualGender: '1',
        manualAlamat: '',
        searchQuery: '',
        openDropdown: false,
        selectedDokter: '{{ $allDokters->first()->id ?? '' }}',
        selectedDokter2: '{{ $allDokters->first()->id ?? '' }}',
        selectedRuang: '{{ $allRuangs->first()->id ?? '' }}',
        selectedPetugas: '{{ $allPetugas->first()->id ?? '' }}',
        selectedPenjamin: '1',
        
        init() {
            this.$watch('selectedDate', value => {
                this.loadRegisteredPatients();
            });
            this.$watch('openDropdown', value => {
                if (value) {
                    this.searchQuery = '';
                } else {
                    if (this.selectedPatient) {
                        this.searchQuery = this.selectedPatient.no_rm + ' - ' + this.selectedPatient.nama;
                    } else {
                        this.searchQuery = '';
                    }
                }
            });
            this.loadRegisteredPatients();
        },
        filteredPatients() {
            if (!this.searchQuery) return this.searchResults;
            const q = this.searchQuery.toLowerCase();
            return this.searchResults.filter(p => {
                return (p.no_rm && p.no_rm.toLowerCase().includes(q)) || 
                       (p.nama && p.nama.toLowerCase().includes(q)) ||
                       (p.nama_ruang && p.nama_ruang.toLowerCase().includes(q));
            });
        },
        loadRegisteredPatients() {
            this.isSearching = true;
            this.searchResults = [];
            fetch('{{ route('lis.periksa.search-pasien') }}?date=' + this.selectedDate)
                .then(res => res.json())
                .then(data => {
                    this.searchResults = data;
                    this.isSearching = false;
                })
                .catch(err => {
                    console.error(err);
                    this.isSearching = false;
                });
        },
        selectPatient(p) {
            this.selectedPatient = p;
            this.searchQuery = p.no_rm + ' - ' + p.nama;

            // Auto-select Dokter Pengirim
            if (p.id_dokter_local) {
                this.selectedDokter = p.id_dokter_local;
            }

            // Auto-select Ruangan
            if (p.id_ruang_local) {
                this.selectedRuang = p.id_ruang_local;
            }

            // Auto-select Status Pasien (Penjamin)
            if (p.nama_penjamin) {
                const name = p.nama_penjamin.toUpperCase();
                if (name.includes('BPJS') || name.includes('KIS') || name.includes('ASKES')) {
                    this.selectedPenjamin = '2'; // BPJS
                } else if (name.includes('UMUM') || name.includes('CASH') || name.includes('MANDIRI') || name.includes('KOSONG')) {
                    this.selectedPenjamin = '1'; // UMUM
                } else {
                    this.selectedPenjamin = '3'; // ASURANSI
                }
            }
        }
    }">
        @if (session('success'))
            <x-ui.alert variant="success" :message="session('success')" class="mb-6" />
        @endif
        @if ($errors->any())
            <x-ui.alert variant="error" title="Terdapat beberapa kesalahan:" class="mb-6">
                <ul class="list-disc list-inside mt-1 text-sm">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-ui.alert>
        @endif

        <!-- Filter Card -->
        <x-common.component-card :title="'Filter Pencarian'" desc="Cari data pemeriksaan LIS berdasarkan tanggal periksa, status, dan kata kunci.">
            <form method="GET" action="{{ route('lis.periksa.index') }}" class="grid grid-cols-1 gap-4 lg:grid-cols-12">
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
                    <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Pencarian</span>
                    <input type="search" name="search" value="{{ $filters['search'] }}"
                        placeholder="No. Lab, No. Reg/Order, RM, Nama..."
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm text-gray-800 outline-none placeholder:text-gray-400 focus:border-brand-500 dark:border-gray-700 dark:text-white/90">
                </label>

                <div class="flex items-end gap-2 lg:col-span-2">
                    <button type="submit"
                        class="h-11 flex-1 rounded-lg bg-brand-500 px-4 text-sm font-medium text-white hover:bg-brand-600">
                        Terapkan
                    </button>
                    <a href="{{ route('lis.periksa.index', ['status' => $filters['status']]) }}"
                        class="inline-flex h-11 items-center rounded-lg border border-gray-300 px-3 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.05]">
                        Reset
                    </a>
                </div>
            </form>
        </x-common.component-card>

        <!-- Status Tabs Navigation -->
        <div class="flex flex-wrap gap-2">
            <button type="button" @click="openAddModal = true; selectedPatient = null;"
                class="rounded-lg border border-transparent px-4 py-2 text-sm font-semibold bg-brand-500 hover:bg-brand-600 text-white flex items-center gap-1.5 dark:bg-brand-500 dark:hover:bg-brand-600">
                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                + Tambah
            </button>
            <a href="{{ route('lis.periksa.simrs') }}"
                class="rounded-lg border px-4 py-2 text-sm font-medium border-gray-200 text-gray-600 hover:border-brand-300 dark:border-gray-800 dark:text-gray-300 dark:hover:border-gray-700">
                Periksa SIMRS
            </a>
            @foreach ([
                'all' => 'Semua Pemeriksaan',
                'pending' => 'Pending',
                'hasil' => 'Hasil',
                'biaya' => 'Manajemen Biaya / Billing'
            ] as $key => $label)
                <a href="{{ route('lis.periksa.index', array_merge(['status' => $key], request()->except(['status', 'page']))) }}"
                    class="rounded-lg border px-4 py-2 text-sm font-medium {{ $filters['status'] === $key ? 'border-brand-500 bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400' : 'border-gray-200 text-gray-600 hover:border-brand-300 dark:border-gray-800 dark:text-gray-300 dark:hover:border-gray-700' }}">
                    {{ $label }}
                </a>
            @endforeach
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

        @if ($connectionError)
            <x-ui.alert variant="warning" title="Koneksi SIMRS Bermasalah" :message="$connectionError" class="mb-6" />
        @endif

        <!-- List Card -->
        <x-common.component-card :title="'Daftar Pemeriksaan LIS'">
            <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800">
                <div class="max-w-full overflow-x-auto custom-scrollbar">
                    <table class="w-full min-w-[1000px] border-collapse">
                        <thead class="bg-gray-50 dark:bg-gray-900">
                            <tr class="border-b border-gray-200 dark:border-gray-800">
                                <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">No. Lab (LIS)</th>
                                <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">No. Registrasi SIMRS</th>
                                <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Tanggal Periksa</th>
                                <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">No. RM</th>
                                <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Nama Pasien</th>
                                <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Dokter Pengirim</th>
                                <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Poliklinik / Ruang</th>
                                <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Perusahaan/asuransi</th>
                                <th class="px-5 py-3 text-center text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Status</th>
                                <th class="px-5 py-3 text-center text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse ($rows as $row)
                                <tr class="hover:bg-gray-50/30 dark:hover:bg-gray-800/10">
                                    <td class="whitespace-nowrap px-5 py-4 text-sm font-semibold text-brand-600 dark:text-brand-400">
                                        {{ $row->nomor }}
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-4 text-sm text-gray-500 dark:text-gray-400">
                                        {{ $row->no_reg ?: '-' }}
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-4 text-sm text-gray-600 dark:text-gray-300">
                                        {{ \Illuminate\Support\Carbon::parse($row->tanggal)->format('d/m/Y H:i') }}
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-4 text-sm font-medium text-gray-700 dark:text-gray-300">
                                        {{ $row->pasien->no_rm ?? '-' }}
                                    </td>
                                    <td class="px-5 py-4 text-sm text-gray-900 dark:text-white font-semibold">
                                        {{ $row->ext_pasien->nm_pasien ?? ($row->pasien->nama ?? '-') }}
                                    </td>
                                    <td class="px-5 py-4 text-sm text-gray-600 dark:text-gray-400">
                                        {{ $row->ext_dokter->nm_dokter ?? ($row->dokter->nama ?? '-') }}
                                    </td>
                                    <td class="px-5 py-4 text-sm text-gray-600 dark:text-gray-400">
                                        {{ $row->ext_ruang->nm_poli ?? ($row->ruang->nama ?? '-') }}
                                    </td>
                                    <td class="px-5 py-4 text-sm text-gray-600 dark:text-gray-400 font-medium">
                                        {{ $row->ext_reg->png_jawab ?? ($row->id_penjamin == 1 ? 'UMUM' : ($row->id_penjamin == 2 ? 'BPJS' : ($row->id_penjamin == 3 ? 'ASURANSI' : 'LAIN-LAIN'))) }}
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-4 text-center text-sm">
                                        @if ($row->state == 1 && $row->validasi == 1)
                                            <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400">
                                                Hasil Selesai
                                            </span>
                                        @elseif ($row->state == 1 && $row->validasi == 0)
                                            <span class="inline-flex rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700 dark:bg-blue-500/15 dark:text-blue-400">
                                                Belum Validasi
                                            </span>
                                        @else
                                            <span class="inline-flex rounded-full bg-warning-50 px-2.5 py-1 text-xs font-semibold text-warning-700 dark:bg-warning-500/15 dark:text-warning-400">
                                                Proses (Pending)
                                            </span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-4 text-center text-sm">
                                        <div class="flex items-center justify-center gap-1.5">
                                            <a href="{{ route('lis.periksa.show', $row->id) }}"
                                                class="inline-flex items-center gap-1 rounded-md bg-brand-50 px-2 py-1 text-xs font-medium text-brand-600 hover:bg-brand-100 dark:bg-brand-500/10 dark:text-brand-400">
                                                Edit / Input
                                            </a>
                                            <a href="{{ route('lis.periksa.biaya', $row->id) }}"
                                                class="inline-flex items-center gap-1 rounded-md bg-warning-50 px-2 py-1 text-xs font-medium text-warning-600 hover:bg-warning-100 dark:bg-warning-500/10 dark:text-warning-400">
                                                Biaya
                                            </a>
                                            @if ($row->validasi == 1)
                                                <a href="{{ route('lis.periksa.cetak', $row->id) }}" target="_blank"
                                                    class="inline-flex items-center gap-1 rounded-md bg-gray-100 px-2 py-1 text-xs font-medium text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300">
                                                    Cetak
                                                </a>
                                            @endif
                                            <button type="button"
                                                @click="confirmDelete({{ $row->id }}, '{{ $row->nomor }}')"
                                                class="inline-flex items-center gap-1 rounded-md bg-error-50 px-2 py-1 text-xs font-medium text-error-600 hover:bg-error-100 dark:bg-error-500/10 dark:text-error-400">
                                                <svg class="size-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                                Hapus
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="px-5 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                                        Tidak ada data pemeriksaan LIS yang ditemukan.
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
        </x-common.component-card>

        <!-- Main Manual Add Modal -->
        <div class="fixed inset-0 z-[50] flex items-center justify-center bg-black/50 p-4" x-show="openAddModal" x-cloak>
            <div @click.away="openAddModal = false" class="w-full max-w-2xl rounded-2xl bg-white p-6 shadow-xl dark:bg-gray-900 border border-gray-100 dark:border-gray-800 text-left">
                <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 pb-3 mb-4">
                    <h3 class="text-lg font-bold text-gray-950 dark:text-white">Tambah Pemeriksaan LIS Manual</h3>
                    <button type="button" @click="openAddModal = false" class="text-gray-400 hover:text-gray-500">
                        <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                <form method="POST" action="{{ route('lis.periksa.store-manual') }}" class="grid grid-cols-2 gap-4">
                    @csrf

                    @if ($errors->any())
                        <div class="col-span-2">
                            <x-ui.alert variant="error" title="Gagal Menyimpan Pemeriksaan:">
                                <ul class="list-disc list-inside mt-1 text-sm">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </x-ui.alert>
                        </div>
                    @endif

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nomor <span class="text-red-500">*</span></label>
                        <input type="text" readonly :value="selectedPatient ? selectedPatient.no_rawat : ''" placeholder="xxxxxxxxxx"
                            class="h-11 w-full rounded-lg border border-gray-300 bg-gray-50 px-4 text-sm text-gray-800 outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white/90">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tanggal <span class="text-red-500">*</span></label>
                        <input type="date" name="tanggal" required x-model="selectedDate"
                            class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white/90">
                    </div>

                    <div class="col-span-2">
                        <div class="flex items-center justify-between mb-1">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Pasien <span class="text-red-500">*</span></label>
                            <label class="inline-flex items-center text-xs text-brand-600 hover:text-brand-700 cursor-pointer">
                                <input type="checkbox" x-model="isManualPatient" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500 mr-1.5">
                                Input Pasien Manual (Offline/Baru)
                            </label>
                        </div>

                        <!-- Dropdown Select (when not manual) -->
                        <div x-show="!isManualPatient" class="relative">
                            <!-- Input Search Trigger -->
                            <div class="relative">
                                <input type="text" 
                                    x-model="searchQuery" 
                                    @focus="openDropdown = true; $el.select();"
                                    @input="openDropdown = true"
                                    placeholder="Pilih/Cari Pasien dari reg_periksa..."
                                    class="h-11 w-full rounded-lg border border-gray-300 bg-white dark:bg-gray-900 pl-3 pr-10 text-sm outline-none focus:border-brand-500 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 text-gray-800 dark:text-white/90 placeholder:text-gray-450 dark:placeholder:text-gray-500 transition duration-150 font-medium">
                                
                                <!-- Clickable Search Icon Button -->
                                <button type="button" @click="openDropdown = !openDropdown"
                                    class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-450 hover:text-brand-500 dark:text-gray-500 dark:hover:text-brand-400">
                                    <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                </button>
                            </div>

                            <!-- Dropdown List Container -->
                            <div x-show="openDropdown" @click.away="openDropdown = false" x-transition x-cloak
                                class="absolute left-0 mt-1.5 z-[60] w-full rounded-xl border border-gray-200 bg-white p-3 shadow-xl dark:border-gray-800 dark:bg-gray-950">
                                
                                <!-- Scrollable Patient Options -->
                                <div class="max-h-[220px] overflow-y-auto custom-scrollbar space-y-1 pr-0.5">
                                    <!-- Searching Indicator -->
                                    <div x-show="isSearching" class="flex items-center justify-center py-4">
                                        <div class="h-5 w-5 animate-spin rounded-full border-2 border-brand-500 border-t-transparent"></div>
                                        <span class="ml-2.5 text-xs text-gray-400">Memuat data pasien...</span>
                                    </div>

                                    <!-- Empty State: No register for current date -->
                                    <div x-show="!isSearching && searchResults.length === 0" class="py-4 text-center text-xs text-gray-400 italic">
                                        Tidak ada registrasi pasien pada tanggal <span x-text="selectedDate"></span>
                                    </div>

                                    <!-- Empty State: Query did not match local list -->
                                    <div x-show="!isSearching && searchResults.length > 0 && filteredPatients().length === 0" class="py-4 text-center text-xs text-gray-400 italic">
                                        Pasien tidak ditemukan.
                                    </div>

                                    <!-- Options -->
                                    <template x-for="p in filteredPatients()" :key="p.no_rm + '-' + (p.no_reg || '')">
                                        <button type="button" @click="selectPatient(p); openDropdown = false;"
                                            class="w-full text-left px-3 py-2 rounded-lg transition duration-150 text-xs flex flex-col gap-0.5 hover:bg-gray-50 dark:hover:bg-white/[0.03]"
                                            :class="selectedPatient && selectedPatient.no_rm === p.no_rm ? 'bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400 font-semibold' : 'text-gray-700 dark:text-gray-300'">
                                            <div class="flex justify-between items-center w-full">
                                                <span class="font-semibold text-gray-900 dark:text-white" x-text="p.nama"></span>
                                                <span class="text-[10px] text-gray-455 dark:text-gray-400 font-mono" x-text="p.no_rm"></span>
                                            </div>
                                            <div class="flex justify-between items-center w-full text-[10px] text-gray-550 dark:text-gray-400">
                                                <span x-text="'Ruang: ' + (p.nama_ruang || '-')"></span>
                                                <span class="px-1.5 py-0.25 rounded bg-gray-200 dark:bg-gray-800 text-gray-600 dark:text-gray-350" x-text="p.nama_penjamin || 'Umum'"></span>
                                            </div>
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <!-- Manual Patient Fields (when manual) -->
                        <div x-show="isManualPatient" class="grid grid-cols-2 gap-3 border border-gray-200 dark:border-gray-800 p-3 rounded-lg mt-1.5 bg-gray-50/50 dark:bg-white/[0.01]">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">No. RM <span class="text-red-500">*</span></label>
                                <input type="text" x-model="manualRM" name="manual_no_rm" placeholder="Contoh: 123456" :required="isManualPatient"
                                    class="h-9 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-xs outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Nama Pasien <span class="text-red-500">*</span></label>
                                <input type="text" x-model="manualNama" name="manual_nama" placeholder="Contoh: TN. JOHN DOE" :required="isManualPatient"
                                    class="h-9 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-xs outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Tempat Lahir</label>
                                <input type="text" x-model="manualTempatLahir" name="manual_tempat_lahir" placeholder="Jakarta"
                                    class="h-9 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-xs outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Tanggal Lahir (DD-MM-YYYY) <span class="text-red-500">*</span></label>
                                <input type="text" x-model="manualTglLahir" name="manual_tgl_lahir" placeholder="31-12-1990" :required="isManualPatient"
                                    class="h-9 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-xs outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Jenis Kelamin</label>
                                <select x-model="manualGender" name="manual_gender" class="h-9 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-xs outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white">
                                    <option value="1">Laki-laki</option>
                                    <option value="2">Perempuan</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Alamat</label>
                                <input type="text" x-model="manualAlamat" name="manual_alamat" placeholder="Alamat Lengkap"
                                    class="h-9 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-xs outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white">
                            </div>
                        </div>

                        <!-- Hidden Inputs for form submission -->
                        <input type="hidden" name="no_rm" :value="isManualPatient ? manualRM : (selectedPatient ? selectedPatient.no_rm : '')">
                        <input type="hidden" name="nama" :value="isManualPatient ? manualNama : (selectedPatient ? selectedPatient.nama : '')">
                        <input type="hidden" name="tempat_lahir" :value="isManualPatient ? manualTempatLahir : (selectedPatient ? selectedPatient.tempat_lahir : '')">
                        <input type="hidden" name="tgl_lahir" :value="isManualPatient ? manualTglLahir : (selectedPatient ? selectedPatient.tgl_lahir : '')">
                        <input type="hidden" name="gender" :value="isManualPatient ? manualGender : (selectedPatient ? selectedPatient.gender : '')">
                        <input type="hidden" name="alamat" :value="isManualPatient ? manualAlamat : (selectedPatient ? selectedPatient.alamat : '')">
                        <input type="hidden" name="no_reg" :value="selectedPatient ? selectedPatient.no_rawat : '-'">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Dokter Pengirim <span class="text-red-500">*</span></label>
                        <select name="id_dokter" required x-model="selectedDokter" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white/90">
                            @foreach ($allDokters as $d)
                                <option value="{{ $d->id }}">{{ $d->nama }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Dokter Penanggung Jawab <span class="text-red-500">*</span></label>
                        <select name="id_dokter2" required x-model="selectedDokter2" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white/90">
                            @foreach ($allDokters as $d)
                                <option value="{{ $d->id }}">{{ $d->nama }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Ruangan <span class="text-red-500">*</span></label>
                        <select name="id_ruang" required x-model="selectedRuang" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white/90">
                            @foreach ($allRuangs as $r)
                                <option value="{{ $r->id }}">{{ $r->nama }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Petugas <span class="text-red-500">*</span></label>
                        <select name="id_petugas" required x-model="selectedPetugas" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white/90">
                            @foreach ($allPetugas as $p)
                                <option value="{{ $p->id }}">{{ $p->nama }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Perusahaan/asuransi <span class="text-red-500">*</span></label>
                        <select name="id_penjamin" required x-model="selectedPenjamin" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white/90">
                            <option value="1">UMUM</option>
                            <option value="2">BPJS</option>
                            <option value="3" x-text="selectedPatient && selectedPatient.nama_penjamin ? selectedPatient.nama_penjamin : 'ASURANSI'"></option>
                            <option value="4">LAIN-LAIN</option>
                        </select>
                    </div>

                    <div class="col-span-2 flex justify-end gap-2 mt-4">
                        <button type="button" @click="openAddModal = false" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg dark:bg-gray-800 dark:text-gray-300">Batal</button>
                        <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-brand-500 hover:bg-brand-600 rounded-lg" :disabled="!isManualPatient && !selectedPatient">Simpan</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Delete Confirmation Modal (inside x-data scope) --}}
        <div class="fixed inset-0 z-[60] flex items-center justify-center bg-black/50 p-4" x-show="showDeleteModal" x-cloak>
            <div @click.away="showDeleteModal = false" class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl dark:bg-gray-900 border border-gray-100 dark:border-gray-800 text-left">
                <div class="flex items-center gap-3 mb-4">
                    <div class="flex size-12 items-center justify-center rounded-full bg-error-50 dark:bg-error-500/10">
                        <svg class="size-6 text-error-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-gray-900 dark:text-white">Hapus Data Pemeriksaan</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Tindakan ini tidak dapat dibatalkan.</p>
                    </div>
                </div>
                <p class="text-sm text-gray-600 dark:text-gray-300 mb-1">
                    Anda akan menghapus data pemeriksaan:
                </p>
                <p class="mb-5 rounded-lg bg-gray-50 dark:bg-gray-800 px-4 py-2.5 font-mono text-sm font-semibold text-error-600 dark:text-error-400" x-text="deleteNomor"></p>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-5">
                    Semua data hasil pemeriksaan (result), biaya, dan data bridging SIMRS terkait juga akan ikut dihapus.
                </p>
                <form method="POST" :action="'/lis/periksa/' + deleteId + '/delete'">
                    @csrf
                    <div class="flex justify-end gap-2">
                        <button type="button" @click="showDeleteModal = false"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">
                            Batal
                        </button>
                        <button type="submit"
                            class="px-4 py-2 text-sm font-medium text-white bg-error-500 hover:bg-error-600 rounded-lg">
                            Ya, Hapus Sekarang
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
