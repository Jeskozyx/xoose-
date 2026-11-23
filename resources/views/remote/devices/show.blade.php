@extends('layouts.app')

@section('title', "Detail {$device->hostname} — Xoose Remote Service Manager")

@php
    $activeMenu = 'remote.devices';
@endphp

@section('content')
<div class="h-full flex flex-col overflow-y-auto px-8 py-8">
    {{-- Header --}}
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 pb-6 border-b border-gray-100">
        <div>
            <div class="flex items-center gap-2 text-sm text-gray-500 mb-1">
                <a href="{{ route('remote.devices.index') }}" class="hover:text-gray-900">Device Manager</a>
                <span>/</span>
                <span class="text-gray-800 font-semibold">{{ $device->device_code }}</span>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="text-2xl font-bold tracking-tight text-gray-900">{{ $device->hostname }}</h1>
                <x-remote-status-badge :status="$device->status" />
            </div>
            <p class="text-xs font-mono text-gray-500 mt-1">
                UUID: {{ $device->device_uuid }} · App: {{ $device->managedApp?->name }} (v{{ $device->app_version }}) · OS: {{ $device->os_info ?? 'Windows' }}
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2.5">
            <a href="{{ route('remote.services.index', $device) }}" class="px-3.5 py-2 rounded-xl bg-[#1A1D1F] text-white text-xs font-semibold hover:bg-black">
                Buka Service Manager
            </a>
            <a href="{{ route('remote.configs.edit', ['scope' => 'device-' . $device->id, 'service' => 'screenshot_service']) }}" class="px-3.5 py-2 rounded-xl border border-gray-200 text-gray-700 text-xs font-semibold hover:bg-gray-50">
                Config Override Perangkat
            </a>
            @if (! $device->isRevoked())
                <form method="POST"
                      action="{{ route('remote.devices.revoke', $device) }}"
                      data-confirm="Cabut akses perangkat {{ $device->hostname }} secara permanen?"
                      onsubmit="return confirm(this.dataset.confirm);">
                    @csrf
                    <button type="submit" class="px-3.5 py-2 rounded-xl border border-amber-200 text-amber-700 text-xs font-semibold hover:bg-amber-50 cursor-pointer">
                        Cabut Akses (Revoke)
                    </button>
                </form>
            @endif
            <form method="POST"
                  action="{{ route('remote.devices.destroy', $device) }}"
                  data-confirm="Hapus permanen perangkat {{ $device->hostname }} ({{ $device->device_code }}) beserta seluruh command, heartbeat, status, dan log-nya?"
                  onsubmit="return confirm(this.dataset.confirm);">
                @csrf
                @method('DELETE')
                <button type="submit" class="px-3.5 py-2 rounded-xl border border-rose-200 bg-rose-50 text-rose-700 text-xs font-semibold hover:bg-rose-100 cursor-pointer">
                    Hapus Perangkat
                </button>
            </form>
        </div>
    </div>

    @if (session('success'))
        <div class="mt-6 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="mt-6 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm">{{ session('error') }}</div>
    @endif

    {{-- Metadata Ringkas --}}
    <div class="mt-6 grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="p-4 rounded-2xl border border-gray-200 bg-white">
            <div class="text-xs text-gray-500">Versi Aplikasi</div>
            <div class="mt-1 text-lg font-bold font-mono text-gray-900">v{{ $device->app_version }}</div>
        </div>
        <div class="p-4 rounded-2xl border border-gray-200 bg-white">
            <div class="text-xs text-gray-500">Terakhir Terlihat</div>
            <div class="mt-1 text-sm font-bold text-gray-900">{{ $device->last_seen_at ? $device->last_seen_at->format('d M Y, H:i:s') : 'Belum ada' }}</div>
        </div>
        <div class="p-4 rounded-2xl border border-gray-200 bg-white">
            <div class="text-xs text-gray-500">Alamat IP Terakhir</div>
            <div class="mt-1 text-sm font-bold font-mono text-gray-900">{{ $device->last_ip ?? '-' }}</div>
        </div>
        <div class="p-4 rounded-2xl border border-gray-200 bg-white">
            <div class="text-xs text-gray-500">Uptime Agent Terakhir</div>
            <div class="mt-1 text-lg font-bold font-mono text-gray-900">{{ $device->heartbeats->first() ? number_format($device->heartbeats->first()->agent_uptime, 0) . 's' : '-' }}</div>
        </div>
    </div>

    {{-- Status & Kontrol Cepat Seluruh Service --}}
    <div class="mt-8">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-base font-bold text-gray-900">Status Layanan Modular (7 Services)</h2>
            <a href="{{ route('remote.services.index', $device) }}" class="text-xs font-semibold text-gray-600 hover:text-gray-900">Kelola Detail →</a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            @foreach ($definitions as $def)
                @php
                    $st = $statusMap->get($def->service_key);
                    $currStatus = $st?->current_status ?? 'Unknown';
                    $cfgVer = $st?->active_config_version ?? 0;
                @endphp
                <div class="p-5 rounded-2xl border border-gray-200 bg-white flex flex-col justify-between">
                    <div>
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <div class="font-bold text-gray-900">{{ $def->display_name }}</div>
                                <div class="text-[11px] font-mono text-gray-400">{{ $def->service_key }} · cfg v{{ $cfgVer }}</div>
                            </div>
                            <x-remote-status-badge :status="$currStatus" />
                        </div>
                        <p class="text-xs text-gray-500 mt-2">{{ $def->description }}</p>
                    </div>

                    @if (! $device->isRevoked())
                        <div class="mt-4 pt-3 border-t border-gray-100 flex flex-wrap gap-1.5">
                            @foreach ($def->supported_actions ?? [] as $act)
                                <form method="POST" action="{{ route('remote.services.command', ['device' => $device, 'service' => $def->service_key]) }}">
                                    @csrf
                                    <input type="hidden" name="action" value="{{ $act }}">
                                    <button type="submit" class="px-2.5 py-1 rounded-lg border border-gray-200 bg-gray-50 hover:bg-[#1A1D1F] hover:text-white text-[11px] font-semibold uppercase tracking-wide transition-colors cursor-pointer">
                                        {{ $act }}
                                    </button>
                                </form>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    {{-- Heartbeats & Command Terbaru --}}
    <div class="mt-8 grid grid-cols-1 xl:grid-cols-2 gap-6">
        <div class="rounded-2xl border border-gray-200 bg-white p-5">
            <h3 class="text-sm font-bold text-gray-900 mb-3">Riwayat Heartbeat Terbaru</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left">
                    <thead>
                        <tr class="border-b border-gray-100 text-gray-400">
                            <th class="py-2">Waktu</th>
                            <th class="py-2">Versi</th>
                            <th class="py-2">Uptime</th>
                            <th class="py-2">IP</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($device->heartbeats as $hb)
                            <tr>
                                <td class="py-2 text-gray-700">{{ $hb->received_at?->format('H:i:s d/m') }}</td>
                                <td class="py-2 font-mono">v{{ $hb->app_version }}</td>
                                <td class="py-2 font-mono">{{ number_format($hb->agent_uptime, 1) }}s</td>
                                <td class="py-2 font-mono text-gray-500">{{ $hb->ip_address }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-6 text-center text-gray-400">Belum ada heartbeat terekam.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-bold text-gray-900">Command Terbaru</h3>
                <a href="{{ route('remote.commands.index', ['device_id' => $device->id]) }}" class="text-xs font-semibold text-gray-500 hover:text-gray-900">Semua →</a>
            </div>
            <div class="space-y-2.5">
                @forelse ($device->commands as $cmd)
                    <div class="p-3 rounded-xl bg-gray-50 border border-gray-100 flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <div class="text-xs font-bold text-gray-900">
                                <span class="uppercase">{{ $cmd->action }}</span> · <span class="font-mono font-normal">{{ $cmd->service_key }}</span>
                            </div>
                            <div class="text-[11px] text-gray-500 truncate">{{ $cmd->result_message ?? $cmd->command_id }}</div>
                        </div>
                        <x-remote-status-badge :status="$cmd->status" />
                    </div>
                @empty
                    <div class="py-6 text-center text-xs text-gray-400">Belum ada perintah dikirim ke perangkat ini.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
