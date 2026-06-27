@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb :pageTitle="$title" />

    <div class="space-y-6">
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
        <x-common.component-card :title="'Filter Pencarian'" desc="Cari data permintaan registrasi SIMRS berdasarkan tanggal permintaan dan kata kunci.">
            <form method="GET" action="{{ route('lis.periksa.simrs') }}" class="grid grid-cols-1 gap-4 lg:grid-cols-12">
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
                        placeholder="No. Order/Reg, RM, Nama..."
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm text-gray-800 outline-none placeholder:text-gray-400 focus:border-brand-500 dark:border-gray-700 dark:text-white/90">
                </label>

                <div class="flex items-end gap-2 lg:col-span-2">
                    <button type="submit"
                        class="h-11 flex-1 rounded-lg bg-brand-500 px-4 text-sm font-medium text-white hover:bg-brand-600">
                        Terapkan
                    </button>
                    <a href="{{ route('lis.periksa.simrs') }}"
                        class="inline-flex h-11 items-center rounded-lg border border-gray-300 px-3 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.05]">
                        Reset
                    </a>
                </div>
            </form>
        </x-common.component-card>

        <!-- Status Tabs Navigation -->
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
                class="rounded-lg border px-4 py-2 text-sm font-medium border-brand-500 bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400">
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

        @if ($connectionError)
            <x-ui.alert variant="warning" title="Koneksi SIMRS Bermasalah" :message="$connectionError" class="mb-6" />
        @endif

        <!-- List Card -->
        <x-common.component-card :title="'Daftar Pemeriksaan SIMRS (Bridging LIS)'">
            <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800">
                <div class="max-w-full overflow-x-auto custom-scrollbar">
                    <table class="w-full min-w-[1000px] border-collapse">
                        <thead class="bg-gray-50 dark:bg-gray-900">
                            <tr class="border-b border-gray-200 dark:border-gray-800">
                                <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">No. Lab / Order</th>
                                <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Tanggal Permintaan</th>
                                <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">No. RM</th>
                                <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Nama Pasien</th>
                                <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">J.K.</th>
                                <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Dokter Pengirim</th>
                                <th class="px-5 py-3 text-center text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse ($rows as $row)
                                <tr class="hover:bg-gray-50/30 dark:hover:bg-gray-800/10">
                                    <td class="whitespace-nowrap px-5 py-4 text-sm font-semibold text-brand-600 dark:text-brand-400">
                                        {{ $row->noorder }}
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-4 text-sm text-gray-600 dark:text-gray-300">
                                        {{ \Illuminate\Support\Carbon::parse($row->tgl_permintaan)->format('d/m/Y') }}
                                        <span class="block text-xs text-gray-405">{{ substr($row->jam_permintaan, 0, 5) }}</span>
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-4 text-sm font-medium text-gray-700 dark:text-gray-300">
                                        {{ $row->no_rkm_medis }}
                                    </td>
                                    <td class="px-5 py-4 text-sm text-gray-900 dark:text-white font-semibold">
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
                                    <td class="px-5 py-4 text-sm text-gray-600 dark:text-gray-400">
                                        {{ $row->dokter_perujuk }}
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-4 text-center text-sm">
                                        <a href="{{ route('lis.periksa.tolis', $row->noorder) }}"
                                            class="inline-flex items-center gap-1.5 rounded-lg bg-warning-500 px-3.5 py-2 text-xs font-semibold text-white hover:bg-warning-600 transition shadow-sm">
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
                                        Tidak ada data permintaan SIMRS yang ditemukan.
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
    </div>
@endsection
