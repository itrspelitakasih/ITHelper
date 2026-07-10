@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="User Profile" />

    @if (session('success'))
        <x-ui.alert variant="success" :message="session('success')" class="mb-6" />
    @endif

    @if ($errors->any())
        <x-ui.alert variant="error" title="Terjadi Kesalahan" class="mb-6">
            <ul class="list-disc pl-5 mt-2">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </x-ui.alert>
    @endif

    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
        <h3 class="mb-5 text-lg font-semibold text-gray-800 dark:text-white/90 lg:mb-7">Profile</h3>
        <x-profile.profile-card />
        <x-profile.personal-info-card />
        <x-profile.address-card />
        <x-profile.password-card />
    </div>
@endsection
