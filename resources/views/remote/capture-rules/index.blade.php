@extends('layouts.app')

@section('title', 'Capture Rules Manager — Xoose Remote Service Manager')

@php
    $activeMenu = 'remote.capture-rules';
    $policy = $config['capture_policy'] ?? [];
    $bc = $config['browser_capture'] ?? [];
    $wc = $config['window_capture'] ?? [];
    $activeSource = $policy['active_source'] ?? 'desktop_mss';
    $browserRules = $bc['rules'] ?? [];
    $windowRules = $wc['rules'] ?? [];
@endphp

@section('content')
<div class="h-full flex flex-col overflow-y-auto px-8 py-8">
    {{-- Header --}}
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-6 border-b border-gray-100">
        <div>
            <div class="flex items-center gap-2 text-sm text-gray-500 mb-1">
                <a href="{{ route('remote.apps.index') }}" class="hover:text-gray-900">Remote Manager</a>
                <span>/</span>
                <span class="text-gray-800 font-semibold">Capture Rules Manager</span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900">Specific Screen & Live Capture Rules</h1>
            <p class="text-sm text-gray-500 mt-1">Pilih dan ubah target tangkapan layar secara live saat aplikasi dipakai pengguna tanpa build ulang EXE.</p>
        </div>

        @if ($managedApp)
            <div class="flex items-center gap-2">
                <span class="px-3 py-1.5 rounded-xl bg-[#FAFAFB] border border-gray-200 font-mono text-xs text-gray-700">
                    Versi Aktif: <strong class="text-gray-900">{{ $activeRecord ? 'v' . $activeRecord->config_version : 'Default (v0)' }}</strong>
                </span>
            </div>
        @endif
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

    @if (! ($featureEnabled ?? false))
        <div class="mt-8 p-8 rounded-2xl border border-amber-200 bg-amber-50/60 max-w-3xl">
            <div class="flex items-center gap-2.5">
                <span class="px-2.5 py-1 rounded-full bg-amber-200/80 text-amber-900 text-[11px] font-bold uppercase tracking-wider">Beta · Terkunci Sementara</span>
                <span class="font-mono text-xs text-amber-800">browser_capture_service</span>
            </div>
            <h2 class="text-lg font-bold text-gray-900 mt-3">Specific Screen &amp; Capture Rules Dinonaktifkan Sementara</h2>
            <p class="text-sm text-gray-700 mt-1.5 leading-relaxed">
                Fitur <strong>Browser Domain Capture</strong> saat ini dibatasi karena masih dalam tahap <strong>Beta</strong>.
                Seluruh <strong>7 layanan utama</strong> (<span class="font-mono text-xs">ScreenshotService MSS</span>, <span class="font-mono text-xs">SyncService</span>, <span class="font-mono text-xs">TrayService</span>, <span class="font-mono text-xs">UiService</span>, <span class="font-mono text-xs">NotificationService</span>, <span class="font-mono text-xs">GameService</span>, <span class="font-mono text-xs">CustomService</span>) serta <strong>App Builder</strong> tetap aktif dan berjalan 100% normal seperti sebelumnya.
            </p>
            <div class="mt-4 p-4 rounded-xl bg-white border border-amber-200/80 text-xs text-gray-700 space-y-1.5">
                <div class="font-semibold text-gray-900">Cara Mengaktifkan Kembali Kapan Saja:</div>
                <p>Buka file <code class="px-1.5 py-0.5 rounded bg-gray-100 font-mono">D:\laragon\www\xoose\.env</code> lalu ubah baris berikut:</p>
                <pre class="p-2.5 rounded-lg bg-[#1A1D1F] text-emerald-300 font-mono text-xs overflow-x-auto">REMOTE_CAPTURE_RULES_ENABLED=true</pre>
            </div>
            <div class="mt-5 flex flex-wrap gap-2.5">
                <a href="{{ route('remote.devices.index') }}" class="px-4 py-2 rounded-xl bg-[#1A1D1F] hover:bg-black text-white text-xs font-semibold">
                    Kelola Devices &amp; 7 Layanan Utama
                </a>
                <a href="{{ route('remote.configs.index') }}" class="px-4 py-2 rounded-xl bg-white hover:bg-gray-50 border border-gray-200 text-gray-800 text-xs font-semibold">
                    Buka Service Config
                </a>
            </div>
        </div>
    @elseif (! $managedApp)
        <div class="mt-8 p-12 text-center border-2 border-dashed border-gray-200 rounded-2xl bg-gray-50/50">
            <h3 class="text-sm font-semibold text-gray-900">Belum ada aplikasi terdaftar</h3>
            <p class="text-xs text-gray-500 mt-1">Silakan daftarkan aplikasi terlebih dahulu di Application Manager.</p>
            <a href="{{ route('remote.apps.index') }}" class="mt-4 inline-flex px-4 py-2 rounded-xl bg-[#1A1D1F] text-white text-xs font-semibold">
                Buka Application Manager
            </a>
        </div>
    @else
        {{-- Scope Switcher (App Default vs Device Override) --}}
        <div class="mt-6 p-4 rounded-2xl border border-gray-200 bg-[#FAFAFB] flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-xs font-semibold text-gray-500 mr-1">Scope Target:</span>
                @foreach ($apps as $appOption)
                    <a
                        href="{{ route('remote.capture-rules.index', ['scope' => 'app-' . $appOption->id]) }}"
                        class="px-3 py-1.5 rounded-lg text-xs font-semibold border transition-colors {{ ($device === null && $managedApp->id === $appOption->id) ? 'bg-[#1A1D1F] text-white border-[#1A1D1F]' : 'bg-white text-gray-700 border-gray-200 hover:bg-gray-50' }}"
                    >
                        App: {{ $appOption->name }}
                    </a>
                @endforeach

                @foreach ($devices as $devOption)
                    <a
                        href="{{ route('remote.capture-rules.index', ['scope' => 'device-' . $devOption->id]) }}"
                        class="px-3 py-1.5 rounded-lg text-xs font-semibold border transition-colors {{ ($device && $device->id === $devOption->id) ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-700 border-gray-200 hover:bg-gray-50' }}"
                    >
                        Device: {{ $devOption->hostname }}
                    </a>
                @endforeach
            </div>

            <div class="text-xs text-gray-500">
                Target:
                @if ($device)
                    <strong class="text-indigo-700">Override {{ $device->hostname }} ({{ $device->device_code }})</strong>
                @else
                    <strong class="text-gray-900">Default Aplikasi {{ $managedApp->name }}</strong>
                @endif
            </div>
        </div>

        {{-- Live Mode Switcher & Runtime Policy --}}
        <div class="mt-6 p-6 rounded-2xl border border-gray-200 bg-white">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-4 border-b border-gray-100">
                <div>
                    <h2 class="text-base font-bold text-gray-900">Live Capture Target Switcher</h2>
                    <p class="text-xs text-gray-500 mt-0.5">Perubahan menerbitkan <span class="font-mono">config_version</span> baru dan langsung disinkronkan oleh LoveYou Agent ke Extension / MSS.</p>
                </div>
                <span class="px-2.5 py-1 rounded-lg bg-gray-100 font-mono text-xs text-gray-700">browser_capture_service</span>
            </div>

            <form method="POST" action="{{ route('remote.capture-rules.policy') }}" class="mt-5 space-y-5">
                @csrf
                <input type="hidden" name="scope" value="{{ $scope }}">

                {{-- 4 Mode Pilihan Sumber Capture --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3">
                    @php
                        $sourceModes = [
                            'browser_domain' => ['label' => 'Browser Domain', 'desc' => 'Isi tab aktif saja via Browser Extension (Chrome, Edge, Firefox)'],
                            'application_window' => ['label' => 'Application Window', 'desc' => 'MSS khusus saat proses & judul jendela tertentu sedang fokus'],
                            'desktop_mss' => ['label' => 'Desktop MSS', 'desc' => 'Mode layar penuh klasik via MSS + Pillow'],
                            'disabled' => ['label' => 'Disabled', 'desc' => 'Nonaktifkan seluruh capture sementara secara live'],
                        ];
                    @endphp
                    @foreach ($sourceModes as $modeKey => $modeMeta)
                        <label class="p-3.5 rounded-xl border cursor-pointer transition-all {{ $activeSource === $modeKey ? 'border-[#1A1D1F] bg-[#FAFAFB] ring-1 ring-[#1A1D1F]' : 'border-gray-200 hover:bg-gray-50' }}">
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-xs font-bold text-gray-900">{{ $modeMeta['label'] }}</span>
                                <input type="radio" name="active_source" value="{{ $modeKey }}" @checked($activeSource === $modeKey)>
                            </div>
                            <p class="text-[11px] text-gray-500 mt-1.5 leading-relaxed">{{ $modeMeta['desc'] }}</p>
                        </label>
                    @endforeach
                </div>

                {{-- Parameter Runtime --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Mode Izin Extension</label>
                        <select name="permission_mode" class="w-full px-3 py-2 rounded-xl border border-gray-200 bg-white text-xs">
                            <option value="local_start" @selected(($bc['permission_mode'] ?? 'local_start') === 'local_start')>Local-Start (Default · activeTab)</option>
                            <option value="automatic_domain" @selected(($bc['permission_mode'] ?? '') === 'automatic_domain')>Automatic Domain (&lt;all_urls&gt;)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Interval Browser (detik)</label>
                        <input
                            type="number"
                            name="capture_interval_seconds"
                            min="2"
                            max="3600"
                            value="{{ (int) ($bc['capture_interval_seconds'] ?? 10) }}"
                            class="w-full px-3 py-2 rounded-xl border border-gray-200 text-xs font-mono"
                        >
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Interval Window MSS (detik)</label>
                        <input
                            type="number"
                            name="window_interval_seconds"
                            min="2"
                            max="3600"
                            value="{{ (int) ($wc['capture_interval_seconds'] ?? 10) }}"
                            class="w-full px-3 py-2 rounded-xl border border-gray-200 text-xs font-mono"
                        >
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Format Gambar Utama</label>
                        <select name="image_format" class="w-full px-3 py-2 rounded-xl border border-gray-200 bg-white text-xs font-mono">
                            <option value="webp" @selected(($bc['image_format'] ?? 'webp') === 'webp')>webp (Fallback: png)</option>
                            <option value="png" @selected(($bc['image_format'] ?? '') === 'png')>png</option>
                        </select>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-6 text-xs text-gray-700">
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="hidden" name="pause_when_browser_unfocused" value="0">
                        <input type="checkbox" name="pause_when_browser_unfocused" value="1" @checked($bc['pause_when_browser_unfocused'] ?? true) class="rounded border-gray-300">
                        <span>Jeda otomatis saat browser tidak fokus</span>
                    </label>
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="hidden" name="pause_when_tab_inactive" value="0">
                        <input type="checkbox" name="pause_when_tab_inactive" value="1" @checked($bc['pause_when_tab_inactive'] ?? true) class="rounded border-gray-300">
                        <span>Jeda otomatis saat tab tidak aktif</span>
                    </label>
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="hidden" name="local_session_required" value="0">
                        <input type="checkbox" name="local_session_required" value="1" @checked($bc['local_session_required'] ?? true) class="rounded border-gray-300">
                        <span>Wajib sesi lokal pengguna</span>
                    </label>
                </div>

                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-2 border-t border-gray-100">
                    <input
                        type="text"
                        name="change_notes"
                        placeholder="Catatan audit opsional (contoh: Beralih ke domain ujian konstantaedu.com)"
                        class="flex-1 px-3 py-2 rounded-xl border border-gray-200 text-xs"
                    >
                    <button type="submit" class="px-5 py-2.5 rounded-xl bg-[#1A1D1F] hover:bg-black text-white text-xs font-semibold cursor-pointer">
                        Terapkan Kebijakan Live
                    </button>
                </div>
            </form>
        </div>

        {{-- Daftar Aturan Domain Browser & Window --}}
        <div class="mt-6 grid grid-cols-1 xl:grid-cols-12 gap-6">
            <div class="xl:col-span-8 space-y-6">
                {{-- Tabel Browser Domain Rules --}}
                <div class="rounded-2xl border border-gray-200 bg-white overflow-x-auto">
                    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-bold text-gray-900">Aturan Domain Browser ({{ count($browserRules) }})</h3>
                            <p class="text-xs text-gray-500">Cocok dengan seluruh path pada domain + opsi subdomain ketat.</p>
                        </div>
                    </div>
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="border-b border-gray-100 text-[11px] uppercase tracking-wider text-gray-400 bg-gray-50/70">
                                <th class="px-4 py-3 font-semibold">Rule & Domain</th>
                                <th class="px-4 py-3 font-semibold">Subdomain / Skema</th>
                                <th class="px-4 py-3 font-semibold">Browser</th>
                                <th class="px-4 py-3 font-semibold">Status</th>
                                <th class="px-4 py-3 font-semibold text-right">Aksi Live</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($browserRules as $rule)
                                <tr class="hover:bg-gray-50/60">
                                    <td class="px-4 py-3">
                                        <div class="font-mono font-bold text-gray-900">{{ $rule['domain'] ?? '-' }}</div>
                                        <div class="font-mono text-[11px] text-gray-400">{{ $rule['id'] ?? '-' }}</div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="px-2 py-0.5 rounded-md {{ !empty($rule['include_subdomains']) ? 'bg-indigo-50 text-indigo-700' : 'bg-gray-100 text-gray-600' }} text-[11px] font-medium">
                                            {{ !empty($rule['include_subdomains']) ? '+ Subdomains' : 'Root Only' }}
                                        </span>
                                        <div class="font-mono text-[11px] text-gray-500 mt-1">{{ implode(', ', $rule['schemes'] ?? ['https']) }}</div>
                                    </td>
                                    <td class="px-4 py-3 font-mono text-[11px] text-gray-700">
                                        {{ implode(', ', $rule['browsers'] ?? []) }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold {{ !empty($rule['enabled']) ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-500' }}">
                                            {{ !empty($rule['enabled']) ? 'Aktif' : 'Nonaktif' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="inline-flex items-center gap-1.5">
                                            <form method="POST" action="{{ route('remote.capture-rules.rules.update', ['ruleId' => $rule['id']]) }}">
                                                @csrf
                                                @method('PUT')
                                                <input type="hidden" name="scope" value="{{ $scope }}">
                                                <input type="hidden" name="rule_type" value="browser">
                                                <input type="hidden" name="enabled" value="{{ !empty($rule['enabled']) ? '0' : '1' }}">
                                                <button type="submit" class="px-2.5 py-1 rounded-lg border border-gray-200 bg-white hover:bg-gray-100 text-[11px] font-semibold cursor-pointer">
                                                    {{ !empty($rule['enabled']) ? 'Nonaktifkan' : 'Aktifkan' }}
                                                </button>
                                            </form>
                                            <form method="POST" action="{{ route('remote.capture-rules.rules.destroy', ['ruleId' => $rule['id']]) }}">
                                                @csrf
                                                @method('DELETE')
                                                <input type="hidden" name="scope" value="{{ $scope }}">
                                                <input type="hidden" name="rule_type" value="browser">
                                                <button type="submit" class="px-2.5 py-1 rounded-lg border border-rose-200 text-rose-700 hover:bg-rose-50 text-[11px] font-semibold cursor-pointer">
                                                    Hapus
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-8 text-center text-gray-400">
                                        Belum ada aturan domain browser. Tambahkan aturan baru di form bawah (contoh: <span class="font-mono">konstantaedu.com</span>).
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Tabel Application Window Rules --}}
                <div class="rounded-2xl border border-gray-200 bg-white overflow-x-auto">
                    <div class="px-5 py-4 border-b border-gray-100">
                        <h3 class="text-sm font-bold text-gray-900">Aturan Jendela Aplikasi MSS ({{ count($windowRules) }})</h3>
                        <p class="text-xs text-gray-500">Digunakan bila mode live disetel ke <span class="font-mono">application_window</span>.</p>
                    </div>
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="border-b border-gray-100 text-[11px] uppercase tracking-wider text-gray-400 bg-gray-50/70">
                                <th class="px-4 py-3 font-semibold">ID & Proses</th>
                                <th class="px-4 py-3 font-semibold">Filter Judul Jendela</th>
                                <th class="px-4 py-3 font-semibold">Status</th>
                                <th class="px-4 py-3 font-semibold text-right">Aksi Live</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($windowRules as $wRule)
                                <tr class="hover:bg-gray-50/60">
                                    <td class="px-4 py-3">
                                        <div class="font-mono font-bold text-gray-900">{{ $wRule['process'] ?? '-' }}</div>
                                        <div class="font-mono text-[11px] text-gray-400">{{ $wRule['id'] ?? '-' }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-gray-700">
                                        {{ ($wRule['window_title_contains'] ?? '') !== '' ? $wRule['window_title_contains'] : '(Semua judul jendela proses ini)' }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold {{ !empty($wRule['enabled']) ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-500' }}">
                                            {{ !empty($wRule['enabled']) ? 'Aktif' : 'Nonaktif' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="inline-flex items-center gap-1.5">
                                            <form method="POST" action="{{ route('remote.capture-rules.rules.update', ['ruleId' => $wRule['id']]) }}">
                                                @csrf
                                                @method('PUT')
                                                <input type="hidden" name="scope" value="{{ $scope }}">
                                                <input type="hidden" name="rule_type" value="window">
                                                <input type="hidden" name="enabled" value="{{ !empty($wRule['enabled']) ? '0' : '1' }}">
                                                <button type="submit" class="px-2.5 py-1 rounded-lg border border-gray-200 bg-white hover:bg-gray-100 text-[11px] font-semibold cursor-pointer">
                                                    {{ !empty($wRule['enabled']) ? 'Nonaktifkan' : 'Aktifkan' }}
                                                </button>
                                            </form>
                                            <form method="POST" action="{{ route('remote.capture-rules.rules.destroy', ['ruleId' => $wRule['id']]) }}">
                                                @csrf
                                                @method('DELETE')
                                                <input type="hidden" name="scope" value="{{ $scope }}">
                                                <input type="hidden" name="rule_type" value="window">
                                                <button type="submit" class="px-2.5 py-1 rounded-lg border border-rose-200 text-rose-700 hover:bg-rose-50 text-[11px] font-semibold cursor-pointer">
                                                    Hapus
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-6 text-center text-gray-400">
                                        Belum ada aturan jendela aplikasi MSS.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Panel Kanan: Status Extension/Izin/Agent & Riwayat Versi + Rollback --}}
            <div class="xl:col-span-4 space-y-6">
                {{-- Status Live Extension, Izin & Agent --}}
                <div class="p-5 rounded-2xl border border-gray-200 bg-white">
                    <h3 class="text-sm font-bold text-gray-900 pb-3 border-b border-gray-100">Status Live Extension & Agent</h3>
                    <div class="mt-3 space-y-3">
                        @forelse ($deviceStatuses as $st)
                            <div class="p-3.5 rounded-xl border border-gray-100 bg-[#FAFAFB] text-xs space-y-1.5">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-bold text-gray-900">{{ $st['device']->hostname }}</span>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $st['extension_connected'] ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-200 text-gray-600' }}">
                                        {{ $st['extension_connected'] ? 'Extension Terhubung' : 'Extension Offline' }}
                                    </span>
                                </div>
                                <div class="flex justify-between text-[11px]">
                                    <span class="text-gray-500">Agent / Config</span>
                                    <span class="font-mono text-gray-800">{{ $st['device']->status }} · v{{ $st['active_config_version'] }}</span>
                                </div>
                                <div class="flex justify-between text-[11px]">
                                    <span class="text-gray-500">Mode / Izin</span>
                                    <span class="font-mono text-gray-800">{{ $st['permission_mode'] }} ({{ $st['has_host_permission'] ? '<all_urls>' : 'activeTab' }})</span>
                                </div>
                                <div class="flex justify-between text-[11px]">
                                    <span class="text-gray-500">Domain / Sesi</span>
                                    <span class="font-mono text-gray-800 truncate max-w-[150px]">{{ $st['matched_domain'] ?? '-' }} ({{ $st['local_session_state'] }})</span>
                                </div>
                                <div class="flex justify-between text-[11px]">
                                    <span class="text-gray-500">Browser / Capture</span>
                                    <span class="font-mono text-gray-800">{{ !empty($st['connected_browsers']) ? implode(',', $st['connected_browsers']) : '-' }} · {{ $st['total_captured'] }}x</span>
                                </div>
                                @if ($st['last_error'])
                                    <div class="text-[11px] text-rose-600 pt-1 border-t border-gray-200/60">{{ $st['last_error'] }}</div>
                                @endif
                            </div>
                        @empty
                            <div class="p-4 text-center text-xs text-gray-400 border border-dashed border-gray-200 rounded-xl">
                                Belum ada perangkat terdaftar untuk aplikasi ini.
                            </div>
                        @endforelse
                    </div>
                </div>

                {{-- Riwayat Versi & Rollback --}}
                <div class="p-5 rounded-2xl border border-gray-200 bg-white">
                    <h3 class="text-sm font-bold text-gray-900 pb-3 border-b border-gray-100">Riwayat Versi & Rollback</h3>
                    <div class="mt-3 space-y-2.5 max-h-80 overflow-y-auto">
                        @forelse ($history as $ver)
                            <div class="p-3 rounded-xl border {{ $ver->is_active ? 'border-emerald-200 bg-emerald-50/40' : 'border-gray-100 bg-[#FAFAFB]' }} text-xs">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-mono font-bold text-gray-900">
                                        v{{ $ver->config_version }}
                                        @if ($ver->is_active)
                                            <span class="ml-1 px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-800 text-[10px]">AKTIF</span>
                                        @endif
                                    </span>
                                    @if (! $ver->is_active)
                                        <form method="POST" action="{{ route('remote.capture-rules.rollback', ['version' => $ver->config_version]) }}">
                                            @csrf
                                            <input type="hidden" name="scope" value="{{ $scope }}">
                                            <button type="submit" class="px-2 py-0.5 rounded-lg border border-gray-300 bg-white hover:bg-gray-100 text-[11px] font-semibold cursor-pointer">
                                                Rollback
                                            </button>
                                        </form>
                                    @endif
                                </div>
                                <div class="text-[11px] text-gray-600 mt-1">{{ $ver->change_notes ?: 'Pembaruan konfigurasi capture' }}</div>
                                <div class="text-[10px] text-gray-400 mt-0.5">
                                    Mode: <span class="font-mono">{{ $ver->config_json['capture_policy']['active_source'] ?? '-' }}</span> · {{ $ver->created_at?->diffForHumans() }}
                                </div>
                            </div>
                        @empty
                            <div class="p-4 text-center text-xs text-gray-400 border border-dashed border-gray-200 rounded-xl">
                                Belum ada riwayat versi capture rules. Simpan perubahan untuk menerbitkan v1.
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        {{-- Form Tambah Aturan Domain & Window --}}
        @include('remote.capture-rules._form')
    @endif
</div>
@endsection
