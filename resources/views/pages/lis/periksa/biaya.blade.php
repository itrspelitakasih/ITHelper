@extends('layouts.app')

@section('content')
    <div class="mb-6 flex items-center justify-between gap-4">
        <div class="flex items-center gap-2.5">
            <a href="{{ route('lis.periksa.show', $model->id) }}" class="inline-flex h-9 items-center justify-center rounded-lg border border-gray-300 bg-white px-4 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-950 dark:text-gray-300">
                <svg class="mr-1.5 size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                Kembali ke Detail
            </a>
            <h2 class="text-xl font-bold text-gray-900 dark:text-white">Billing & Biaya Pemeriksaan</h2>
        </div>
        <a href="{{ route('lis.periksa.cetak-biaya', $model->id) }}" target="_blank" class="inline-flex h-9 items-center justify-center rounded-lg bg-brand-500 px-4 text-sm font-medium text-white hover:bg-brand-600">
            Cetak Nota Biaya
        </a>
    </div>

    @if (session('success'))
        <x-ui.alert variant="success" :message="session('success')" class="mb-6" />
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <!-- Billing Summary & Rate Picker -->
        <div class="space-y-6 lg:col-span-1">
            <x-common.component-card :title="'Beban Biaya LIS'">
                <div class="space-y-3.5 text-sm">
                    <div>
                        <span class="block text-xs font-semibold text-gray-400 uppercase tracking-wider">No. Lab</span>
                        <span class="font-bold text-gray-900 dark:text-white">{{ $model->nomor }}</span>
                    </div>
                    <div>
                        <span class="block text-xs font-semibold text-gray-400 uppercase tracking-wider">Nama Pasien</span>
                        <span class="font-bold text-gray-900 dark:text-white">{{ $model->pasien->nama ?? '-' }}</span>
                    </div>
                    <div>
                        <span class="block text-xs font-semibold text-gray-400 uppercase tracking-wider">Total Biaya Saat Ini</span>
                        <span class="font-bold text-brand-600 dark:text-brand-400 text-lg">Rp {{ number_format($modelBiaya->sum('tarif'), 0, ',', '.') }}</span>
                    </div>
                </div>
            </x-common.component-card>

            <!-- Rate MultiSelect Panel -->
            <x-common.component-card :title="'Bebankan Tarif Baru'">
                <form method="POST" action="{{ route('lis.periksa.biaya.add', $model->id) }}" class="space-y-4" x-data="{ query: '', selected: [] }">
                    @csrf
                    <div>
                        <span class="mb-1.5 block text-xs font-semibold text-gray-400 uppercase tracking-wider">Pilih Item Tarif</span>
                        <input type="text" x-model="query" placeholder="Cari item tarif..." 
                            class="mb-3 h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white/90">
                        
                        <div class="max-h-[300px] overflow-y-auto border border-gray-100 dark:border-gray-800 rounded-lg p-2.5 space-y-1">
                            @foreach ($modelTarif as $t)
                                <label class="flex items-center gap-2.5 p-2 hover:bg-gray-50 dark:hover:bg-gray-800/50 rounded cursor-pointer"
                                    x-show="'{{ addslashes(strtolower($t->nama)) }}'.includes(query.toLowerCase())">
                                    <input type="checkbox" value="{{ $t->id }}" x-model="selected" class="size-4 text-brand-600 rounded">
                                    <div class="text-sm">
                                        <span class="font-semibold text-gray-800 dark:text-gray-200">{{ $t->nama }}</span>
                                        <span class="block text-xs text-gray-400">Rp {{ number_format($t->biaya, 0, ',', '.') }}</span>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <input type="hidden" name="kode" :value="selected.join(',')">
                    <button type="submit" :disabled="selected.length === 0"
                        class="w-full h-11 rounded-lg bg-brand-500 hover:bg-brand-600 text-sm font-semibold text-white disabled:opacity-50 disabled:cursor-not-allowed">
                        Terapkan Tarif Terpilih
                    </button>
                </form>
            </x-common.component-card>
        </div>

        <!-- Table displaying currently charged items -->
        <div class="lg:col-span-2">
            <x-common.component-card :title="'Item Tarif Yang Dibebankan'">
                <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800">
                    <table class="w-full border-collapse text-left">
                        <thead class="bg-gray-50 dark:bg-gray-900">
                            <tr class="border-b border-gray-200 dark:border-gray-800">
                                <th class="px-5 py-3 text-xs font-medium uppercase text-gray-500">Nama Tarif</th>
                                <th class="px-5 py-3 text-xs font-medium uppercase text-gray-500 w-44">Biaya</th>
                                <th class="px-5 py-3 text-center text-xs font-medium uppercase text-gray-500 w-24">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse ($modelBiaya as $item)
                                @php
                                    $tarifDetail = $modelTarif->firstWhere('id', $item->tarif_id);
                                @endphp
                                <tr class="hover:bg-gray-50/20 dark:hover:bg-gray-800/10">
                                    <td class="px-5 py-4 font-semibold text-gray-850 dark:text-white">
                                        {{ $tarifDetail->nama ?? 'Tarif #' . $item->tarif_id }}
                                    </td>
                                    <td class="px-5 py-4 text-sm font-mono text-gray-900 dark:text-white font-semibold">
                                        Rp {{ number_format($item->tarif, 0, ',', '.') }}
                                    </td>
                                    <td class="px-5 py-4 text-center">
                                        <form method="POST" action="{{ route('lis.periksa.biaya.delete', $model->id) }}" onsubmit="return confirm('Hapus beban tarif ini?')">
                                            @csrf
                                            <input type="hidden" name="id_biaya" value="{{ $item->id }}">
                                            <button type="submit"
                                                class="inline-flex size-8 items-center justify-center rounded-lg bg-error-50 text-error-600 hover:bg-error-100 dark:bg-error-500/10 dark:text-error-400">
                                                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-5 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                                        Belum ada tarif yang dibebankan pada pemeriksaan ini.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                        @if ($modelBiaya->count() > 0)
                            <tfoot class="bg-gray-50 dark:bg-gray-900">
                                <tr class="font-bold border-t border-gray-200 dark:border-gray-800 text-gray-900 dark:text-white">
                                    <td class="px-5 py-4 text-right">TOTAL</td>
                                    <td class="px-5 py-4 font-mono text-base" colspan="2">Rp {{ number_format($modelBiaya->sum('tarif'), 0, ',', '.') }}</td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </x-common.component-card>
        </div>
    </div>
@endsection
