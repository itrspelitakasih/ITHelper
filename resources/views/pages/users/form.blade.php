@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb :pageTitle="$title" />
    <x-common.component-card :title="$title">
        <form method="POST" action="{{ $user->exists ? route('users.update', $user) : route('users.store') }}" class="space-y-5">
            @csrf
            @if ($user->exists) @method('PUT') @endif
            <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                <label><span class="form-label">Nama</span><input name="name" value="{{ old('name', $user->name) }}" required class="form-control"></label>
                <label><span class="form-label">Email</span><input type="email" name="email" value="{{ old('email', $user->email) }}" required class="form-control"></label>
                <label><span class="form-label">Password {{ $user->exists ? '(opsional)' : '' }}</span><input type="password" name="password" class="form-control"></label>
                <label><span class="form-label">Konfirmasi Password</span><input type="password" name="password_confirmation" class="form-control"></label>
            </div>
            <div>
                <span class="form-label">Role</span>
                <div class="grid grid-cols-1 gap-3 rounded-xl border border-gray-200 p-4 md:grid-cols-3 dark:border-gray-800">
                    @foreach ($roles as $role)
                        <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <input type="checkbox" name="roles[]" value="{{ $role->id }}" @checked(in_array($role->id, old('roles', $user->roles->pluck('id')->all())))>
                            {{ $role->name }}
                        </label>
                    @endforeach
                </div>
            </div>
            @if ($errors->any()) <p class="text-sm text-error-500">{{ $errors->first() }}</p> @endif
            <div class="flex justify-end gap-3"><a href="{{ route('users.index') }}" class="rounded-lg border border-gray-300 px-5 py-3 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.05]">Batal</a><button class="rounded-lg bg-brand-500 px-5 py-3 text-sm text-white hover:bg-brand-600">Simpan</button></div>
        </form>
    </x-common.component-card>
@endsection
