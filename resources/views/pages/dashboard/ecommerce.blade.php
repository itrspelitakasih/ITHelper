@extends('layouts.app')

@section('content')
    <div class="space-y-6">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <h2 class="text-2xl font-semibold text-gray-800 dark:text-white/90">Ringkasan Transaksi FHIR</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Statistik berdasarkan tanggal pelayanan data yang sudah dikirim ke SATUSEHAT.</p>
            </div>
            <form method="GET" action="{{ route('dashboard') }}" class="grid grid-cols-1 gap-3 sm:grid-cols-4"
                x-data="{ period: '{{ $filters['period'] }}' }">
                <label class="sm:col-span-1">
                    <span class="form-label">Periode</span>
                    <select name="period" x-model="period" class="form-control">
                        <option value="7">7 hari ke belakang</option>
                        <option value="30">30 hari ke belakang</option>
                        <option value="90">90 hari ke belakang</option>
                        <option value="365">1 tahun ke belakang</option>
                        <option value="custom">Tanggal manual</option>
                    </select>
                </label>
                <label><span class="form-label">Dari Tanggal</span><input type="date" name="from" value="{{ $filters['from'] }}" :disabled="period !== 'custom'" class="form-control disabled:bg-gray-100 disabled:text-gray-400"></label>
                <label><span class="form-label">Sampai Tanggal</span><input type="date" name="to" value="{{ $filters['to'] }}" class="form-control"></label>
                <div class="flex items-end gap-2">
                    <button class="h-11 flex-1 rounded-lg bg-brand-500 px-4 text-sm font-medium text-white">Terapkan</button>
                    <a href="{{ route('dashboard') }}" class="inline-flex h-11 items-center rounded-lg border border-gray-300 px-3 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.05]">Reset</a>
                </div>
            </form>
        </div>

        <div class="text-right text-sm text-gray-500 dark:text-gray-400">Update data terakhir: <strong class="text-gray-800 dark:text-white/90">{{ now()->translatedFormat('d F Y, H:i') }} WITA</strong></div>
        @if ($connectionError) <x-ui.alert variant="warning" title="Database eksternal tidak dapat diakses" :message="$connectionError" /> @endif

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($dashboard['items'] as $item)
                <a href="{{ url($item['path']) }}" class="min-w-0 rounded-xl border border-gray-200 bg-white p-4 transition hover:border-brand-300 hover:shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]">
                    <p class="truncate text-sm font-medium text-gray-800 dark:text-white/90" title="{{ $item['label'] }}">{{ $item['label'] }}</p>
                    @if ($item['available'])
                        <p class="mt-2 truncate text-2xl font-bold text-brand-500">{{ number_format($item['sent_count']) }}</p>
                        <div class="mt-2 flex min-w-0 items-center justify-between gap-2 text-[11px]">
                            <span class="truncate text-success-600" title="{{ number_format($item['sent_count']) }} terkirim">{{ number_format($item['sent_count']) }} terkirim</span>
                            <span class="truncate text-warning-600" title="{{ number_format($item['pending']) }} belum terkirim">{{ number_format($item['pending']) }} belum</span>
                        </div>
                    @else
                        <p class="mt-2 text-2xl font-bold text-gray-400">-</p>
                    @endif
                </a>
            @endforeach
        </div>

        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800 sm:px-6">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Rincian Per Menu</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Tabel ini mengikuti filter periode yang sama dengan kartu dan grafik.</p>
            </div>
            <div class="max-w-full overflow-x-auto custom-scrollbar">
                <table class="w-full min-w-[800px]">
                    <thead class="bg-gray-50 dark:bg-gray-900"><tr class="border-b border-gray-200 dark:border-gray-800">
                        @foreach (['Menu', 'Total Data', 'Sudah Terkirim', 'Belum Terkirim', 'Progres', 'Aksi'] as $heading) <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ $heading }}</th> @endforeach
                    </tr></thead>
                    <tbody>
                        @foreach ($dashboard['items'] as $item)
                            @php($percentage = $item['all'] > 0 ? min(100, round($item['sent_count'] / $item['all'] * 100, 1)) : 0)
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <td class="px-5 py-4 text-sm font-medium text-gray-800 dark:text-white/90">{{ $item['label'] }}</td>
                                <td class="px-5 py-4 text-sm">{{ number_format($item['all']) }}</td>
                                <td class="px-5 py-4 text-sm font-medium text-success-600">{{ number_format($item['sent_count']) }}</td>
                                <td class="px-5 py-4 text-sm font-medium text-warning-600">{{ number_format($item['pending']) }}</td>
                                <td class="px-5 py-4"><div class="w-40"><div class="h-2 rounded-full bg-gray-100 dark:bg-gray-800"><div class="h-2 rounded-full bg-brand-500" style="width: {{ $percentage }}%"></div></div><span class="mt-1 block text-xs text-gray-400">{{ $percentage }}% terkirim</span></div></td>
                                <td class="px-5 py-4"><a href="{{ url($item['path']) }}" class="inline-flex rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.05]">Buka Menu</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
