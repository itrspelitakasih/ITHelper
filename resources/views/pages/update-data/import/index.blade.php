@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb :pageTitle="$title" />

    <div class="space-y-6">
        <!-- Control Panel (Combined Import & Filter) -->
        <x-common.component-card :title="'Panel Aksi & Filter ' . $mapping['title']" desc="Impor file CSV tarif baru dan saring data tarif yang aktif saat ini.">
            
            @if (session('success'))
                <div class="mb-4 rounded-lg bg-success-50 p-4 text-sm text-success-700 dark:bg-success-500/10 dark:text-success-400">
                    {{ session('success') }}
                </div>
            @endif

            @if (session('error'))
                <div class="mb-4 rounded-lg bg-error-50 p-4 text-sm text-error-700 dark:bg-error-500/10 dark:text-error-400">
                    {{ session('error') }}
                </div>
            @endif

            <div class="flex flex-col gap-5">
                <!-- Row 1: Compact Import Form -->
                <form method="POST" action="{{ route('update-data.import.store', ['type' => $type]) }}" enctype="multipart/form-data" 
                    onsubmit="return confirm('Apakah Anda yakin ingin memperbaharui data tarif dengan file yang diunggah?')"
                    class="flex flex-wrap items-center gap-4 bg-gray-50 dark:bg-gray-900/50 p-4 rounded-xl border border-gray-150 dark:border-gray-800">
                    @csrf
                    <div class="flex-1 min-w-[280px]">
                        <span class="mb-1.5 block text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Upload File CSV / Excel</span>
                        <input type="file" name="file" accept=".csv,.txt,.xlsx,.xls" required
                            class="w-full text-xs text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100 dark:file:bg-brand-500/10 dark:file:text-brand-400" />
                        @error('file')
                            <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex items-center gap-4 text-xs font-medium text-gray-700 dark:text-gray-400">
                        <label class="flex items-center gap-1.5 cursor-pointer">
                            <input type="checkbox" name="skip_header" value="1" checked
                                class="rounded border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-900">
                            <span>Abaikan Header</span>
                        </label>

                        <label class="flex items-center gap-1.5 cursor-pointer">
                            <input type="checkbox" name="on_duplicate_update" value="1" checked
                                class="rounded border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-900">
                            <span>Update Duplikat (UPSERT)</span>
                        </label>
                    </div>

                    <div>
                        <button type="submit"
                            class="h-9 px-4 rounded-lg bg-brand-500 text-xs font-medium text-white hover:bg-brand-600 transition-colors">
                            Import CSV
                        </button>
                    </div>
                </form>

                <!-- Row 2: Compact Filter Form -->
                <form method="GET" action="{{ route('update-data.import.index', ['type' => $type]) }}" class="flex flex-wrap items-end gap-3">
                    
                    <!-- Search -->
                    <div class="min-w-[200px] flex-1">
                        <span class="mb-1 block text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Kata Kunci</span>
                        <input type="search" name="search" value="{{ $filters['search'] }}"
                            placeholder="Cari kode atau nama..."
                            class="h-9 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none placeholder:text-gray-400 focus:border-brand-500 dark:border-gray-700 dark:text-white/90">
                    </div>

                    <!-- Perusahaan -->
                    <div class="w-52">
                        <span class="mb-1 block text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Perusahaan</span>
                        <select name="kd_pj"
                            class="h-9 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                            <option value="">Semua Perusahaan</option>
                            @foreach ($penjabs as $pj)
                                <option value="{{ $pj->kd_pj }}" {{ $filters['kd_pj'] === $pj->kd_pj ? 'selected' : '' }}>
                                    {{ $pj->png_jawab }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Kelas -->
                    @if ($kelases->isNotEmpty())
                        <div class="w-40">
                            <span class="mb-1 block text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Kelas</span>
                            <select name="kelas"
                                class="h-9 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                <option value="">Semua Kelas</option>
                                @foreach ($kelases as $kls)
                                    <option value="{{ $kls }}" {{ $filters['kelas'] === $kls ? 'selected' : '' }}>
                                        {{ $kls }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <!-- Actions -->
                    <div class="flex items-center gap-2">
                        <button type="submit"
                            class="h-9 rounded-lg bg-brand-500 px-4 text-xs font-medium text-white hover:bg-brand-600 transition-colors">
                            Cari
                        </button>
                        <a href="{{ route('update-data.import.index', ['type' => $type]) }}"
                            class="inline-flex h-9 items-center justify-center rounded-lg border border-gray-300 px-3 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.05]">
                            Reset
                        </a>
                    </div>
                </form>
            </div>
        </x-common.component-card>

        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-6">
                <!-- Card Header with Backup Button -->
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                            {{ $mapping['title'] }} saat ini
                        </h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            Menampilkan data tarif aktif sesuai dengan filter yang dipilih.
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <!-- Backup Button -->
                        <a href="{{ route('update-data.import.export', ['type' => $type] + request()->query()) }}"
                            class="inline-flex h-10 items-center justify-center rounded-lg bg-indigo-500 px-4 text-sm font-medium text-white hover:bg-indigo-600 transition-colors gap-2">
                            <!-- Download Icon -->
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                            </svg>
                            Backup / Export Tabel
                        </a>
                    </div>
                </div>

                @if ($connectionError)
                    <div class="rounded-lg border border-warning-200 bg-warning-50 p-4 text-sm text-warning-700 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-400">
                        {{ $connectionError }}
                    </div>
                @else
                    <!-- Scrollable Table Container -->
                    <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800">
                        <div class="max-h-[600px] overflow-y-auto custom-scrollbar">
                            <table class="w-full text-left border-collapse">
                                <thead class="bg-gray-50 dark:bg-gray-900 sticky top-0 z-10 shadow-[0_1px_0_0_rgba(229,231,235,1)] dark:shadow-[0_1px_0_0_rgba(31,41,55,1)]">
                                    <tr>
                                        <th class="px-5 py-3 text-xs font-medium uppercase text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-900">Nama / Kode Perawatan</th>
                                        
                                        @if ($type === 'ralan' || $type === 'ranap')
                                            <th class="px-5 py-3 text-xs font-medium uppercase text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-900">Jasa Sarana</th>
                                            <th class="px-5 py-3 text-xs font-medium uppercase text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-900">BHP</th>
                                            <th class="px-5 py-3 text-xs font-medium uppercase text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-900">J.M. Dokter</th>
                                            <th class="px-5 py-3 text-xs font-medium uppercase text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-900">J.M. Perawat</th>
                                            <th class="px-5 py-3 text-xs font-medium uppercase text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-900">Total Tarif (Dr/Pr/DrPr)</th>
                                        @else
                                            <th class="px-5 py-3 text-xs font-medium uppercase text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-900">Jasa RS</th>
                                            <th class="px-5 py-3 text-xs font-medium uppercase text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-900">BHP</th>
                                            <th class="px-5 py-3 text-xs font-medium uppercase text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-900">J.M. Dokter</th>
                                            <th class="px-5 py-3 text-xs font-medium uppercase text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-900">J.M. Petugas</th>
                                            <th class="px-5 py-3 text-xs font-medium uppercase text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-900">Total Tarif</th>
                                        @endif

                                        <th class="px-5 py-3 text-xs font-medium uppercase text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-900">Cara Bayar</th>
                                        
                                        @if ($type === 'ralan')
                                            <th class="px-5 py-3 text-xs font-medium uppercase text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-900">Poliklinik</th>
                                        @elseif ($type === 'ranap')
                                            <th class="px-5 py-3 text-xs font-medium uppercase text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-900">Kamar</th>
                                            <th class="px-5 py-3 text-xs font-medium uppercase text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-900">Kelas</th>
                                        @elseif ($type === 'lab' || $type === 'radiology')
                                            <th class="px-5 py-3 text-xs font-medium uppercase text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-900">Kelas</th>
                                        @endif
                                        
                                        @if ($type === 'lab')
                                            <th class="px-5 py-3 text-xs font-medium uppercase text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-900">Kategori</th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                    @forelse ($records as $row)
                                        <tr class="hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition-colors">
                                            <td class="px-5 py-3 text-sm">
                                                <span class="block text-xs font-semibold text-brand-600 dark:text-brand-400">
                                                    {{ $row->kd_jenis_prw }}
                                                </span>
                                                <span class="block text-gray-900 dark:text-white font-medium max-w-[280px] truncate" title="{{ $row->nm_perawatan }}">
                                                    {{ $row->nm_perawatan }}
                                                </span>
                                            </td>

                                            @if ($type === 'ralan' || $type === 'ranap')
                                                <td class="whitespace-nowrap px-5 py-3 text-sm text-gray-600 dark:text-gray-300">
                                                    Rp {{ number_format($row->material) }}
                                                </td>
                                                <td class="whitespace-nowrap px-5 py-3 text-sm text-gray-600 dark:text-gray-300">
                                                    Rp {{ number_format($row->bhp) }}
                                                </td>
                                                <td class="whitespace-nowrap px-5 py-3 text-sm text-gray-600 dark:text-gray-300">
                                                    Rp {{ number_format($row->tarif_tindakandr) }}
                                                </td>
                                                <td class="whitespace-nowrap px-5 py-3 text-sm text-gray-600 dark:text-gray-300">
                                                    Rp {{ number_format($row->tarif_tindakanpr) }}
                                                </td>
                                                <td class="whitespace-nowrap px-5 py-3 text-sm font-medium text-gray-900 dark:text-white">
                                                    <span class="text-xs text-gray-500 block">Dr: Rp {{ number_format($row->total_byrdr) }}</span>
                                                    <span class="text-xs text-gray-500 block">Pr: Rp {{ number_format($row->total_byrpr) }}</span>
                                                    <span class="font-semibold block text-brand-600 dark:text-brand-400">Ttl: Rp {{ number_format($row->total_byrdrpr) }}</span>
                                                </td>
                                            @else
                                                <td class="whitespace-nowrap px-5 py-3 text-sm text-gray-600 dark:text-gray-300">
                                                    Rp {{ number_format($row->bagian_rs) }}
                                                </td>
                                                <td class="whitespace-nowrap px-5 py-3 text-sm text-gray-600 dark:text-gray-300">
                                                    Rp {{ number_format($row->bhp) }}
                                                </td>
                                                <td class="whitespace-nowrap px-5 py-3 text-sm text-gray-600 dark:text-gray-300">
                                                    Rp {{ number_format($row->tarif_tindakan_dokter) }}
                                                </td>
                                                <td class="whitespace-nowrap px-5 py-3 text-sm text-gray-600 dark:text-gray-300">
                                                    Rp {{ number_format($row->tarif_tindakan_petugas) }}
                                                </td>
                                                <td class="whitespace-nowrap px-5 py-3 text-sm font-semibold text-brand-600 dark:text-brand-400">
                                                    Rp {{ number_format($row->total_byr) }}
                                                </td>
                                            @endif

                                            <td class="px-5 py-3 text-sm text-gray-700 dark:text-gray-300">
                                                {{ $row->png_jawab ?: $row->kd_pj }}
                                            </td>

                                            @if ($type === 'ralan')
                                                <td class="px-5 py-3 text-sm text-gray-700 dark:text-gray-300">
                                                    {{ $row->nm_poli ?: $row->kd_poli }}
                                                </td>
                                            @elseif ($type === 'ranap')
                                                <td class="px-5 py-3 text-sm text-gray-700 dark:text-gray-300">
                                                    {{ $row->nm_bangsal ?: $row->kd_bangsal }}
                                                </td>
                                                <td class="whitespace-nowrap px-5 py-3 text-sm text-gray-700 dark:text-gray-300">
                                                    {{ $row->kelas }}
                                                </td>
                                            @elseif ($type === 'lab' || $type === 'radiology')
                                                <td class="whitespace-nowrap px-5 py-3 text-sm text-gray-700 dark:text-gray-300">
                                                    {{ $row->kelas }}
                                                </td>
                                            @endif

                                            @if ($type === 'lab')
                                                <td class="whitespace-nowrap px-5 py-3 text-sm text-gray-700 dark:text-gray-300">
                                                    <span class="inline-flex rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-semibold text-blue-700 dark:bg-blue-500/15 dark:text-blue-400">
                                                        {{ $row->kategori }}
                                                    </span>
                                                </td>
                                            @endif
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="15" class="px-5 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                                                Tidak ada data tarif yang ditemukan.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Pagination -->
                    @if ($records instanceof \Illuminate\Pagination\LengthAwarePaginator)
                        <div class="mt-6">
                            {{ $records->links() }}
                        </div>
                    @endif
                @endif
            </div>
    </div>
@endsection
