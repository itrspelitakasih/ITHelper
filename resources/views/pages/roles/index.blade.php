@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Manajemen Role" />
    <x-common.component-card title="Daftar Role">
        <div class="flex justify-between"><p class="text-sm text-gray-500">Kelompokkan permission lalu tetapkan kepada user.</p><a href="{{ route('roles.create') }}" class="rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white">Tambah Role</a></div>
        @if (session('success')) <div class="rounded-lg bg-success-50 p-3 text-sm text-success-700">{{ session('success') }}</div> @endif
        <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-800">
            <table class="w-full min-w-[700px]"><thead class="bg-gray-50 dark:bg-gray-900"><tr>
                @foreach (['Role', 'Slug', 'Permission', 'User', 'Aksi'] as $heading)<th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ $heading }}</th>@endforeach
            </tr></thead><tbody>
                @foreach ($roles as $role)
                    <tr class="border-t border-gray-100 dark:border-gray-800">
                        <td class="px-5 py-4"><span class="font-medium text-gray-800 dark:text-white/90">{{ $role->name }}</span><span class="block text-xs text-gray-400">{{ $role->description }}</span></td>
                        <td class="px-5 py-4 text-sm text-gray-600">{{ $role->slug }}</td>
                        <td class="px-5 py-4 text-sm text-gray-600">{{ $role->permissions_count }}</td>
                        <td class="px-5 py-4 text-sm text-gray-600">{{ $role->users_count }}</td>
                        <td class="px-5 py-4"><div class="flex gap-2"><a href="{{ route('roles.edit', $role) }}" class="rounded-lg border px-3 py-2 text-sm">Ubah</a>
                            @if ($role->slug !== 'super-admin' && $role->users_count === 0)<form method="POST" action="{{ route('roles.destroy', $role) }}" onsubmit="return confirm('Hapus role ini?')">@csrf @method('DELETE')<button class="rounded-lg border border-error-200 px-3 py-2 text-sm text-error-600">Hapus</button></form>@endif
                        </div></td>
                    </tr>
                @endforeach
            </tbody></table>
        </div>
        {{ $roles->links() }}
    </x-common.component-card>
@endsection
