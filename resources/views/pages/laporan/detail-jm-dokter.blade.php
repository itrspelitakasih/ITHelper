@extends('layouts.app')

@section('content')
    <style>
        @media print {
            /* Hide sidebar, backdrop, header, filters, pagination, and action buttons */
            #sidebar, 
            header, 
            .no-print, 
            form, 
            .pagination-container,
            button,
            a,
            .breadcrumb,
            .preloader-wrapper {
                display: none !important;
            }
            
            /* Reset container margin for print */
            body {
                background-color: white !important;
                color: black !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            
            .min-h-screen, 
            .flex, 
            .xl:ml-\[290px\], 
            .xl:ml-\[90px\],
            .p-4, 
            .md:p-6 {
                margin-left: 0 !important;
                margin-right: 0 !important;
                margin-top: 0 !important;
                padding: 0 !important;
                display: block !important;
            }

            .overflow-x-auto {
                overflow: visible !important;
            }
            
            /* Table styling for print */
            table {
                width: 100% !important;
                border-collapse: collapse !important;
                font-size: 9px !important;
                color: black !important;
                margin-top: 15px;
            }
            
            th, td {
                border: 1px solid #000 !important;
                padding: 4px 5px !important;
                text-align: left !important;
            }
            
            th {
                background-color: #f3f4f6 !important;
                font-weight: bold !important;
            }

            /* Prevent page breaks inside rows */
            tr {
                page-break-inside: avoid !important;
            }
            .print\:max-h-none {
                max-height: none !important;
                overflow: visible !important;
            }
        }

        /* Sticky Header for Scrollable Table */
        .sticky-table-wrapper {
            max-height: 600px;
            overflow-y: auto;
        }
        .sticky-table-wrapper thead {
            position: sticky;
            top: 0;
            z-index: 20;
        }
        .sticky-table-wrapper thead th {
            background-color: #f9fafb !important;
        }
        .dark .sticky-table-wrapper thead th {
            background-color: #111827 !important;
        }
    </style>

    <div class="no-print">
        <x-common.page-breadcrumb :pageTitle="$title" />
    </div>

    <!-- Print Header -->
    <div class="hidden print:block mb-6 border-b-2 border-black pb-4 text-center">
        <h1 class="text-2xl font-bold uppercase">{{ $appName }}</h1>
        <h2 class="text-lg font-semibold uppercase">Laporan Tindakan Dokter</h2>
        <div class="text-sm text-gray-700 mt-2 space-y-1">
            <div>Periode: <strong>{{ \Carbon\Carbon::parse($filters['from'])->format('d/m/Y') }}</strong> s.d. <strong>{{ \Carbon\Carbon::parse($filters['to'])->format('d/m/Y') }}</strong></div>
            @if($filters['kdPoli'])
                @php $selPoli = $polis->firstWhere('kd_poli', $filters['kdPoli']); @endphp
                @if($selPoli)
                    <div>Poliklinik: <strong>{{ $selPoli->nm_poli }}</strong></div>
                @endif
            @endif
            @if($filters['kdDokter'])
                @php $selDokter = $dokters->firstWhere('kd_dokter', $filters['kdDokter']); @endphp
                @if($selDokter)
                    <div>Dokter: <strong>{{ $selDokter->nm_dokter }}</strong></div>
                @endif
            @endif
        </div>
    </div>

    <div class="space-y-6">
        <!-- Filter Form -->
        <x-common.component-card :title="'Filter Laporan'" desc="Kriteria pencarian tindakan dokter." class="no-print">
            <form method="GET" action="{{ route('laporan.detail-jm-dokter') }}" class="grid grid-cols-1 gap-4 lg:grid-cols-12">
                <label class="lg:col-span-2">
                    <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Dari Tanggal</span>
                    <input type="date" name="from" value="{{ $filters['from'] }}"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white/90">
                </label>

                <label class="lg:col-span-2">
                    <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Sampai Tanggal</span>
                    <input type="date" name="to" value="{{ $filters['to'] }}"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white/90">
                </label>

                <label class="lg:col-span-3">
                    <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Dokter</span>
                    <select name="kd_dokter" class="h-11 w-full rounded-lg border border-gray-300 bg-white dark:bg-gray-850 px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white/90">
                        <option value="">-- Semua Dokter --</option>
                        @foreach ($dokters as $d)
                            <option value="{{ $d->kd_dokter }}" @selected($filters['kdDokter'] === $d->kd_dokter)>
                                {{ $d->nm_dokter }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="lg:col-span-2">
                    <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Poliklinik (Ralan)</span>
                    <select name="kd_poli" class="h-11 w-full rounded-lg border border-gray-300 bg-white dark:bg-gray-850 px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white/90">
                        <option value="">-- Semua Poli --</option>
                        @foreach ($polis as $p)
                            <option value="{{ $p->kd_poli }}" @selected($filters['kdPoli'] === $p->kd_poli)>
                                {{ $p->nm_poli }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="lg:col-span-3">
                    <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Pencarian</span>
                    <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="Nama, No.RM, Tindakan..."
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white/90">
                </label>

                <div class="flex items-end gap-2 lg:col-span-12">
                    <button type="submit"
                        class="h-11 flex-1 rounded-lg bg-brand-500 px-4 text-sm font-medium text-white hover:bg-brand-600">
                        Terapkan
                    </button>
                    <a href="{{ route('laporan.detail-jm-dokter', array_merge(request()->query(), ['export' => 'excel'])) }}"
                        class="inline-flex h-11 items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 text-sm font-medium text-brand-500 hover:bg-gray-50 dark:border-gray-700 dark:text-brand-400 dark:hover:bg-white/[0.05] no-print">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-brand-500 dark:text-brand-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Excel
                    </a>
                    <button type="button" onclick="window.print()"
                        class="inline-flex h-11 items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 text-sm font-medium text-success-700 hover:bg-gray-50 dark:border-gray-700 dark:text-success-400 dark:hover:bg-white/[0.05] no-print">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-success-700 dark:text-success-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                        </svg>
                        Cetak
                    </button>
                    <a href="{{ route('laporan.detail-jm-dokter') }}"
                        class="inline-flex h-11 items-center rounded-lg border border-gray-300 px-3 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.05]">
                        Reset
                    </a>
                </div>
            </form>
        </x-common.component-card>

        <!-- Connection Error Alert -->
        @if ($connectionError)
            <div class="rounded-lg border border-error-200 bg-error-50 p-4 text-sm text-error-700 dark:border-error-800/60 dark:bg-error-500/10 dark:text-error-400">
                {{ $connectionError }}
            </div>
        @else
            <!-- Data Card -->
            <x-common.component-card :title="'Data Tindakan Dokter'" desc="Menampilkan rincian tindakan dokter.">
                <div class="relative">
                    <div class="sticky-table-wrapper print:max-h-none border border-gray-200 dark:border-gray-800 rounded-xl overflow-x-auto">
                        <table class="w-full min-w-[1000px] print:min-w-full divide-y divide-gray-100 dark:divide-gray-800">
                            <thead class="bg-gray-50 dark:bg-gray-900 print:bg-gray-100">
                                <tr class="border-b border-gray-200 dark:border-gray-800 print:border-black">
                                    <th class="px-3 py-3 text-center text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 print:text-black border-r border-gray-200 dark:border-gray-800 print:border-black">No.</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 print:text-black border-r border-gray-200 dark:border-gray-800 print:border-black">Tanggal</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 print:text-black border-r border-gray-200 dark:border-gray-800 print:border-black">No. Rawat</th>
                                    <th class="px-3 py-3 text-center text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 print:text-black border-r border-gray-200 dark:border-gray-800 print:border-black">No. RM</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 print:text-black border-r border-gray-200 dark:border-gray-800 print:border-black">Nama Pasien</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 print:text-black border-r border-gray-200 dark:border-gray-800 print:border-black">Tindakan/Perawatan</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 print:text-black">Poli/Ruangan</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800 print:divide-y print:divide-black">
                                @php $start = ($transactions->currentPage() - 1) * $transactions->perPage() + 1; @endphp
                                @forelse ($transactions as $t)
                                    <tr class="print:border-b print:border-gray-300">
                                        <td class="whitespace-nowrap px-3 py-3 text-center text-sm text-gray-700 dark:text-gray-300 print:text-black border-r border-gray-100 dark:border-gray-800 print:border-black">
                                            {{ $start++ }}
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600 dark:text-gray-400 print:text-black border-r border-gray-100 dark:border-gray-800 print:border-black">
                                            {{ \Illuminate\Support\Carbon::parse($t->tgl_perawatan)->format('d/m/Y') }} <span class="text-xs text-gray-400 block">{{ $t->jam_rawat }}</span>
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700 dark:text-gray-300 print:text-black border-r border-gray-100 dark:border-gray-800 print:border-black">
                                            {{ $t->no_rawat }}
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-3 text-center text-sm text-gray-700 dark:text-gray-300 print:text-black border-r border-gray-100 dark:border-gray-800 print:border-black font-medium">
                                            {{ $t->no_rkm_medis }}
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-955 dark:text-white print:text-black font-medium border-r border-gray-100 dark:border-gray-800 print:border-black">
                                            {{ $t->nm_pasien }}
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300 print:text-black border-r border-gray-100 dark:border-gray-800 print:border-black">
                                            {{ $t->nm_perawatan }}
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400 print:text-black">
                                            {{ $t->ruangan }} <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold {{ $t->status_lanjut === 'Ralan' ? 'bg-blue-50 text-blue-700 dark:bg-blue-500/15 dark:text-blue-400' : 'bg-purple-50 text-purple-700 dark:bg-purple-500/15 dark:text-purple-400' }}">{{ $t->status_lanjut }}</span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="px-5 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                                            Tidak ada rincian jasa medis dokter yang ditemukan.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="mt-4 pagination-container no-print">
                    {{ $transactions->onEachSide(1)->links() }}
                </div>
            </x-common.component-card>
        @endif
    </div>
@endsection
