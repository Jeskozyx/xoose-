@extends('layouts.app')

@section('title', "Service Manager ({$device->hostname}) — Xoose")

@php
    $activeMenu = 'remote.devices';
@endphp

@section('content')
<div class="h-full flex flex-col overflow-y-auto px-8 py-8">
    {{-- Header --}}
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-6 border-b border-gray-100">
        <div>
            <div class="flex items-center gap-2 text-sm text-gray-500 mb-1">
                <a href="{{ route('remote.devices.index') }}" class="hover:text-gray-900">Device Manager</a>
                <span>/</span>
                <a href="{{ route('remote.devices.show', $device) }}" class="hover:text-gray-900">{{ $device->hostname }}</a>
                <span>/</span>
                <span class="text-gray-800 font-semibold">Service Manager</span>
            </div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold tracking-tight text-gray-900">Service Manager</h1>
                <x-remote-status-badge :status="$device->status" />
            </div>
            <p class="text-sm text-gray-500 mt-1">Kendali lifecycle independen tiap service modular berbasis allowlist ketat (tanpa shell arbitrer).</p>
        </div>

        <a href="{{ route('remote.devices.show', $device) }}" class="px-4 py-2 rounded-xl border border-gray-200 text-xs font-semibold text-gray-700 hover:bg-gray-50">
            ← Kembali ke Detail Perangkat
        </a>
    </div>

    @if (session('success'))
        <div class="mt-6 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="mt-6 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm">{{ session('error') }}</div>
    @endif

    @if ($device->isRevoked())
        <div class="mt-6 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm font-medium">
            Perangkat ini telah dicabut (Revoked). Pengiriman perintah remote dinonaktifkan.
        </div>
    @endif

    {{-- Kartu Kontrol Tiap Service --}}
    <div class="mt-6 space-y-4">
        @foreach ($definitions as $def)
            @php
                $st = $statusMap->get($def->service_key);
                $currStatus = $st?->current_status ?? 'Unknown';
            @endphp
            <div class="p-6 rounded-2xl border border-gray-200 bg-white flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                <div class="max-w-xl">
                    <div class="flex flex-wrap items-center gap-2.5">
                        <h3 class="text-base font-bold text-gray-900">{{ $def->display_name }}</h3>
                        <span class="px-2 py-0.5 rounded bg-gray-100 font-mono text-[11px] text-gray-600">{{ $def->service_key }}</span>
                        <x-remote-status-badge :status="$currStatus" />
                    </div>
                    <p class="text-xs text-gray-500 mt-1.5">{{ $def->description }}</p>
                    <div class="mt-2 flex flex-wrap items-center gap-3 text-[11px] text-gray-400">
                        <span>Config Aktif: <strong class="text-gray-700 font-mono">v{{ $st?->active_config_version ?? 0 }}</strong></span>
                        <span>Dilaporkan: {{ $st?->reported_at ? $st->reported_at->diffForHumans() : 'Belum ada laporan' }}</span>
                        @if (! empty($def->permission_requirements))
                            <span>Izin: <span class="font-mono">{{ implode(', ', $def->permission_requirements) }}</span></span>
                        @endif
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    @foreach ($def->supported_actions ?? [] as $action)
                        @php
                            $btnClass = match ($action) {
                                'start', 'resume' => 'bg-emerald-600 hover:bg-emerald-700 text-white border-emerald-600',
                                'pause' => 'bg-amber-500 hover:bg-amber-600 text-white border-amber-500',
                                'stop' => 'bg-rose-600 hover:bg-rose-700 text-white border-rose-600',
                                default => 'bg-[#1A1D1F] hover:bg-black text-white border-[#1A1D1F]',
                            };
                        @endphp
                        <form method="POST" action="{{ route('remote.services.command', ['device' => $device, 'service' => $def->service_key]) }}">
                            @csrf
                            <input type="hidden" name="action" value="{{ $action }}">
                            <button
                                type="submit"
                                @disabled($device->isRevoked())
                                class="px-3.5 py-2 rounded-xl border text-xs font-semibold uppercase tracking-wide transition-colors cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed {{ $btnClass }}"
                            >
                                {{ $action }}
                            </button>
                        </form>
                    @endforeach

                    <a
                        href="{{ route('remote.configs.edit', ['scope' => 'device-' . $device->id, 'service' => $def->service_key]) }}"
                        class="px-3.5 py-2 rounded-xl border border-gray-200 text-xs font-semibold text-gray-700 hover:bg-gray-50"
                    >
                        Config
                    </a>
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection
