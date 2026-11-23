@extends('layouts.app')

@section('title', 'Logs & Monitoring — Xoose Remote Service Manager')

@php
    $activeMenu = 'remote.logs';
@endphp

@section('content')
<div class="h-full flex flex-col overflow-y-auto px-8 py-8">
    {{-- Header --}}
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-6 border-b border-gray-100">
        <div>
            <div class="flex items-center gap-2 text-sm text-gray-500 mb-1">
                <a href="{{ route('remote.apps.index') }}" class="hover:text-gray-900">Remote Manager</a>
                <span>/</span>
                <span class="text-gray-800 font-semibold">Logs & Monitoring</span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900">Logs, Errors & Admin Audit Trail</h1>
            <p class="text-sm text-gray-500 mt-1">Audit lengkap seluruh tindakan admin Xoose, event eksekusi command, perubahan konfigurasi, dan log error lokal perangkat.</p>
        </div>

        <form method="POST"
              action="{{ route('remote.logs.clear') }}"
              data-confirm="Bersihkan seluruh catatan log & audit trail pada Remote Manager Anda? Aplikasi dan perangkat tetap tersimpan."
              onsubmit="return confirm(this.dataset.confirm);"
              class="shrink-0">
            @csrf
            @method('DELETE')
            <button type="submit" class="px-3.5 py-2.5 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-sm font-semibold hover:bg-rose-100 transition-colors cursor-pointer">
                Bersihkan Semua Log
            </button>
        </form>
    </div>

    @if (session('success'))
        <div class="mt-6 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="mt-6 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm">{{ session('error') }}</div>
    @endif

    {{-- Filter Bar --}}
    <form method="GET" action="{{ route('remote.logs.index') }}" class="mt-6 p-4 rounded-2xl border border-gray-200 bg-[#FAFAFB] flex flex-wrap items-center gap-3">
        <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="Cari pesan atau service..." class="px-3.5 py-2 rounded-xl border border-gray-200 bg-white text-sm flex-1 min-w-[200px]">
        <select name="event_type" class="px-3 py-2 rounded-xl border border-gray-200 bg-white text-sm">
            <option value="">Semua Event</option>
            @foreach (['admin_audit', 'agent_event', 'command_event', 'config_event', 'enrollment_event', 'security_event', 'heartbeat_timeout'] as $et)
                <option value="{{ $et }}" @selected($filters['event_type'] === $et)>{{ $et }}</option>
            @endforeach
        </select>
        <select name="level" class="px-3 py-2 rounded-xl border border-gray-200 bg-white text-sm">
            <option value="">Semua Level</option>
            @foreach (['debug', 'info', 'warning', 'error', 'critical'] as $lvl)
                <option value="{{ $lvl }}" @selected($filters['level'] === $lvl)>{{ strtoupper($lvl) }}</option>
            @endforeach
        </select>
        <select name="device_id" class="px-3 py-2 rounded-xl border border-gray-200 bg-white text-sm">
            <option value="">Semua Perangkat</option>
            @foreach ($devices as $dev)
                <option value="{{ $dev->id }}" @selected((string)$filters['device_id'] === (string)$dev->id)>{{ $dev->hostname }}</option>
            @endforeach
        </select>
        <button type="submit" class="px-4 py-2 rounded-xl bg-[#1A1D1F] text-white text-sm font-semibold hover:bg-black cursor-pointer">Filter</button>
        @if ($filters['q'] || $filters['level'] || $filters['event_type'] || $filters['device_id'])
            <a href="{{ route('remote.logs.index') }}" class="px-3 py-2 text-xs font-semibold text-gray-500 hover:text-gray-900">Reset</a>
        @endif
    </form>

    {{-- Tabel Logs --}}
    <div class="mt-6 rounded-2xl border border-gray-200 bg-white overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="border-b border-gray-100 text-[11px] uppercase tracking-wider text-gray-400 bg-gray-50/70">
                    <th class="px-5 py-3.5 font-semibold">Waktu</th>
                    <th class="px-5 py-3.5 font-semibold">Level & Event</th>
                    <th class="px-5 py-3.5 font-semibold">Sumber (Service / Aktor)</th>
                    <th class="px-5 py-3.5 font-semibold">Pesan & Konteks</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 text-xs">
                @forelse ($logs as $log)
                    <tr class="hover:bg-gray-50/60 align-top">
                        <td class="px-5 py-3.5 whitespace-nowrap font-mono text-gray-500">
                            {{ $log->created_at?->format('d/m/Y H:i:s') }}
                        </td>
                        <td class="px-5 py-3.5 whitespace-nowrap">
                            <x-remote-status-badge :status="strtoupper($log->level)" />
                            <div class="font-mono text-[11px] text-gray-500 mt-1">{{ $log->event_type }}</div>
                        </td>
                        <td class="px-5 py-3.5 whitespace-nowrap">
                            <div class="font-mono font-semibold text-gray-800">{{ $log->service_key }}</div>
                            @if ($log->device)
                                <div class="text-[11px] text-gray-500">Device: {{ $log->device->hostname }}</div>
                            @endif
                            @if ($log->actor)
                                <div class="text-[11px] text-indigo-600">Admin: {{ $log->actor->email }}</div>
                            @endif
                        </td>
                        <td class="px-5 py-3.5">
                            <div class="text-gray-900 font-medium">{{ $log->message }}</div>
                            @if (! empty($log->context))
                                <pre class="mt-1.5 p-2 rounded-lg bg-gray-50 border border-gray-100 font-mono text-[10px] text-gray-500 overflow-x-auto max-w-xl">{{ json_encode($log->context, JSON_UNESCAPED_SLASHES) }}</pre>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-5 py-12 text-center text-sm text-gray-400">
                            Belum ada log atau audit trail yang tercatat.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $logs->links() }}
    </div>
</div>
@endsection
