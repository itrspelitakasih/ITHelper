@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb :pageTitle="$title" />

    <div class="space-y-6" x-data="{ 
        openStoreModal: false, 
        openEditModal: false, 
        editItem: {},
        openAdd(type) {
            this.openStoreModal = true;
        },
        openEdit(type, item) {
            this.editItem = {...item};
            this.openEditModal = true;
        }
    }">
        @if (session('success'))
            <div class="rounded-lg border border-success-200 bg-success-50 p-4 text-sm text-success-700 dark:border-success-500/30 dark:bg-success-500/10 dark:text-success-400">
                {{ session('success') }}
            </div>
        @endif

        @if (isset($connectionError) && $connectionError)
            <div class="rounded-lg border border-error-200 bg-error-50 p-4 text-sm text-error-700 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-400">
                {{ $connectionError }}
            </div>
        @endif

        <!-- Tab Navigation -->
        <div class="flex flex-wrap gap-2 border-b border-gray-200 dark:border-gray-800 pb-3">
            @foreach ([
                'pemeriksaan' => 'Pemeriksaan (Kode)',
                'mapping' => 'Mapping SIMRS',
                'tarif' => 'Tarif Tindakan',
                'paket' => 'Paket LIS',
                'dokter' => 'Dokter LIS',
                'ruang' => 'Ruangan LIS',
                'petugas' => 'Petugas LIS'
            ] as $key => $label)
                <a href="{{ route('lis.referensi.index', ['tab' => $key]) }}"
                    class="px-4 py-2 text-sm font-semibold rounded-lg border {{ $tab === $key ? 'border-brand-500 bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-200' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>

        <!-- Add Button -->
        @if (!in_array($tab, ['dokter', 'ruang', 'petugas']))
            <div class="flex justify-end">
                <button type="button" @click="openAdd('{{ $tab }}')"
                    class="inline-flex h-11 items-center justify-center rounded-lg bg-brand-500 px-5 text-sm font-semibold text-white hover:bg-brand-600">
                    + Tambah Baru
                </button>
            </div>
        @endif

        <!-- Data Card -->
        <x-common.component-card :title="'Daftar ' . ucfirst($tab)">
            <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800">
                <div class="max-w-full overflow-x-auto custom-scrollbar">
                    <table class="w-full min-w-[700px] border-collapse text-left">
                        <thead class="bg-gray-50 dark:bg-gray-900">
                            <tr class="border-b border-gray-200 dark:border-gray-800">
                                @if ($tab === 'pemeriksaan')
                                    <th class="px-5 py-3 text-xs font-medium uppercase text-gray-500">Nama Pemeriksaan</th>
                                    <th class="px-5 py-3 text-xs font-medium uppercase text-gray-500 w-32">LIS Code</th>
                                    <th class="px-5 py-3 text-xs font-medium uppercase text-gray-500 w-24">Satuan</th>
                                    <th class="px-5 py-3 text-xs font-medium uppercase text-gray-500 w-32">Metoda</th>
                                    <th class="px-5 py-3 text-xs font-medium uppercase text-gray-500">Grup 1 (Utama)</th>
                                    <th class="px-5 py-3 text-xs font-medium uppercase text-gray-500 w-20">Order</th>
                                @elseif ($tab === 'mapping')
                                    <th class="px-5 py-3 text-xs font-medium uppercase text-gray-500">Grup SIMRS</th>
                                    <th class="px-5 py-3 text-xs font-medium uppercase text-gray-500">Kode SIMRS</th>
                                    <th class="px-5 py-3 text-xs font-medium uppercase text-gray-500">LIS Code</th>
                                    <th class="px-5 py-3 text-xs font-medium uppercase text-gray-500">Nama</th>
                                @elseif ($tab === 'tarif')
                                    <th class="px-5 py-3 text-xs font-medium uppercase text-gray-500">Nama Tarif</th>
                                    <th class="px-5 py-3 text-xs font-medium uppercase text-gray-500 w-44">Biaya</th>
                                @elseif ($tab === 'paket')
                                    <th class="px-5 py-3 text-xs font-medium uppercase text-gray-500">Nama Paket LIS</th>
                                @elseif ($tab === 'dokter')
                                    <th class="px-5 py-3 text-xs font-medium uppercase text-gray-500">Nama Dokter</th>
                                    <th class="px-5 py-3 text-xs font-medium uppercase text-gray-500">ID Dokter SIMRS</th>
                                @elseif ($tab === 'ruang')
                                    <th class="px-5 py-3 text-xs font-medium uppercase text-gray-500">Nama Ruangan</th>
                                    <th class="px-5 py-3 text-xs font-medium uppercase text-gray-500">ID Poli SIMRS</th>
                                @elseif ($tab === 'petugas')
                                    <th class="px-5 py-3 text-xs font-medium uppercase text-gray-500">Nama Petugas LIS</th>
                                @endif
                                @if (!in_array($tab, ['dokter', 'ruang', 'petugas']))
                                    <th class="px-5 py-3 text-center text-xs font-medium uppercase text-gray-500 w-28">Aksi</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse ($data as $item)
                                <tr class="hover:bg-gray-50/20 dark:hover:bg-gray-800/10">
                                    @if ($tab === 'pemeriksaan')
                                        <td class="px-5 py-4 font-semibold text-gray-900 dark:text-white">{{ $item->nama }}</td>
                                        <td class="px-5 py-4 text-sm font-semibold text-brand-600 dark:text-brand-400 font-mono">{{ $item->lis }}</td>
                                        <td class="px-5 py-4 text-sm text-gray-700 dark:text-gray-300 font-mono">{{ $item->satuan ?: '-' }}</td>
                                        <td class="px-5 py-4 text-sm text-gray-600 dark:text-gray-400">{{ $item->metoda ?: '-' }}</td>
                                        <td class="px-5 py-4 text-sm text-gray-600 dark:text-gray-400">{{ $item->grup1 }}</td>
                                        <td class="px-5 py-4 text-sm text-gray-500 dark:text-gray-400 font-mono">{{ $item->order ?? 0 }}</td>
                                    @elseif ($tab === 'mapping')
                                        <td class="px-5 py-4 text-sm text-gray-800 dark:text-gray-200">{{ $item->grup }}</td>
                                        <td class="px-5 py-4 text-sm text-gray-800 dark:text-gray-200 font-mono">{{ $item->kode }}</td>
                                        <td class="px-5 py-4 text-sm font-semibold text-brand-600 dark:text-brand-400 font-mono">{{ $item->lis }}</td>
                                        <td class="px-5 py-4 text-sm text-gray-800 dark:text-gray-200">{{ $item->pemeriksaan ?: '-' }}</td>
                                    @elseif ($tab === 'tarif')
                                        <td class="px-5 py-4 font-semibold text-gray-900 dark:text-white">{{ $item->nama }}</td>
                                        <td class="px-5 py-4 text-sm font-semibold text-gray-900 dark:text-white font-mono">Rp {{ number_format($item->biaya, 0, ',', '.') }}</td>
                                    @elseif ($tab === 'paket')
                                        <td class="px-5 py-4 font-semibold text-gray-900 dark:text-white">{{ $item->nama }}</td>
                                    @elseif ($tab === 'dokter')
                                        <td class="px-5 py-4 font-semibold text-gray-900 dark:text-white">{{ $item->nama }}</td>
                                        <td class="px-5 py-4 text-sm text-gray-800 dark:text-gray-200 font-mono">{{ $item->id_dokter }}</td>
                                    @elseif ($tab === 'ruang')
                                        <td class="px-5 py-4 font-semibold text-gray-900 dark:text-white">{{ $item->nama }}</td>
                                        <td class="px-5 py-4 text-sm text-gray-800 dark:text-gray-200 font-mono">{{ $item->poli_id }}</td>
                                    @elseif ($tab === 'petugas')
                                        <td class="px-5 py-4 font-semibold text-gray-900 dark:text-white">{{ $item->nama }}</td>
                                    @endif
                                    
                                    @if (!in_array($tab, ['dokter', 'ruang', 'petugas']))
                                        <td class="whitespace-nowrap px-5 py-4 text-center text-sm">
                                            <div class="flex items-center justify-center gap-2">
                                                @if ($tab === 'paket')
                                                    <a href="{{ route('lis.referensi.paket.show', $item->id) }}"
                                                        class="inline-flex size-8 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 hover:bg-emerald-100 dark:bg-emerald-500/10 dark:text-emerald-400"
                                                        title="Lihat Isi Paket">
                                                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                        </svg>
                                                    </a>
                                                @endif
                                                <button type="button" @click="openEdit('{{ $tab }}', {{ json_encode($item) }})"
                                                    class="inline-flex size-8 items-center justify-center rounded-lg bg-brand-50 text-brand-600 hover:bg-brand-100 dark:bg-brand-500/10 dark:text-brand-400">
                                                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                                                </button>
                                                <form method="POST" action="{{ route('lis.referensi.delete', $tab) }}" onsubmit="return confirm('Hapus item referensi ini?')">
                                                    @csrf
                                                    <input type="hidden" name="id" value="{{ $item->id }}">
                                                    <button type="submit"
                                                        class="inline-flex size-8 items-center justify-center rounded-lg bg-error-50 text-error-600 hover:bg-error-100 dark:bg-error-500/10 dark:text-error-400">
                                                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="px-5 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                                        Tidak ada data referensi.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-4">
                {{ $data->links() }}
            </div>
        </x-common.component-card>

        <!-- Store Modal -->
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" x-show="openStoreModal" x-cloak>
            <div @click.away="openStoreModal = false" class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl dark:bg-gray-900 border border-gray-100 dark:border-gray-800 text-left">
                <h3 class="text-lg font-bold text-gray-950 dark:text-white mb-4">Tambah Referensi {{ ucfirst($tab) }}</h3>
                <form method="POST" action="{{ route('lis.referensi.store', $tab) }}" class="space-y-4">
                    @csrf
                    @if ($tab === 'pemeriksaan')
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nama Pemeriksaan</label>
                            <input type="text" name="nama" required class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white/90">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">LIS Code</label>
                            <input type="text" name="lis" required class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white/90">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Satuan</label>
                            <input type="text" name="satuan" class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white/90">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Metoda</label>
                            <input type="text" name="metoda" class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white/90">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Grup 1 (Utama)</label>
                            <input type="text" name="grup1" required class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white/90">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Grup 2</label>
                            <input type="text" name="grup2" class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white/90">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Grup 3</label>
                            <input type="text" name="grup3" class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white/90">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Order</label>
                            <input type="number" name="order" value="0" class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white/90">
                        </div>
                    @elseif ($tab === 'mapping')
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Grup SIMRS</label>
                            <input type="text" name="grup" required class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white/90">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Kode SIMRS</label>
                            <input type="text" name="kode" required class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white/90">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">LIS Code</label>
                            <input type="text" name="lis" required class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white/90">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nama / Keterangan</label>
                            <input type="text" name="pemeriksaan" class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white/90">
                        </div>
                    @elseif ($tab === 'tarif')
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nama Tarif</label>
                            <input type="text" name="nama" required class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white/90">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Biaya</label>
                            <input type="number" name="biaya" required class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white/90">
                        </div>
                    @elseif ($tab === 'paket' || $tab === 'petugas')
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nama</label>
                            <input type="text" name="nama" required class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white/90">
                        </div>
                    @elseif ($tab === 'dokter')
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nama Dokter</label>
                            <input type="text" name="nama" required class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white/90">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">ID Dokter SIMRS</label>
                            <input type="text" name="id_dokter" required class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white/90">
                        </div>
                    @elseif ($tab === 'ruang')
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nama Ruangan</label>
                            <input type="text" name="nama" required class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white/90">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">ID Poli SIMRS</label>
                            <input type="text" name="poli_id" required class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white/90">
                        </div>
                    @endif
                    <div class="flex justify-end gap-2 mt-6">
                        <button type="button" @click="openStoreModal = false" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg dark:bg-gray-800 dark:text-gray-300">Batal</button>
                        <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-brand-500 hover:bg-brand-600 rounded-lg">Simpan</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Edit Modal -->
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" x-show="openEditModal" x-cloak>
            <div @click.away="openEditModal = false" class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl dark:bg-gray-900 border border-gray-100 dark:border-gray-800 text-left">
                <h3 class="text-lg font-bold text-gray-950 dark:text-white mb-4">Edit Referensi {{ ucfirst($tab) }}</h3>
                <form method="POST" action="{{ route('lis.referensi.update', $tab) }}" class="space-y-4">
                    @csrf
                    <input type="hidden" name="id" :value="editItem.id">
                    @if ($tab === 'pemeriksaan')
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nama Pemeriksaan</label>
                            <input type="text" name="nama" x-model="editItem.nama" required class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white/90">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">LIS Code</label>
                            <input type="text" name="lis" x-model="editItem.lis" required class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white/90">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Satuan</label>
                            <input type="text" name="satuan" x-model="editItem.satuan" class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white/90">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Metoda</label>
                            <input type="text" name="metoda" x-model="editItem.metoda" class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white/90">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Grup 1 (Utama)</label>
                            <input type="text" name="grup1" x-model="editItem.grup1" required class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white/90">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Grup 2</label>
                            <input type="text" name="grup2" x-model="editItem.grup2" class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white/90">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Grup 3</label>
                            <input type="text" name="grup3" x-model="editItem.grup3" class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white/90">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Order</label>
                            <input type="number" name="order" x-model="editItem.order" class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white/90">
                        </div>
                    @elseif ($tab === 'mapping')
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Grup SIMRS</label>
                            <input type="text" name="grup" x-model="editItem.grup" required class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white/90">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Kode SIMRS</label>
                            <input type="text" name="kode" x-model="editItem.kode" required class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white/90">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">LIS Code</label>
                            <input type="text" name="lis" x-model="editItem.lis" required class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white/90">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nama / Keterangan</label>
                            <input type="text" name="pemeriksaan" x-model="editItem.pemeriksaan" class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white/90">
                        </div>
                    @elseif ($tab === 'tarif')
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nama Tarif</label>
                            <input type="text" name="nama" x-model="editItem.nama" required class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white/90">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Biaya</label>
                            <input type="number" name="biaya" x-model="editItem.biaya" required class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white/90">
                        </div>
                    @elseif ($tab === 'paket' || $tab === 'petugas')
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nama</label>
                            <input type="text" name="nama" x-model="editItem.nama" required class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white/90">
                        </div>
                    @elseif ($tab === 'dokter')
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nama Dokter</label>
                            <input type="text" name="nama" x-model="editItem.nama" required class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white/90">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">ID Dokter SIMRS</label>
                            <input type="text" name="id_dokter" x-model="editItem.id_dokter" required class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white/90">
                        </div>
                    @elseif ($tab === 'ruang')
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nama Ruangan</label>
                            <input type="text" name="nama" x-model="editItem.nama" required class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white/90">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">ID Poli SIMRS</label>
                            <input type="text" name="poli_id" x-model="editItem.poli_id" required class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white/90">
                        </div>
                    @endif
                    <div class="flex justify-end gap-2 mt-6">
                        <button type="button" @click="openEditModal = false" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg dark:bg-gray-800 dark:text-gray-300">Batal</button>
                        <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-brand-500 hover:bg-brand-600 rounded-lg">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
