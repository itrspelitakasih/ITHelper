@extends('layouts.app')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-2.5">
            <a href="{{ route('lis.referensi.index', ['tab' => 'paket']) }}" class="inline-flex h-9 items-center justify-center rounded-lg border border-gray-300 bg-white px-4 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-950 dark:text-gray-300 dark:hover:bg-white/[0.05]">
                <svg class="mr-1.5 size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                Kembali
            </a>
            <h2 class="text-xl font-bold text-gray-900 dark:text-white">Detail Paket LIS: {{ $paket->nama }}</h2>
        </div>
    </div>

    @if (session('success'))
        <div class="mb-6 rounded-lg border border-success-200 bg-success-50 p-4 text-sm text-success-700 dark:border-success-500/30 dark:bg-success-500/10 dark:text-success-400">
            {{ session('success') }}
        </div>
    @endif

    <div class="space-y-6">
        <x-common.component-card :title="'Daftar Parameter di dalam Paket'">
            <!-- Action buttons -->
            <div class="mb-4 flex flex-wrap gap-2">
                <button type="button" @click="$dispatch('open-add-param-modal')"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-brand-500 hover:bg-brand-600 px-4 py-2 text-xs font-semibold text-white shadow-sm transition">
                    + Tambah Parameter
                </button>
            </div>

            <!-- Table of Package Parameters -->
            <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800">
                <div class="max-w-full overflow-x-auto custom-scrollbar">
                    <table class="w-full min-w-[700px] border-collapse text-left">
                        <thead class="bg-gray-50 dark:bg-gray-900">
                            <tr class="border-b border-gray-200 dark:border-gray-800">
                                <th class="px-5 py-3 text-xs font-medium uppercase text-gray-500">Nama Pemeriksaan</th>
                                <th class="px-5 py-3 text-xs font-medium uppercase text-gray-500 w-32">LIS Code</th>
                                <th class="px-5 py-3 text-xs font-medium uppercase text-gray-500 w-24">Satuan</th>
                                <th class="px-5 py-3 text-xs font-medium uppercase text-gray-500 w-32">Metoda</th>
                                <th class="px-5 py-3 text-xs font-medium uppercase text-gray-500">Grup 1 (Utama)</th>
                                <th class="px-5 py-3 text-xs font-medium uppercase text-gray-500 w-32">Alat</th>
                                <th class="px-5 py-3 text-center text-xs font-medium uppercase text-gray-500 w-24">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse ($detail as $row)
                                <tr class="hover:bg-gray-50/20 dark:hover:bg-gray-800/10">
                                    <td class="px-5 py-4 font-semibold text-gray-900 dark:text-white">{{ $row->nama }}</td>
                                    <td class="px-5 py-4 text-sm font-semibold text-brand-600 dark:text-brand-400 font-mono">{{ $row->lis }}</td>
                                    <td class="px-5 py-4 text-sm text-gray-700 dark:text-gray-300 font-mono">{{ $row->satuan ?: '-' }}</td>
                                    <td class="px-5 py-4 text-sm text-gray-600 dark:text-gray-400">{{ $row->metoda ?: '-' }}</td>
                                    <td class="px-5 py-4 text-sm text-gray-600 dark:text-gray-400">{{ $row->grup1 }}</td>
                                    <td class="px-5 py-4 text-sm text-gray-500 dark:text-gray-400 font-mono">{{ $row->alat ?: '-' }}</td>
                                    <td class="whitespace-nowrap px-5 py-4 text-center text-sm">
                                        <form method="POST" action="{{ route('lis.referensi.paket.delete', $paket->id) }}" onsubmit="return confirm('Keluarkan parameter ini dari paket?')">
                                            @csrf
                                            <input type="hidden" name="id" value="{{ $row->detail_id }}">
                                            <button type="submit"
                                                class="inline-flex size-8 items-center justify-center rounded-lg bg-error-50 text-error-600 hover:bg-error-100 dark:bg-error-500/10 dark:text-error-400">
                                                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-5 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                                        Belum ada parameter di dalam paket ini. Klik tombol di atas untuk menambahkan.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </x-common.component-card>
    </div>

    <!-- Modal Add Parameter to Package -->
    <div x-data="{ open: false, query: '', selected: [] }"
        @open-add-param-modal.window="open = true; selected = []; query = ''"
        class="fixed inset-0 z-999999 flex items-center justify-center bg-black/60 backdrop-blur-xs p-4" x-show="open" x-cloak>
        <div @click.away="open = false" 
            class="w-full max-w-4xl rounded-2xl bg-white p-6 shadow-xl dark:bg-gray-900 border border-gray-100 dark:border-gray-800 text-left flex flex-col max-h-[90vh]">
            
            <div class="flex items-center justify-between mb-2">
                <h3 class="text-lg font-bold text-gray-950 dark:text-white">Tambah Parameter ke Paket: {{ $paket->nama }}</h3>
                <button type="button" @click="open = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                    <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <p class="text-sm text-gray-500 mb-4">Pilih satu atau beberapa parameter dari daftar di bawah ini.</p>

            <div class="relative mb-4">
                <span class="absolute -translate-y-1/2 pointer-events-none left-4 top-1/2 text-gray-400 dark:text-gray-500">
                    <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </span>
                <input type="text" x-model="query" placeholder="Cari parameter berdasarkan nama, kode LIS, grup, atau alat..." 
                    class="h-11 w-full rounded-lg border border-gray-300 bg-transparent pl-11 pr-4 text-sm text-gray-800 outline-none placeholder:text-gray-400 focus:border-brand-500 dark:border-gray-700 dark:text-white/90 focus:ring-3 focus:ring-brand-500/10 transition duration-150">
            </div>

            <div class="flex-1 overflow-hidden min-h-0 flex flex-col">
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
                            @foreach ($availableKode as $kode)
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
            </div>

            <form method="POST" action="{{ route('lis.referensi.paket.add', $paket->id) }}" class="mt-4 flex justify-end gap-2">
                @csrf
                <input type="hidden" name="kode_ids" :value="selected.join(',')">
                <button type="button" @click="open = false" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg dark:bg-gray-800 dark:text-gray-300">Batal</button>
                <button type="submit" :disabled="selected.length === 0" class="px-4 py-2 text-sm font-medium text-white bg-brand-500 hover:bg-brand-600 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed">Tambahkan</button>
            </form>
        </div>
    </div>
@endsection
