@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb :pageTitle="$title" />
    <x-common.component-card :title="$title">
        <form method="POST" action="{{ $role->exists ? route('roles.update', $role) : route('roles.store') }}" class="space-y-5">
            @csrf @if ($role->exists) @method('PUT') @endif
            <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                <label><span class="form-label">Nama Role</span><input name="name" value="{{ old('name', $role->name) }}" required class="form-control"></label>
                <label><span class="form-label">Slug</span><input name="slug" value="{{ old('slug', $role->slug) }}" class="form-control" placeholder="otomatis-dari-nama"></label>
                <label class="md:col-span-2"><span class="form-label">Deskripsi</span><input name="description" value="{{ old('description', $role->description) }}" class="form-control"></label>
            </div>
            <div class="space-y-4">
                <span class="form-label">Permission</span>
                @foreach ($permissionGroups as $group => $permissions)
                    <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                        <h4 class="mb-3 font-medium text-gray-800 dark:text-white/90">{{ $group }}</h4>
                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                            @foreach ($permissions as $permission)
                                <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300"><input type="checkbox" name="permissions[]" value="{{ $permission->id }}" @checked(in_array($permission->id, old('permissions', $role->permissions->pluck('id')->all())))>{{ $permission->name }}</label>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
            @if ($errors->any()) <p class="text-sm text-error-500">{{ $errors->first() }}</p> @endif
            <div class="flex justify-end gap-3"><a href="{{ route('roles.index') }}" class="rounded-lg border border-gray-300 px-5 py-3 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.05]">Batal</a><button class="rounded-lg bg-brand-500 px-5 py-3 text-sm text-white hover:bg-brand-600">Simpan</button></div>
        </form>
    </x-common.component-card>
@endsection
