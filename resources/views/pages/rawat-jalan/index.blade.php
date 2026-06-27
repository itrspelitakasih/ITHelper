@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb :pageTitle="$title" />

    <div class="space-y-6">
        <x-common.component-card :title="'Filter ' . $title"
            desc="Cari data registrasi berdasarkan rentang tanggal registrasi dan kata kunci.">
            <form method="GET" action="{{ route('rawat-jalan.' . $type . '.index') }}" class="grid grid-cols-1 gap-4 lg:grid-cols-12">
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
                    <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Cari</span>
                    <input type="search" name="search" value="{{ $filters['search'] }}"
                        placeholder="No. rawat, RM, pasien, dokter, atau poli"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm text-gray-800 outline-none placeholder:text-gray-400 focus:border-brand-500 dark:border-gray-700 dark:text-white/90">
                </label>

                <div class="flex items-end gap-2 lg:col-span-2">
                    <button type="submit"
                        class="h-11 flex-1 rounded-lg bg-brand-500 px-4 text-sm font-medium text-white hover:bg-brand-600">
                        Terapkan
                    </button>
                    <a href="{{ route('rawat-jalan.' . $type . '.index') }}"
                        class="inline-flex h-11 items-center rounded-lg border border-gray-300 px-3 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.05]">
                        Reset
                    </a>
                </div>
            </form>
            @error('to')
                <p class="mt-2 text-sm text-error-500">{{ $message }}</p>
            @enderror
        </x-common.component-card>

        <x-common.component-card :title="'Daftar ' . $title">
            @if ($connectionError)
                <div class="rounded-lg border border-warning-200 bg-warning-50 p-4 text-sm text-warning-700 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-400">
                    {{ $connectionError }}
                </div>
            @else
                <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800">
                    <div class="max-w-full overflow-x-auto custom-scrollbar">
                        <table class="w-full min-w-[1600px]">
                            <thead class="bg-gray-50 dark:bg-gray-900">
                                <tr class="border-b border-gray-200 dark:border-gray-800">
                                    <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">No. Reg</th>
                                    <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">No. Rawat</th>
                                    <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Tanggal & Jam</th>
                                    <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Pasien</th>
                                    <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">J.K.</th>
                                    <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Umur</th>
                                    <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Dokter Dituju</th>
                                    <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Poliklinik</th>
                                    <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Cara Bayar</th>
                                    <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Penanggung Jawab</th>
                                    <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Biaya Reg</th>
                                    <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Status Periksa</th>
                                    <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Status Bayar</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @forelse ($registrations as $reg)
                                    <tr>
                                        <td class="whitespace-nowrap px-5 py-4 text-sm font-medium text-gray-950 dark:text-white">
                                            {{ $reg->no_reg }}
                                        </td>
                                        <td class="whitespace-nowrap px-5 py-4 text-sm font-semibold text-brand-600 dark:text-brand-400">
                                            {{ $reg->no_rawat }}
                                        </td>
                                        <td class="whitespace-nowrap px-5 py-4 text-sm text-gray-600 dark:text-gray-300">
                                            {{ \Illuminate\Support\Carbon::parse($reg->tgl_registrasi)->format('d/m/Y') }}
                                            <span class="block text-xs text-gray-400">{{ $reg->jam_reg }}</span>
                                        </td>
                                        <td class="px-5 py-4 text-sm text-gray-700 dark:text-gray-300">
                                            <span class="font-medium text-gray-950 dark:text-white">{{ $reg->nm_pasien }}</span>
                                            <span class="block text-xs text-gray-400">RM: {{ $reg->no_rkm_medis }} | Telp: {{ $reg->no_tlp ?: '-' }}</span>
                                        </td>
                                        <td class="whitespace-nowrap px-5 py-4 text-sm">
                                            @if($reg->jk === 'L')
                                                <span class="inline-flex rounded-full bg-blue-50 px-2.5 py-1 text-xs font-medium text-blue-700 dark:bg-blue-500/15 dark:text-blue-400">
                                                    Laki-laki
                                                </span>
                                            @elseif($reg->jk === 'P')
                                                <span class="inline-flex rounded-full bg-rose-50 px-2.5 py-1 text-xs font-medium text-rose-700 dark:bg-rose-500/15 dark:text-rose-400">
                                                    Perempuan
                                                </span>
                                            @else
                                                <span class="text-gray-400">-</span>
                                            @endif
                                        </td>
                                        <td class="whitespace-nowrap px-5 py-4 text-sm text-gray-700 dark:text-gray-300">
                                            {{ $reg->umurdaftar }} {{ $reg->sttsumur }}
                                        </td>
                                        <td class="px-5 py-4 text-sm text-gray-700 dark:text-gray-300">
                                            {{ $reg->nama_dokter }}
                                        </td>
                                        <td class="px-5 py-4 text-sm text-gray-700 dark:text-gray-300">
                                            {{ $reg->nm_poli }}
                                        </td>
                                        <td class="px-5 py-4 text-sm text-gray-700 dark:text-gray-300">
                                            {{ $reg->png_jawab }}
                                        </td>
                                        <td class="px-5 py-4 text-sm text-gray-700 dark:text-gray-300">
                                            <span class="font-medium text-gray-950 dark:text-white">{{ $reg->p_jawab ?: '-' }}</span>
                                            @if($reg->hubunganpj)
                                                <span class="block text-xs text-gray-400">Hub: {{ $reg->hubunganpj }}</span>
                                            @endif
                                            @if($reg->almt_pj)
                                                <span class="block text-xs text-gray-400">Alamat: {{ $reg->almt_pj }}</span>
                                            @endif
                                        </td>
                                        <td class="whitespace-nowrap px-5 py-4 text-sm text-gray-700 dark:text-gray-300">
                                            Rp {{ number_format($reg->biaya_reg) }}
                                        </td>
                                        <td class="whitespace-nowrap px-5 py-4 text-sm">
                                            @php
                                                $sttsClass = match ($reg->stts) {
                                                    'Belum' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
                                                    'Sudah' => 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-400',
                                                    'Batal' => 'bg-error-50 text-error-700 dark:bg-error-500/15 dark:text-error-400',
                                                    'Dirujuk' => 'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-400',
                                                    'Dirawat' => 'bg-blue-50 text-blue-700 dark:bg-blue-500/15 dark:text-blue-400',
                                                    default => 'bg-gray-50 text-gray-600 dark:bg-gray-800 dark:text-gray-400'
                                                };
                                            @endphp
                                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $sttsClass }}">
                                                {{ $reg->stts }}
                                            </span>
                                            <span class="mt-1 block text-xs text-gray-400">
                                                Daftar: <span class="font-medium">{{ $reg->stts_daftar }}</span>
                                            </span>
                                        </td>
                                        <td class="whitespace-nowrap px-5 py-4 text-sm">
                                            @php
                                                $payClass = match ($reg->status_bayar) {
                                                    'Sudah Bayar' => 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-400',
                                                    'Belum Bayar' => 'bg-warning-50 text-warning-700 dark:bg-warning-500/15 dark:text-warning-400',
                                                    default => 'bg-gray-50 text-gray-600 dark:bg-gray-800 dark:text-gray-400'
                                                };
                                            @endphp
                                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $payClass }}">
                                                {{ $reg->status_bayar }}
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="13" class="px-5 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                                            Tidak ada data registrasi yang ditemukan.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="mt-4">
                    {{ $registrations->links() }}
                </div>
            @endif
        </x-common.component-card>
    </div>
@endsection
