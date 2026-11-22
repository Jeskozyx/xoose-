@extends('layouts.app')

@section('title', 'Command History — Xoose Remote Service Manager')

@php
    $activeMenu = 'remote.commands';
@endphp

@section('content')
<div class="h-full flex flex-col overflow-y-auto px-8 py-8">
    {{-- Header --}}
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-6 border-b border-gray-100">
        <div>
            <div class="flex items-center gap-2 text-sm text-gray-500 mb-1">
                <a href="{{ route('remote.apps.index') }}" class="hover:text-gray-900">Remote Manager</a>
                <span>/</span>
                <span class="text-gray-800 font-semibold">Command History</span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900">Command Center & History</h1>
            <p class="text-sm text-gray-500 mt-1">Pantau antrean command (<code class="font-mono text-xs bg-gray-100 px-1 rounded">pending</code> → <code class="font-mono text-xs bg-gray-100 px-1 rounded">delivered</code> → <code class="font-mono text-xs bg-gray-100 px-1 rounded">acknowledged</code> → <code class="font-mono text-xs bg-gray-100 px-1 rounded">succeeded</code>/<code class="font-mono text-xs bg-gray-100 px-1 rounded">failed</code>/<code class="font-mono text-xs bg-gray-100 px-1 rounded">expired</code>), batalkan, atau kirim ulang.</p>
        </div>
    </div>

    @if (session('success'))
        <div class="mt-6 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="mt-6 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm">{{ session('error') }}</div>
    @endif

    {{-- Filter Bar --}}
    <form method="GET" action="{{ route('remote.commands.index') }}" class="mt-6 p-4 rounded-2xl border border-gray-200 bg-[#FAFAFB] flex flex-wrap items-center gap-3">
        <select name="status" class="px-3 py-2 rounded-xl border border-gray-200 bg-white text-sm">
            <option value="">Semua Status</option>
            @foreach (['pending', 'delivered', 'acknowledged', 'succeeded', 'failed', 'expired', 'cancelled'] as $st)
                <option value="{{ $st }}" @selected($filters['status'] === $st)>{{ ucfirst($st) }}</option>
            @endforeach
        </select>

        <select name="device_id" class="px-3 py-2 rounded-xl border border-gray-200 bg-white text-sm">
            <option value="">Semua Perangkat</option>
            @foreach ($devices as $dev)
                <option value="{{ $dev->id }}" @selected((string)$filters['device_id'] === (string)$dev->id)>{{ $dev->hostname }} ({{ $dev->device_code }})</option>
            @endforeach
        </select>

        <input type="text" name="service_key" value="{{ $filters['service_key'] }}" placeholder="Filter service_key..." class="px-3 py-2 rounded-xl border border-gray-200 bg-white text-sm font-mono">
        <button type="submit" class="px-4 py-2 rounded-xl bg-[#1A1D1F] text-white text-sm font-semibold hover:bg-black cursor-pointer">Filter</button>
        @if ($filters['status'] || $filters['device_id'] || $filters['service_key'])
            <a href="{{ route('remote.commands.index') }}" class="px-3 py-2 text-xs font-semibold text-gray-500 hover:text-gray-900">Reset</a>
        @endif
    </form>

    {{-- Tabel Command --}}
    <div class="mt-6 rounded-2xl border border-gray-200 bg-white overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="border-b border-gray-100 text-[11px] uppercase tracking-wider text-gray-400 bg-gray-50/70">
                    <th class="px-5 py-3.5 font-semibold">Command & Target</th>
                    <th class="px-5 py-3.5 font-semibold">Service & Aksi</th>
                    <th class="px-5 py-3.5 font-semibold">Status</th>
                    <th class="px-5 py-3.5 font-semibold">TTL / Waktu</th>
                    <th class="px-5 py-3.5 font-semibold">Hasil Eksekusi</th>
                    <th class="px-5 py-3.5 font-semibold text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 text-xs">
                @forelse ($commands as $cmd)
                    <tr class="hover:bg-gray-50/60">
                        <td class="px-5 py-3.5">
                            <div class="font-bold text-gray-900">{{ $cmd->device?->hostname ?? 'Unknown Device' }}</div>
                            <div class="font-mono text-[11px] text-gray-400">{{ $cmd->command_id }}</div>
                        </td>
                        <td class="px-5 py-3.5">
                            <span class="px-2 py-0.5 rounded bg-gray-900 text-white font-mono font-semibold uppercase text-[10px]">{{ $cmd->action }}</span>
                            <div class="font-mono text-gray-600 mt-1">{{ $cmd->service_key }}</div>
                        </td>
                        <td class="px-5 py-3.5">
                            <x-remote-status-badge :status="$cmd->status" />
                        </td>
                        <td class="px-5 py-3.5 text-gray-500">
                            <div>Dibuat: {{ $cmd->created_at?->format('H:i:s d/m') }}</div>
                            <div>Expired: {{ $cmd->expires_at?->format('H:i:s d/m') }}</div>
                        </td>
                        <td class="px-5 py-3.5 max-w-xs text-gray-600">
                            {{ $cmd->result_message ?: 'Menunggu konfirmasi Agent...' }}
                        </td>
                        <td class="px-5 py-3.5 text-right">
                            <div class="inline-flex items-center gap-1.5">
                                @if (! $cmd->isTerminal())
                                    <form method="POST" action="{{ route('remote.commands.cancel', $cmd) }}">
                                        @csrf
                                        <button type="submit" class="px-2.5 py-1 rounded-lg border border-rose-200 text-rose-700 font-semibold hover:bg-rose-50 cursor-pointer">
                                            Cancel
                                        </button>
                                    </form>
                                @elseif ($cmd->device && ! $cmd->device->isRevoked())
                                    <form method="POST" action="{{ route('remote.commands.retry', $cmd) }}">
                                        @csrf
                                        <button type="submit" class="px-2.5 py-1 rounded-lg border border-gray-200 text-gray-700 font-semibold hover:bg-gray-100 cursor-pointer">
                                            Retry
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-5 py-12 text-center text-sm text-gray-400">
                            Belum ada riwayat remote command.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $commands->links() }}
    </div>
</div>
@endsection
