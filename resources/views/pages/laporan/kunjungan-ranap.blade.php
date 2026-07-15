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
        <h2 class="text-lg font-semibold uppercase">Laporan Kunjungan Rawat Inap</h2>
        <div class="text-sm text-gray-700 mt-2 space-y-1">
            <div>Periode: <strong>{{ \Carbon\Carbon::parse($filters['from'])->format('d/m/Y') }}</strong> s.d. <strong>{{ \Carbon\Carbon::parse($filters['to'])->format('d/m/Y') }}</strong></div>
            @if($filters['kdBangsal'])
                @php $selBangsal = $bangsals->firstWhere('kd_bangsal', $filters['kdBangsal']); @endphp
                @if($selBangsal)
                    <div>Bangsal: <strong>{{ $selBangsal->nm_bangsal }}</strong></div>
                @endif
            @endif
            @if($filters['kdDokter'])
                @php $selDokter = $dokters->firstWhere('kd_dokter', $filters['kdDokter']); @endphp
                @if($selDokter)
                    <div>Dokter DPJP: <strong>{{ $selDokter->nm_dokter }}</strong></div>
                @endif
            @endif
        </div>
    </div>

    <div class="space-y-6">
        <!-- Filter Form -->
        <x-common.component-card :title="'Filter Laporan'" desc="Kriteria pencarian kunjungan rawat inap pasien." class="no-print">
            <form method="GET" action="{{ route('laporan.kunjungan-ranap') }}" class="grid grid-cols-1 gap-4 lg:grid-cols-12">
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

                <label class="lg:col-span-3">
                    <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Bangsal/Kamar</span>
                    <select name="kd_bangsal" class="h-11 w-full rounded-lg border border-gray-300 bg-white dark:bg-gray-850 px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white/90">
                        <option value="">-- Semua Bangsal --</option>
                        @foreach ($bangsals as $b)
                            <option value="{{ $b->kd_bangsal }}" @selected($filters['kdBangsal'] === $b->kd_bangsal)>
                                {{ $b->nm_bangsal }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="lg:col-span-3">
                    <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Dokter DPJP</span>
                    <select name="kd_dokter" class="h-11 w-full rounded-lg border border-gray-300 bg-white dark:bg-gray-850 px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white/90">
                        <option value="">-- Semua Dokter --</option>
                        @foreach ($dokters as $d)
                            <option value="{{ $d->kd_dokter }}" @selected($filters['kdDokter'] === $d->kd_dokter)>
                                {{ $d->nm_dokter }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="lg:col-span-3">
                    <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Cara Bayar</span>
                    <select name="kd_pj" class="h-11 w-full rounded-lg border border-gray-300 bg-white dark:bg-gray-850 px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white/90">
                        <option value="">-- Semua Cara Bayar --</option>
                        @foreach ($penjabs as $pj)
                            <option value="{{ $pj->kd_pj }}" @selected($filters['kdPj'] === $pj->kd_pj)>
                                {{ $pj->png_jawab }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="lg:col-span-3">
                    <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Status Daftar</span>
                    <select name="status_daftar" class="h-11 w-full rounded-lg border border-gray-300 bg-white dark:bg-gray-850 px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white/90">
                        <option value="Semua" @selected($filters['statusDaftar'] === 'Semua')>Semua</option>
                        <option value="Baru" @selected($filters['statusDaftar'] === 'Baru')>Baru</option>
                        <option value="Lama" @selected($filters['statusDaftar'] === 'Lama')>Lama</option>
                    </select>
                </label>

                <label class="lg:col-span-2">
                    <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Tampilkan</span>
                    <select name="show_all" class="h-11 w-full rounded-lg border border-gray-300 bg-white dark:bg-gray-850 px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white/90">
                        <option value="" @selected(!$filters['showAll'])>50 Baris</option>
                        <option value="1" @selected($filters['showAll'])>Semua</option>
                    </select>
                </label>

                <div class="flex items-end gap-2 lg:col-span-4">
                    <button type="submit"
                        class="h-11 flex-1 rounded-lg bg-brand-500 px-4 text-sm font-medium text-white hover:bg-brand-600">
                        Terapkan
                    </button>
                    <a href="{{ route('laporan.kunjungan-ranap', array_merge(request()->query(), ['export' => 'excel'])) }}"
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
                    <a href="{{ route('laporan.kunjungan-ranap') }}"
                        class="inline-flex h-11 items-center rounded-lg border border-gray-300 px-3 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.05]">
                        Reset
                    </a>
                </div>
            </form>
            @error('to')
                <p class="mt-2 text-sm text-error-500">{{ $message }}</p>
            @enderror
        </x-common.component-card>

        <!-- Report Results -->
        <x-common.component-card :title="'Daftar Kunjungan'">


            @if ($connectionError)
                <div class="rounded-lg border border-warning-200 bg-warning-50 p-4 text-sm text-warning-700 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-400">
                    {{ $connectionError }}
                </div>
            @else
                <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800 print:border-none print:rounded-none">
                    <div class="max-w-full overflow-x-auto sticky-table-wrapper custom-scrollbar print:overflow-visible print:max-h-none">
                        <table class="w-full min-w-[1400px] print:min-w-full">
                            <thead class="bg-gray-50 dark:bg-gray-900 print:bg-gray-100">
                                <tr class="border-b border-gray-200 dark:border-gray-800 print:border-black">
                                    <th rowspan="2" class="px-3 py-3 text-center text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 print:text-black border-r border-gray-200 dark:border-gray-800 print:border-black">No.</th>
                                    <th colspan="2" class="px-3 py-2 text-center text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 print:text-black border-b border-r border-gray-200 dark:border-gray-800 print:border-black">No. RKM Medis</th>
                                    <th rowspan="2" class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 print:text-black border-r border-gray-200 dark:border-gray-800 print:border-black">Nama Pasien</th>
                                    <th colspan="2" class="px-3 py-2 text-center text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 print:text-black border-b border-r border-gray-200 dark:border-gray-800 print:border-black">Umur</th>
                                    <th rowspan="2" class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 print:text-black border-r border-gray-200 dark:border-gray-800 print:border-black">Alamat PJ</th>
                                    <th rowspan="2" class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 print:text-black border-r border-gray-200 dark:border-gray-800 print:border-black">Ruang/Kamar</th>
                                    <th rowspan="2" class="px-3 py-3 text-center text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 print:text-black border-r border-gray-200 dark:border-gray-800 print:border-black">Stts Pulang</th>
                                    <th rowspan="2" class="px-3 py-3 text-center text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 print:text-black border-r border-gray-200 dark:border-gray-800 print:border-black">Tgl Masuk</th>
                                    <th rowspan="2" class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 print:text-black">Dokter DPJP</th>
                                </tr>
                                <tr class="border-b border-gray-200 dark:border-gray-800 print:border-black bg-gray-50/50 dark:bg-gray-900/50 print:bg-gray-50">
                                    <th class="px-3 py-2 text-center text-xs font-medium uppercase text-gray-500 dark:text-gray-400 print:text-black border-r border-gray-200 dark:border-gray-800 print:border-black">Lama</th>
                                    <th class="px-3 py-2 text-center text-xs font-medium uppercase text-gray-500 dark:text-gray-400 print:text-black border-r border-gray-200 dark:border-gray-800 print:border-black">Baru</th>
                                    <th class="px-3 py-2 text-center text-xs font-medium uppercase text-gray-500 dark:text-gray-400 print:text-black border-r border-gray-200 dark:border-gray-800 print:border-black">L</th>
                                    <th class="px-3 py-2 text-center text-xs font-medium uppercase text-gray-500 dark:text-gray-400 print:text-black border-r border-gray-200 dark:border-gray-800 print:border-black">P</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800 print:divide-y print:divide-black">
                                @php $start = ($registrations->currentPage() - 1) * $registrations->perPage() + 1; @endphp
                                @forelse ($registrations as $reg)
                                    <tr class="print:border-b print:border-gray-300">
                                        <td class="whitespace-nowrap px-3 py-3 text-center text-sm text-gray-700 dark:text-gray-300 print:text-black border-r border-gray-100 dark:border-gray-800 print:border-black">
                                            {{ $start++ }}
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-3 text-center text-sm font-medium text-gray-950 dark:text-white print:text-black border-r border-gray-100 dark:border-gray-800 print:border-black">
                                            {{ $reg->stts_daftar === 'Lama' ? $reg->no_rkm_medis : '' }}
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-3 text-center text-sm font-medium text-gray-950 dark:text-white print:text-black border-r border-gray-100 dark:border-gray-800 print:border-black">
                                            {{ $reg->stts_daftar === 'Baru' ? $reg->no_rkm_medis : '' }}
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-955 dark:text-white print:text-black font-medium border-r border-gray-100 dark:border-gray-800 print:border-black">
                                            {{ $reg->nm_pasien }}
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-3 text-center text-sm text-gray-700 dark:text-gray-300 print:text-black border-r border-gray-100 dark:border-gray-800 print:border-black">
                                            {{ $reg->jk === 'L' ? ($reg->umurdaftar . ' ' . $reg->sttsumur) : '' }}
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-3 text-center text-sm text-gray-700 dark:text-gray-300 print:text-black border-r border-gray-100 dark:border-gray-800 print:border-black">
                                            {{ $reg->jk === 'P' ? ($reg->umurdaftar . ' ' . $reg->sttsumur) : '' }}
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-650 dark:text-gray-300 print:text-black border-r border-gray-100 dark:border-gray-800 print:border-black font-normal">
                                            {{ $reg->alamat }}, {{ $reg->nm_kel }}, {{ $reg->nm_kec }}, {{ $reg->nm_kab }}
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300 print:text-black border-r border-gray-100 dark:border-gray-800 print:border-black">
                                            {{ $reg->kd_kamar }} <span class="block text-xs text-gray-400 print:inline print:text-black print:text-[8px] print:ml-1">{{ $reg->nm_bangsal }}</span>
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-3 text-center text-sm text-gray-700 dark:text-gray-300 print:text-black border-r border-gray-100 dark:border-gray-800 print:border-black">
                                            {{ $reg->stts_pulang }}
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-3 text-center text-sm text-gray-600 dark:text-gray-300 print:text-black border-r border-gray-100 dark:border-gray-800 print:border-black">
                                            {{ \Illuminate\Support\Carbon::parse($reg->tgl_masuk)->format('d/m/Y') }}
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300 print:text-black">
                                            {{ $reg->dokter_dpjp }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="11" class="px-5 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                                            Tidak ada data kunjungan rawat inap yang ditemukan.
                                        </td>
                                    </tr>
                                @endforelse
                                
                                <!-- Rekap Totals Row (Visible if data exists) -->
                                @if($registrations->total() > 0)
                                    <tr class="bg-gray-100 dark:bg-gray-850 font-semibold text-gray-900 dark:text-white print:text-black print:bg-gray-100 border-t-2 border-black">
                                        <td class="px-3 py-3 text-center border-r border-gray-200 dark:border-gray-800 print:border-black">
                                            &gt;&gt;
                                        </td>
                                        <td class="px-3 py-3 text-center border-r border-gray-200 dark:border-gray-800 print:border-black">
                                            {{ number_format($stats['lama']) }}
                                        </td>
                                        <td class="px-3 py-3 text-center border-r border-gray-200 dark:border-gray-800 print:border-black">
                                            {{ number_format($stats['baru']) }}
                                        </td>
                                        <td class="px-4 py-3 border-r border-gray-200 dark:border-gray-800 print:border-black">
                                            Total Kunjungan: {{ number_format($stats['lama'] + $stats['baru']) }}
                                        </td>
                                        <td class="px-3 py-3 text-center border-r border-gray-200 dark:border-gray-800 print:border-black">
                                            L: {{ number_format($stats['laki']) }}
                                        </td>
                                        <td class="px-3 py-3 text-center border-r border-gray-200 dark:border-gray-800 print:border-black">
                                            P: {{ number_format($stats['perempuan']) }}
                                        </td>
                                        <td colspan="5" class="px-4 py-3">
                                            Total L/P: {{ number_format($stats['laki'] + $stats['perempuan']) }}
                                        </td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="mt-4 pagination-container no-print">
                    {{ $registrations->onEachSide(1)->links() }}
                </div>
            @endif
        </x-common.component-card>
    </div>
@endsection
