@extends('layouts.app')

@section('title', 'Service Configuration — Xoose Remote Service Manager')

@php
    $activeMenu = 'remote.configs';
@endphp

@section('content')
<div class="h-full flex flex-col overflow-y-auto px-8 py-8">
    {{-- Header --}}
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-6 border-b border-gray-100">
        <div>
            <div class="flex items-center gap-2 text-sm text-gray-500 mb-1">
                <a href="{{ route('remote.apps.index') }}" class="hover:text-gray-900">Remote Manager</a>
                <span>/</span>
                <span class="text-gray-800 font-semibold">Service Configuration</span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900">Service Configuration (Runtime)</h1>
            <p class="text-sm text-gray-500 mt-1">Ubah konfigurasi runtime layanan tanpa build ulang EXE, tervalidasi skema JSON dengan dukungan versi & rollback.</p>
        </div>
    </div>

    @if (session('success'))
        <div class="mt-6 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="mt-6 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm">{{ session('error') }}</div>
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

    @if (! $managedApp || ! $serviceDefinition)
        <div class="mt-8 p-12 text-center border-2 border-dashed border-gray-200 rounded-2xl bg-gray-50/50">
            <h3 class="text-sm font-semibold text-gray-900">Belum ada aplikasi untuk dikonfigurasi</h3>
            <p class="text-xs text-gray-500 mt-1">Silakan daftarkan aplikasi terlebih dahulu di Application Manager.</p>
            <a href="{{ route('remote.apps.index') }}" class="mt-4 inline-flex px-4 py-2 rounded-xl bg-[#1A1D1F] text-white text-xs font-semibold">
                Buka Application Manager
            </a>
        </div>
    @else
        {{-- Scope & Service Switcher --}}
        <div class="mt-6 p-4 rounded-2xl border border-gray-200 bg-[#FAFAFB] flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-xs font-semibold text-gray-500 mr-1">Scope:</span>
                @foreach ($apps as $appOption)
                    <a
                        href="{{ route('remote.configs.edit', ['scope' => 'app-' . $appOption->id, 'service' => $serviceDefinition->service_key]) }}"
                        class="px-3 py-1.5 rounded-lg text-xs font-semibold border transition-colors {{ ($device === null && $managedApp->id === $appOption->id) ? 'bg-[#1A1D1F] text-white border-[#1A1D1F]' : 'bg-white text-gray-700 border-gray-200 hover:bg-gray-50' }}"
                    >
                        App: {{ $appOption->name }}
                    </a>
                @endforeach

                @foreach ($devices as $devOption)
                    <a
                        href="{{ route('remote.configs.edit', ['scope' => 'device-' . $devOption->id, 'service' => $serviceDefinition->service_key]) }}"
                        class="px-3 py-1.5 rounded-lg text-xs font-semibold border transition-colors {{ ($device && $device->id === $devOption->id) ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-700 border-gray-200 hover:bg-gray-50' }}"
                    >
                        Device: {{ $devOption->hostname }}
                    </a>
                @endforeach
            </div>

            <div class="text-xs font-mono text-gray-500">
                Aktif: <strong class="text-gray-900">{{ $activeConfig ? 'v' . $activeConfig->config_version : 'Default Skema (v0)' }}</strong>
            </div>
        </div>

        {{-- Service Tabs --}}
        <div class="mt-4 flex flex-wrap gap-2">
            @foreach ($definitions as $def)
                <a
                    href="{{ route('remote.configs.edit', ['scope' => $scope, 'service' => $def->service_key]) }}"
                    class="px-3.5 py-2 rounded-xl text-xs font-semibold border transition-colors {{ $def->service_key === $serviceDefinition->service_key ? 'bg-white text-gray-900 border-gray-900 shadow-sm' : 'bg-gray-50 text-gray-600 border-gray-200 hover:bg-white' }}"
                >
                    {{ $def->display_name }}
                </a>
            @endforeach
        </div>

        <div class="mt-6 grid grid-cols-1 xl:grid-cols-12 gap-6">
            {{-- Form Editor Konfigurasi Runtime --}}
            <div class="xl:col-span-7 p-6 rounded-2xl border border-gray-200 bg-white">
                <div class="flex items-start justify-between gap-2 pb-4 border-b border-gray-100">
                    <div>
                        <h2 class="text-base font-bold text-gray-900">{{ $serviceDefinition->display_name }}</h2>
                        <p class="text-xs text-gray-500 mt-0.5">
                            Target:
                            @if ($device)
                                <strong class="text-indigo-700">Override Perangkat {{ $device->hostname }} ({{ $device->device_code }})</strong>
                            @else
                                <strong class="text-gray-900">Default Aplikasi {{ $managedApp->name }}</strong>
                            @endif
                        </p>
                    </div>
                    <span class="px-2.5 py-1 rounded-lg bg-gray-100 font-mono text-xs text-gray-700">{{ $serviceDefinition->service_key }}</span>
                </div>

                <form method="POST" action="{{ route('remote.configs.update', ['scope' => $scope, 'service' => $serviceDefinition->service_key]) }}" class="mt-5 space-y-4">
                    @csrf
                    @method('PUT')

                    @php
                        $props = $serviceDefinition->configuration_schema['properties'] ?? [];
                    @endphp

                    @foreach ($props as $fieldKey => $rule)
                        @php
                            $fieldType = $rule['type'] ?? 'string';
                            $val = old("config.{$fieldKey}", $currentValues[$fieldKey] ?? ($rule['default'] ?? ''));
                        @endphp
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">
                                {{ $fieldKey }}
                                <span class="font-mono font-normal text-gray-400">({{ $fieldType }})</span>
                            </label>

                            @if ($fieldType === 'boolean')
                                <label class="inline-flex items-center gap-2.5 px-3.5 py-2 rounded-xl border border-gray-200 bg-gray-50 cursor-pointer">
                                    <input type="hidden" name="config[{{ $fieldKey }}]" value="0">
                                    <input type="checkbox" name="config[{{ $fieldKey }}]" value="1" @checked((bool) $val) class="rounded border-gray-300 text-gray-900">
                                    <span class="text-xs font-medium text-gray-700">Aktifkan {{ $fieldKey }}</span>
                                </label>
                            @elseif (! empty($rule['enum']))
                                <select name="config[{{ $fieldKey }}]" class="w-full px-3.5 py-2 rounded-xl border border-gray-200 text-sm bg-white">
                                    @foreach ($rule['enum'] as $opt)
                                        <option value="{{ $opt }}" @selected((string)$val === (string)$opt)>{{ $opt }}</option>
                                    @endforeach
                                </select>
                            @elseif ($fieldType === 'integer' || $fieldType === 'number')
                                <input
                                    type="number"
                                    name="config[{{ $fieldKey }}]"
                                    value="{{ $val }}"
                                    step="{{ $fieldType === 'number' ? '0.1' : '1' }}"
                                    @if(isset($rule['minimum'])) min="{{ $rule['minimum'] }}" @endif
                                    @if(isset($rule['maximum'])) max="{{ $rule['maximum'] }}" @endif
                                    class="w-full px-3.5 py-2 rounded-xl border border-gray-200 text-sm font-mono"
                                >
                            @elseif ($fieldType === 'object')
                                <textarea
                                    name="config[{{ $fieldKey }}]"
                                    rows="3"
                                    class="w-full px-3.5 py-2 rounded-xl border border-gray-200 text-xs font-mono"
                                >{{ is_array($val) ? json_encode($val, JSON_PRETTY_PRINT) : ($val ?: '{}') }}</textarea>
                            @else
                                <input
                                    type="text"
                                    name="config[{{ $fieldKey }}]"
                                    value="{{ $val }}"
                                    class="w-full px-3.5 py-2 rounded-xl border border-gray-200 text-sm"
                                >
                            @endif
                        </div>
                    @endforeach

                    <div class="pt-2 border-t border-gray-100">
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Catatan Perubahan (Audit)</label>
                        <input type="text" name="change_notes" value="{{ old('change_notes') }}" placeholder="Contoh: Ubah interval screenshot menjadi 10 detik" class="w-full px-3.5 py-2 rounded-xl border border-gray-200 text-sm">
                    </div>

                    <div class="flex justify-end pt-2">
                        <button type="submit" class="px-5 py-2.5 rounded-xl bg-[#1A1D1F] hover:bg-black text-white text-sm font-semibold shadow-sm cursor-pointer">
                            Simpan & Terbitkan Versi Baru
                        </button>
                    </div>
                </form>
            </div>

            {{-- Riwayat Versi & Rollback --}}
            <div class="xl:col-span-5 p-6 rounded-2xl border border-gray-200 bg-white">
                <h3 class="text-sm font-bold text-gray-900 mb-4">Riwayat Versi & Rollback</h3>
                <div class="space-y-3">
                    @forelse ($history as $ver)
                        <div class="p-4 rounded-xl border {{ $ver->is_active ? 'border-emerald-300 bg-emerald-50/30' : 'border-gray-200 bg-gray-50/40' }}">
                            <div class="flex items-center justify-between gap-2">
                                <div class="flex items-center gap-2">
                                    <span class="font-mono font-bold text-sm text-gray-900">v{{ $ver->config_version }}</span>
                                    @if ($ver->is_active)
                                        <x-remote-status-badge status="Active" />
                                    @endif
                                    @if ($ver->force_apply)
                                        <span class="px-2 py-0.5 rounded bg-amber-100 text-amber-800 text-[10px] font-semibold">Rollback</span>
                                    @endif
                                </div>

                                @if (! $ver->is_active)
                                    <form method="POST" action="{{ route('remote.configs.rollback', ['scope' => $scope, 'service' => $serviceDefinition->service_key, 'version' => $ver->config_version]) }}">
                                        @csrf
                                        <button type="submit" class="px-2.5 py-1 rounded-lg border border-amber-300 bg-amber-50 hover:bg-amber-100 text-amber-800 text-[11px] font-semibold cursor-pointer">
                                            Rollback ke v{{ $ver->config_version }}
                                        </button>
                                    </form>
                                @endif
                            </div>

                            @if ($ver->change_notes)
                                <div class="text-xs text-gray-700 mt-1.5">{{ $ver->change_notes }}</div>
                            @endif
                            <div class="text-[11px] text-gray-400 mt-1">
                                {{ $ver->updater?->name ?? 'System' }} · {{ $ver->created_at?->format('d M Y, H:i') }}
                            </div>
                            <pre class="mt-2 p-2.5 rounded-lg bg-white border border-gray-200 text-[11px] font-mono text-gray-600 overflow-x-auto">{{ json_encode($ver->config_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                        </div>
                    @empty
                        <div class="p-8 text-center text-xs text-gray-400 border border-dashed border-gray-200 rounded-xl">
                            Belum ada versi konfigurasi tersimpan. Simpan konfigurasi pertama di sebelah kiri.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
