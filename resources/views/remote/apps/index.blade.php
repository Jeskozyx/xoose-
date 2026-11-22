@extends('layouts.app')

@section('title', 'Application Manager — Xoose Remote Service Manager')

@php
    $activeMenu = 'remote.apps';
@endphp

@section('content')
<div class="h-full flex flex-col overflow-y-auto px-8 py-8">
    {{-- Header --}}
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-6 border-b border-gray-100">
        <div>
            <div class="flex items-center gap-2 text-sm text-gray-500 mb-1">
                <a href="{{ route('dashboard') }}" class="hover:text-gray-900 transition-colors">Xoose</a>
                <span>/</span>
                <span class="text-gray-800 font-medium">Remote Manager</span>
                <span>/</span>
                <span class="text-gray-800 font-semibold">Application Manager</span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900">Application Manager</h1>
            <p class="text-sm text-gray-500 mt-1">Kelola aplikasi LoveYou & modul game, terbitkan kode enrollment perangkat, dan picu build EXE terpusat.</p>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('remote.devices.index') }}" class="px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-gray-700 text-sm font-semibold hover:bg-gray-50 transition-colors">
                Lihat Perangkat
            </a>
            <a href="{{ route('builder.projects.index') }}" class="px-3.5 py-2.5 rounded-xl bg-[#1A1D1F] text-white text-sm font-semibold hover:bg-black transition-colors shadow-sm">
                Buka App Builder
            </a>
        </div>
    </div>

    {{-- Flash Messages --}}
    @if (session('success'))
        <div class="mt-6 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <span>{{ session('success') }}</span>
            @if (session('generated_enrollment_code'))
                <code class="px-3 py-1 rounded-lg bg-white border border-emerald-300 font-mono font-bold text-emerald-900 select-all">
                    {{ session('generated_enrollment_code') }}
                </code>
            @endif
        </div>
    @endif

    @if (session('error'))
        <div class="mt-6 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm">
            {{ session('error') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mt-6 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm">
            <ul class="list-disc list-inside space-y-0.5">
                @foreach ($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- KPI Ringkas --}}
    <div class="mt-6 grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="p-5 rounded-2xl border border-gray-200 bg-white">
            <div class="text-xs font-medium text-gray-500">Total Aplikasi Terkelola</div>
            <div class="mt-1 text-2xl font-bold text-gray-900">{{ $apps->count() }}</div>
        </div>
        <div class="p-5 rounded-2xl border border-gray-200 bg-white">
            <div class="text-xs font-medium text-gray-500">Perangkat Online Saat Ini</div>
            <div class="mt-1 text-2xl font-bold text-emerald-600">{{ $apps->sum('online_devices_count') }} <span class="text-sm font-normal text-gray-400">/ {{ $apps->sum('devices_count') }}</span></div>
        </div>
        <div class="p-5 rounded-2xl border border-gray-200 bg-white">
            <div class="text-xs font-medium text-gray-500">Total Release Terdaftar</div>
            <div class="mt-1 text-2xl font-bold text-gray-900">{{ $apps->sum('releases_count') }}</div>
        </div>
    </div>

    {{-- Form Pendaftaran ManagedApp Baru --}}
    <div class="mt-6 p-6 rounded-2xl border border-gray-200 bg-[#FAFAFB]">
        <h2 class="text-sm font-bold text-gray-900 mb-3">Daftarkan Aplikasi Baru ke Control Plane</h2>
        <form method="POST" action="{{ route('remote.apps.store') }}" class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
            @csrf
            <div class="md:col-span-3">
                <label class="block text-xs font-semibold text-gray-600 mb-1">Nama Aplikasi *</label>
                <input type="text" name="name" value="{{ old('name') }}" required placeholder="LoveYou Primary" class="w-full px-3 py-2 rounded-xl border border-gray-200 bg-white text-sm">
            </div>
            <div class="md:col-span-2">
                <label class="block text-xs font-semibold text-gray-600 mb-1">Slug (opsional)</label>
                <input type="text" name="slug" value="{{ old('slug') }}" placeholder="loveyou" class="w-full px-3 py-2 rounded-xl border border-gray-200 bg-white text-sm font-mono">
            </div>
            <div class="md:col-span-3">
                <label class="block text-xs font-semibold text-gray-600 mb-1">Hubungkan Proyek Builder</label>
                <select name="app_project_id" class="w-full px-3 py-2 rounded-xl border border-gray-200 bg-white text-sm">
                    <option value="">— Tanpa tautan proyek —</option>
                    @foreach ($availableProjects as $proj)
                        <option value="{{ $proj->id }}">{{ $proj->name }} ({{ $proj->slug }})</option>
                    @endforeach
                </select>
            </div>
            <div class="md:col-span-2">
                <label class="block text-xs font-semibold text-gray-600 mb-1">Versi Awal</label>
                <input type="text" name="current_version" value="{{ old('current_version', '1.0.0') }}" class="w-full px-3 py-2 rounded-xl border border-gray-200 bg-white text-sm font-mono">
            </div>
            <div class="md:col-span-2">
                <button type="submit" class="w-full px-4 py-2 rounded-xl bg-[#1A1D1F] hover:bg-black text-white text-sm font-semibold transition-colors cursor-pointer">
                    + Daftarkan App
                </button>
            </div>
        </form>
    </div>

    {{-- Daftar Managed Apps --}}
    <div class="mt-8 space-y-4">
        @forelse ($apps as $app)
            <div class="p-6 rounded-2xl border border-gray-200 bg-white hover:border-gray-300 transition-colors">
                <div class="flex flex-col lg:flex-row lg:items-start justify-between gap-4">
                    <div>
                        <div class="flex flex-wrap items-center gap-2.5">
                            <h3 class="text-lg font-bold text-gray-900">{{ $app->name }}</h3>
                            <span class="px-2 py-0.5 rounded-md bg-gray-100 text-gray-700 font-mono text-xs">{{ $app->slug }}</span>
                            <span class="px-2 py-0.5 rounded-md bg-sky-50 text-sky-700 border border-sky-200 font-mono text-xs">v{{ $app->current_version }}</span>
                            <x-remote-status-badge :status="$app->build_status" />
                        </div>
                        @if ($app->description)
                            <p class="text-xs text-gray-500 mt-1">{{ $app->description }}</p>
                        @endif
                        <div class="mt-3 flex flex-wrap items-center gap-4 text-xs text-gray-500">
                            <span>Perangkat: <strong class="text-gray-900">{{ $app->online_devices_count }} Online</strong> / {{ $app->devices_count }} total</span>
                            <span>Release: <strong class="text-gray-900">{{ $app->releases_count }}</strong></span>
                            @if ($app->project)
                                <span>Proyek Builder: <a href="{{ route('builder.projects.show', $app->project) }}" class="font-semibold text-gray-900 underline">{{ $app->project->name }}</a></span>
                            @endif
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <a href="{{ route('remote.devices.index', ['managed_app_id' => $app->id]) }}" class="px-3 py-2 rounded-xl border border-gray-200 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                            Perangkat ({{ $app->devices_count }})
                        </a>
                        <a href="{{ route('remote.configs.edit', ['scope' => 'app-' . $app->id, 'service' => 'screenshot_service']) }}" class="px-3 py-2 rounded-xl border border-gray-200 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                            Konfigurasi Runtime
                        </a>
                        <a href="{{ route('remote.releases.index', ['managed_app_id' => $app->id]) }}" class="px-3 py-2 rounded-xl border border-gray-200 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                            Releases
                        </a>
                        @if ($app->project)
                            <form method="POST" action="{{ route('remote.apps.build', $app) }}" class="inline">
                                @csrf
                                <input type="hidden" name="format" value="onefile">
                                <button type="submit" class="px-3 py-2 rounded-xl bg-[#1A1D1F] hover:bg-black text-white text-xs font-semibold cursor-pointer">
                                    Build EXE
                                </button>
                            </form>
                        @endif
                    </div>
                </div>

                {{-- Panel Enrollment Code Generator --}}
                <div class="mt-5 pt-4 border-t border-gray-100 flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                    <form method="POST" action="{{ route('remote.apps.enrollments.store', $app) }}" class="flex flex-wrap items-center gap-2">
                        @csrf
                        <input type="text" name="label" placeholder="Label perangkat (mis. Laptop A)" class="px-3 py-1.5 rounded-lg border border-gray-200 text-xs w-48">
                        <input type="number" name="max_uses" value="1" min="1" max="500" title="Maksimal perangkat" class="px-2.5 py-1.5 rounded-lg border border-gray-200 text-xs w-20">
                        <button type="submit" class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold cursor-pointer">
                            + Buat Kode Enrollment
                        </button>
                    </form>

                    @if ($app->enrollments->isNotEmpty())
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-[11px] text-gray-400">Kode Terbaru:</span>
                            @foreach ($app->enrollments->take(3) as $enr)
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-gray-50 border border-gray-200 font-mono text-[11px] text-gray-700">
                                    <span class="select-all font-semibold">{{ $enr->enrollment_code }}</span>
                                    <span class="text-gray-400">({{ $enr->uses_count }}/{{ $enr->max_uses }} · {{ $enr->status }})</span>
                                </span>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        @empty
            <div class="p-12 text-center border-2 border-dashed border-gray-200 rounded-2xl bg-gray-50/50">
                <h3 class="text-sm font-semibold text-gray-900">Belum ada aplikasi di Remote Service Manager</h3>
                <p class="text-xs text-gray-500 mt-1">Gunakan formulir di atas untuk mendaftarkan aplikasi LoveYou atau modul game pertama Anda.</p>
            </div>
        @endforelse
    </div>
</div>
@endsection
