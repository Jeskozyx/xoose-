@extends('layouts.app')

@section('title', 'Device Manager — Xoose Remote Service Manager')

@php
    $activeMenu = 'remote.devices';
@endphp

@section('content')
<div class="h-full flex flex-col overflow-y-auto px-8 py-8">
    {{-- Header --}}
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-6 border-b border-gray-100">
        <div>
            <div class="flex items-center gap-2 text-sm text-gray-500 mb-1">
                <a href="{{ route('remote.apps.index') }}" class="hover:text-gray-900">Remote Manager</a>
                <span>/</span>
                <span class="text-gray-800 font-semibold">Device Manager</span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900">Device Manager</h1>
            <p class="text-sm text-gray-500 mt-1">Pantau seluruh perangkat Windows terdaftar, status Online/Offline real-time, dan cabut akses (revoke) kapan saja.</p>
        </div>

        <a href="{{ route('remote.apps.index') }}" class="px-4 py-2.5 rounded-xl bg-[#1A1D1F] text-white text-sm font-semibold hover:bg-black transition-colors shadow-sm">
            + Buat Kode Enrollment
        </a>
    </div>

    @if (session('success'))
        <div class="mt-6 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="mt-6 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm">{{ session('error') }}</div>
    @endif

    {{-- Ringkasan Status --}}
    <div class="mt-6 grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="p-4 rounded-2xl border border-gray-200 bg-white">
            <div class="text-xs text-gray-500">Total Perangkat</div>
            <div class="mt-1 text-2xl font-bold text-gray-900">{{ $stats['total'] }}</div>
        </div>
        <div class="p-4 rounded-2xl border border-emerald-200 bg-emerald-50/40">
            <div class="text-xs text-emerald-700 font-medium">Online</div>
            <div class="mt-1 text-2xl font-bold text-emerald-700">{{ $stats['online'] }}</div>
        </div>
        <div class="p-4 rounded-2xl border border-gray-200 bg-white">
            <div class="text-xs text-gray-500">Offline</div>
            <div class="mt-1 text-2xl font-bold text-gray-700">{{ $stats['offline'] }}</div>
        </div>
        <div class="p-4 rounded-2xl border border-rose-200 bg-rose-50/40">
            <div class="text-xs text-rose-700 font-medium">Revoked</div>
            <div class="mt-1 text-2xl font-bold text-rose-700">{{ $stats['revoked'] }}</div>
        </div>
    </div>

    {{-- Filter Bar --}}
    <form method="GET" action="{{ route('remote.devices.index') }}" class="mt-6 p-4 rounded-2xl border border-gray-200 bg-[#FAFAFB] flex flex-wrap items-center gap-3">
        <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="Cari hostname, device code, UUID..." class="px-3.5 py-2 rounded-xl border border-gray-200 bg-white text-sm flex-1 min-w-[200px]">
        <select name="managed_app_id" class="px-3 py-2 rounded-xl border border-gray-200 bg-white text-sm">
            <option value="">Semua Aplikasi</option>
            @foreach ($apps as $app)
                <option value="{{ $app->id }}" @selected((string)$filters['managed_app_id'] === (string)$app->id)>{{ $app->name }}</option>
            @endforeach
        </select>
        <select name="status" class="px-3 py-2 rounded-xl border border-gray-200 bg-white text-sm">
            <option value="">Semua Status</option>
            @foreach (['Online', 'Offline', 'Pending', 'Revoked'] as $st)
                <option value="{{ $st }}" @selected($filters['status'] === $st)>{{ $st }}</option>
            @endforeach
        </select>
        <button type="submit" class="px-4 py-2 rounded-xl bg-[#1A1D1F] text-white text-sm font-semibold hover:bg-black cursor-pointer">Filter</button>
        @if ($filters['q'] || $filters['status'] || $filters['managed_app_id'])
            <a href="{{ route('remote.devices.index') }}" class="px-3 py-2 rounded-xl text-xs font-semibold text-gray-500 hover:text-gray-900">Reset</a>
        @endif
    </form>

    {{-- Tabel Perangkat --}}
    <div class="mt-6 rounded-2xl border border-gray-200 bg-white overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="border-b border-gray-100 text-[11px] uppercase tracking-wider text-gray-400 bg-gray-50/70">
                    <th class="px-5 py-3.5 font-semibold">Perangkat</th>
                    <th class="px-5 py-3.5 font-semibold">Aplikasi & Versi</th>
                    <th class="px-5 py-3.5 font-semibold">Status</th>
                    <th class="px-5 py-3.5 font-semibold">Layanan Aktif</th>
                    <th class="px-5 py-3.5 font-semibold">Terakhir Terlihat</th>
                    <th class="px-5 py-3.5 font-semibold text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 text-sm">
                @forelse ($devices as $device)
                    <tr class="hover:bg-gray-50/60 transition-colors">
                        <td class="px-5 py-4">
                            <div class="font-bold text-gray-900">{{ $device->hostname }}</div>
                            <div class="text-xs font-mono text-gray-500">{{ $device->device_code }} · {{ $device->os_info ?? 'Windows' }}</div>
                        </td>
                        <td class="px-5 py-4">
                            <div class="font-medium text-gray-800">{{ $device->managedApp?->name ?? '-' }}</div>
                            <div class="text-xs font-mono text-gray-400">v{{ $device->app_version }}</div>
                        </td>
                        <td class="px-5 py-4">
                            <x-remote-status-badge :status="$device->status" />
                        </td>
                        <td class="px-5 py-4">
                            @php
                                $runningCount = $device->serviceStatuses->where('current_status', 'Running')->count();
                                $totalSvc = $device->serviceStatuses->count();
                            @endphp
                            <span class="text-xs font-semibold text-gray-700">{{ $runningCount }} Running</span>
                            <span class="text-xs text-gray-400">/ {{ $totalSvc }} reported</span>
                        </td>
                        <td class="px-5 py-4 text-xs text-gray-500">
                            <div>{{ $device->last_seen_at ? $device->last_seen_at->diffForHumans() : 'Belum ada heartbeat' }}</div>
                            @if ($device->last_ip)
                                <div class="font-mono text-[11px] text-gray-400">{{ $device->last_ip }}</div>
                            @endif
                        </td>
                        <td class="px-5 py-4 text-right">
                            <div class="inline-flex items-center gap-2">
                                <a href="{{ route('remote.devices.show', $device) }}" class="px-3 py-1.5 rounded-lg border border-gray-200 text-xs font-semibold text-gray-700 hover:bg-gray-100">
                                    Detail
                                </a>
                                <a href="{{ route('remote.services.index', $device) }}" class="px-3 py-1.5 rounded-lg bg-[#1A1D1F] text-white text-xs font-semibold hover:bg-black">
                                    Services
                                </a>
                                @if (! $device->isRevoked())
                                    <form method="POST" action="{{ route('remote.devices.revoke', $device) }}" onsubmit="return confirm('Cabut akses perangkat {{ $device->hostname }}? Token perangkat akan langsung tidak berlaku.');">
                                        @csrf
                                        <button type="submit" class="px-3 py-1.5 rounded-lg border border-rose-200 text-rose-700 text-xs font-semibold hover:bg-rose-50 cursor-pointer">
                                            Revoke
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-5 py-12 text-center text-sm text-gray-500">
                            Belum ada perangkat yang cocok dengan filter ini.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
