@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Manajemen User" />
    <x-common.component-card title="Daftar User">
        <div class="flex justify-between">
            <p class="text-sm text-gray-500">Kelola akun lokal dan role yang dimiliki.</p>
            <a href="{{ route('users.create') }}" class="rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white">Tambah User</a>
        </div>
        @if (session('success')) <div class="rounded-lg bg-success-50 p-3 text-sm text-success-700">{{ session('success') }}</div> @endif
        <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-800">
            <table class="w-full min-w-[800px]">
                <thead class="bg-gray-50 dark:bg-gray-900"><tr>
                    @foreach (['Nama', 'Email', 'Role', 'Dibuat', 'Aksi'] as $heading)
                        <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ $heading }}</th>
                    @endforeach
                </tr></thead>
                <tbody>
                    @foreach ($users as $user)
                        <tr class="border-t border-gray-100 dark:border-gray-800">
                            <td class="px-5 py-4 font-medium text-gray-800 dark:text-white/90">{{ $user->name }}</td>
                            <td class="px-5 py-4 text-sm text-gray-600 dark:text-gray-300">{{ $user->email }}</td>
                            <td class="px-5 py-4 text-sm text-gray-600 dark:text-gray-300">{{ $user->roles->pluck('name')->join(', ') ?: '-' }}</td>
                            <td class="px-5 py-4 text-sm text-gray-500">{{ $user->created_at->format('d/m/Y') }}</td>
                            <td class="px-5 py-4"><div class="flex gap-2">
                                <a href="{{ route('users.edit', $user) }}" class="rounded-lg border px-3 py-2 text-sm">Ubah</a>
                                @unless (auth()->user()->is($user))
                                    <form method="POST" action="{{ route('users.destroy', $user) }}" onsubmit="return confirm('Hapus user ini?')">
                                        @csrf @method('DELETE')
                                        <button class="rounded-lg border border-error-200 px-3 py-2 text-sm text-error-600">Hapus</button>
                                    </form>
                                @endunless
                            </div></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $users->links() }}
    </x-common.component-card>
@endsection
