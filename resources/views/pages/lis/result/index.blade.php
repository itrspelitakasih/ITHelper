@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb :pageTitle="$title" />

    <div class="space-y-6">
        @if (session('success'))
            <div class="rounded-lg border border-success-200 bg-success-50 p-4 text-sm text-success-700 dark:border-success-500/30 dark:bg-success-500/10 dark:text-success-400">
                {{ session('success') }}
            </div>
        @endif

        <!-- Filter Card -->
        <x-common.component-card :title="'Cari Data Hasil Analyzer'" desc="Cari data hasil analyzer raw berdasarkan No. Sampel/Patient atau Kode Alat.">
            <form method="GET" action="{{ route('lis.result.index') }}" class="grid grid-cols-1 gap-4 lg:grid-cols-12">
                <label class="lg:col-span-10">
                    <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Pencarian</span>
                    <input type="search" name="search" value="{{ $search }}"
                        placeholder="No. Sampel/Patient, Kode Alat..."
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm text-gray-800 outline-none placeholder:text-gray-400 focus:border-brand-500 dark:border-gray-700 dark:text-white/90">
                </label>

                <div class="flex items-end gap-2 lg:col-span-2">
                    <button type="submit"
                        class="h-11 flex-1 rounded-lg bg-brand-500 px-4 text-sm font-medium text-white hover:bg-brand-600">
                        Cari
                    </button>
                    <a href="{{ route('lis.result.index') }}"
                        class="inline-flex h-11 items-center rounded-lg border border-gray-300 px-3 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.05]">
                        Reset
                    </a>
                </div>
            </form>
        </x-common.component-card>

        <!-- List Card -->
        <x-common.component-card :title="'Hasil Raw Analyzer (v_result)'">
            <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800" x-data="{
                deleteResult(id, btn) {
                    if (confirm('Hapus log hasil analyzer ini?')) {
                        fetch('{{ route('lis.result.delete') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({ id: id })
                        })
                        .then(() => {
                            btn.closest('tr').remove();
                        });
                    }
                }
            }">
                <div class="max-w-full overflow-x-auto custom-scrollbar">
                    <table class="w-full min-w-[800px] border-collapse text-left">
                        <thead class="bg-gray-50 dark:bg-gray-900">
                            <tr class="border-b border-gray-200 dark:border-gray-800">
                                <th class="px-5 py-3 text-xs font-medium uppercase text-gray-500">No. Sampel / Patient</th>
                                <th class="px-5 py-3 text-xs font-medium uppercase text-gray-500">Alat</th>
                                <th class="px-5 py-3 text-xs font-medium uppercase text-gray-500">Parameter</th>
                                <th class="px-5 py-3 text-xs font-medium uppercase text-gray-500 w-32">Nilai</th>
                                <th class="px-5 py-3 text-xs font-medium uppercase text-gray-500">Waktu Kirim</th>
                                <th class="px-5 py-3 text-center text-xs font-medium uppercase text-gray-500 w-24">ACC</th>
                                <th class="px-5 py-3 text-center text-xs font-medium uppercase text-gray-500 w-20">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse ($rows as $row)
                                <tr class="hover:bg-gray-50/20 dark:hover:bg-gray-800/10">
                                    <td class="whitespace-nowrap px-5 py-4 text-sm font-semibold text-brand-600 dark:text-brand-400">
                                        {{ $row->KodePatient }}
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-4 text-sm text-gray-800 dark:text-gray-200">
                                        {{ $row->KodeAlat ?: '-' }}
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-4 text-sm text-gray-600 dark:text-gray-400">
                                        {{ $row->KodeParamater }}
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-4 text-sm font-semibold text-gray-900 dark:text-white font-mono">
                                        {{ $row->Nilai }}
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-4 text-sm text-gray-500 dark:text-gray-400">
                                        {{ \Illuminate\Support\Carbon::parse($row->tanggal)->format('d/m/Y H:i:s') }}
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-4 text-center text-sm">
                                        @if ($row->acc == 1)
                                            <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400">ACC</span>
                                        @else
                                            <span class="inline-flex rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-400">Belum</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-4 text-center">
                                        <button type="button" @click="deleteResult({{ $row->id }}, $event.target)"
                                            class="inline-flex size-7 items-center justify-center rounded bg-error-50 text-error-600 hover:bg-error-100 dark:bg-error-500/10 dark:text-error-400">
                                            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-5 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                                        Tidak ada data hasil analyzer yang ditemukan.
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
