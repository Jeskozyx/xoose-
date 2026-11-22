@extends('layouts.app')

@section('title', $project->name . ' — Application Builder')

@php
    $activeMenu = 'builder';
@endphp

@section('content')
<div class="h-full flex flex-col overflow-y-auto px-8 py-8" x-data="buildMonitor()">
    {{-- Header --}}
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-6 border-b border-gray-100">
        <div>
            <div class="flex items-center gap-2 text-sm text-gray-500 mb-1">
                <a href="{{ route('dashboard') }}" class="hover:text-gray-900 transition-colors">Xoose</a>
                <span>/</span>
                <a href="{{ route('builder.projects.index') }}" class="hover:text-gray-900 transition-colors">Application Builder</a>
                <span>/</span>
                <span class="text-gray-800 font-medium">{{ $project->slug }}</span>
            </div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold tracking-tight text-gray-900">{{ $project->name }}</h1>
                @php
                    $statusBadge = match($project->status) {
                        'built' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
                        'building' => 'bg-amber-100 text-amber-800 border-amber-200 animate-pulse',
                        'ready' => 'bg-sky-100 text-sky-800 border-sky-200',
                        'failed' => 'bg-rose-100 text-rose-800 border-rose-200',
                        default => 'bg-gray-100 text-gray-700 border-gray-200',
                    };
                @endphp
                <span class="px-2.5 py-0.5 text-xs font-semibold rounded-full border {{ $statusBadge }}">
                    {{ ucfirst($project->status) }}
                </span>
            </div>
            <p class="text-xs text-gray-500 mt-1 font-mono">Workspace: {{ $project->workspace_path }}</p>
        </div>

        <div class="flex items-center gap-2">
            <a
                href="{{ route('builder.editor.show', $project) }}"
                class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl border border-gray-200 text-xs font-semibold text-gray-700 hover:bg-gray-50 transition-colors"
            >
                <svg class="w-4 h-4 text-gray-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                <span>Edit Source/Template</span>
            </a>
            <a
                href="{{ route('builder.projects.edit', $project) }}"
                class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl border border-gray-200 text-xs font-semibold text-gray-700 hover:bg-gray-50 transition-colors"
            >
                <svg class="w-4 h-4 text-gray-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                <span>Ubah Konfigurasi</span>
            </a>
        </div>
    </div>

    {{-- Flash Messages --}}
    @if (session('success'))
        <div class="mt-6 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm">
            {{ session('success') }}
        </div>
    @endif
    @if (session('error'))
        <div class="mt-6 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm">
            {{ session('error') }}
        </div>
    @endif

    {{-- Main Grid --}}
    <div class="mt-8 grid grid-cols-1 lg:grid-cols-3 gap-6 pb-16">
        {{-- Kolom Kiri: Panel Kontrol Build & Monitor --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Form Trigger Build --}}
            <div class="p-6 rounded-2xl border border-gray-200 bg-white">
                <div class="flex items-center justify-between pb-4 border-b border-gray-100">
                    <div>
                        <h2 class="text-base font-bold text-gray-900">Jalankan PyInstaller Build</h2>
                        <p class="text-xs text-gray-500 mt-0.5">Kompilasi workspace terisolasi menjadi file executable Windows (.exe).</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('builder.builds.store', $project) }}" class="mt-4 flex flex-wrap items-center gap-3">
                    @csrf
                    <div>
                        <label for="format" class="sr-only">Format Distribusi</label>
                        <select name="format" id="format" class="px-3.5 py-2 rounded-xl border border-gray-300 text-xs font-semibold bg-white focus:outline-none focus:ring-2 focus:ring-black">
                            <option value="onedir" selected>Mode onedir (Folder Distribusi - Cepat & Stabil)</option>
                            <option value="onefile">Mode onefile (Single Portable EXE)</option>
                        </select>
                    </div>

                    <label class="inline-flex items-center gap-1.5 text-xs text-gray-600 cursor-pointer">
                        <input type="checkbox" name="sync" value="1" class="rounded text-black focus:ring-black">
                        <span>Jalankan Langsung (Sync)</span>
                    </label>

                    <button
                        type="submit"
                        class="px-5 py-2 rounded-xl bg-[#1A1D1F] text-white text-xs font-semibold hover:bg-black transition-colors shadow-sm ml-auto"
                    >
                        ⚡ Mulai Kompilasi Build
                    </button>
                </form>
            </div>

            {{-- Terminal Live Log Monitor --}}
            @php $latestBuild = $project->latestBuild; @endphp
            <div class="p-6 rounded-2xl border border-gray-200 bg-white">
                <div class="flex items-center justify-between pb-4 border-b border-gray-100">
                    <div class="flex items-center gap-2">
                        <div class="w-3 h-3 rounded-full {{ $latestBuild && $latestBuild->status === 'building' ? 'bg-amber-400 animate-ping' : ($latestBuild && $latestBuild->isSuccessful() ? 'bg-emerald-500' : 'bg-gray-300') }}"></div>
                        <h2 class="text-base font-bold text-gray-900">
                            Build Monitor
                            @if ($latestBuild)
                                <span class="text-xs font-mono text-gray-500">#{{ $latestBuild->id }} ({{ strtoupper($latestBuild->format) }})</span>
                            @endif
                        </h2>
                    </div>

                    @if ($latestBuild && $latestBuild->isSuccessful())
                        <a
                            href="{{ route('builder.builds.download', $latestBuild) }}"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-600 text-white text-xs font-semibold hover:bg-emerald-700 transition-colors shadow-sm"
                        >
                            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                            <span>Unduh Executable (.exe)</span>
                        </a>
                    @endif
                </div>

                {{-- Status Bar --}}
                @if ($latestBuild)
                    <div class="mt-4 p-3 rounded-xl bg-gray-50 border border-gray-200 text-xs flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <span class="text-gray-500">Status:</span>
                            <span class="font-bold uppercase text-gray-900 ml-1" id="build-status-text">{{ $latestBuild->status }}</span>
                        </div>
                        <div>
                            <span class="text-gray-500">Exit Code:</span>
                            <span class="font-mono text-gray-900 ml-1" id="build-exit-code">{{ $latestBuild->exit_code ?? '-' }}</span>
                        </div>
                        <div>
                            <span class="text-gray-500">Waktu Mulai:</span>
                            <span class="font-mono text-gray-700 ml-1">{{ $latestBuild->started_at?->format('H:i:s d/m/Y') ?? '-' }}</span>
                        </div>
                        @if ($latestBuild->finished_at)
                            <div>
                                <span class="text-gray-500">Selesai:</span>
                                <span class="font-mono text-gray-700 ml-1">{{ $latestBuild->finished_at->format('H:i:s d/m/Y') }}</span>
                            </div>
                        @endif
                    </div>
                @endif

                {{-- Console Window --}}
                <div class="mt-4 rounded-xl bg-[#0D1117] text-gray-200 p-4 font-mono text-xs overflow-x-auto h-[360px] flex flex-col justify-between border border-gray-800">
                    <pre class="overflow-y-auto flex-1 leading-relaxed whitespace-pre-wrap select-all font-mono" id="build-terminal-log">@if ($latestBuild && $latestBuild->log_path && file_exists($latestBuild->log_path)){{ file_get_contents($latestBuild->log_path) }}@else[INFO] Tidak ada proses build yang sedang aktif. Klik 'Mulai Kompilasi Build' untuk menghasilkan .exe.@endif</pre>
                    <div class="pt-2 border-t border-gray-800 text-[11px] text-gray-400 flex justify-between items-center">
                        <div class="flex items-center gap-2">
                            <span>Terminal Log Live Stream (Sanitized)</span>
                            <span id="log-polling-indicator" class="text-gray-500">● idle</span>
                        </div>
                        <button
                            type="button"
                            onclick="navigator.clipboard.writeText(document.getElementById('build-terminal-log').textContent); alert('Log berhasil disalin ke clipboard!');"
                            class="px-2 py-0.5 rounded bg-gray-800 text-gray-300 hover:text-white hover:bg-gray-700 transition-colors text-[10px]"
                        >
                            📋 Salin Log
                        </button>
                    </div>
                </div>
            </div>

            {{-- Riwayat Build --}}
            <div class="p-6 rounded-2xl border border-gray-200 bg-white">
                <h2 class="text-base font-bold text-gray-900 pb-3 border-b border-gray-100">Riwayat Kompilasi (Build History)</h2>
                @if ($project->builds->isEmpty())
                    <p class="text-xs text-gray-400 py-4 text-center">Belum ada riwayat build untuk proyek ini.</p>
                @else
                    <div class="mt-4 divide-y divide-gray-100">
                        @foreach ($project->builds as $b)
                            <div class="py-3 flex items-center justify-between text-xs">
                                <div class="flex items-center gap-3">
                                    <span class="font-mono font-bold text-gray-800">#{{ $b->id }}</span>
                                    <span class="px-2 py-0.5 rounded text-[11px] uppercase font-semibold {{ $b->isSuccessful() ? 'bg-emerald-100 text-emerald-800' : ($b->status === 'building' ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-700') }}">
                                        {{ $b->status }}
                                    </span>
                                    <span class="text-gray-500">Format: {{ $b->format }}</span>
                                    <span class="text-gray-400 font-mono">{{ $b->started_at?->diffForHumans() }}</span>
                                </div>
                                <div>
                                    @if ($b->isSuccessful())
                                        <a href="{{ route('builder.builds.download', $b) }}" class="text-emerald-600 font-semibold hover:underline">
                                            Unduh
                                        </a>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        {{-- Kolom Kanan: Detail Konfigurasi & Revisi --}}
        <div class="space-y-6">
            {{-- Kartu Info Konfigurasi --}}
            <div class="p-6 rounded-2xl border border-gray-200 bg-white">
                <h2 class="text-base font-bold text-gray-900 pb-3 border-b border-gray-100">Parameter Terkini</h2>
                <div class="mt-4 space-y-3 text-xs">
                    <div>
                        <span class="text-gray-400 block text-[11px]">Nama Display:</span>
                        <span class="font-semibold text-gray-800">{{ $project->name }}</span>
                    </div>
                    <div>
                        <span class="text-gray-400 block text-[11px]">Interval Screenshot:</span>
                        <span class="font-semibold text-gray-800">{{ $project->configuration['interval_seconds'] ?? 3 }} Detik</span>
                    </div>
                    <div>
                        <span class="text-gray-400 block text-[11px]">Format & Kualitas Gambar:</span>
                        <span class="font-semibold text-gray-800">{{ strtoupper($project->configuration['image_format'] ?? 'jpg') }} ({{ $project->configuration['jpg_quality'] ?? 80 }}%)</span>
                    </div>
                    <div>
                        <span class="text-gray-400 block text-[11px]">Auto Start (Windows Registry):</span>
                        <span class="font-semibold {{ !empty($project->features['auto_start']) ? 'text-emerald-600' : 'text-gray-400' }}">
                            {{ !empty($project->features['auto_start']) ? 'Aktif (Opt-in)' : 'Nonaktif' }}
                        </span>
                    </div>
                    <div>
                        <span class="text-gray-400 block text-[11px]">Tray Notification:</span>
                        <span class="font-semibold {{ !empty($project->features['notifications']) ? 'text-emerald-600' : 'text-gray-400' }}">
                            {{ !empty($project->features['notifications']) ? 'Aktif' : 'Nonaktif' }}
                        </span>
                    </div>
                    <div class="pt-2 border-t border-gray-100">
                        <span class="text-gray-400 block text-[11px]">Supabase Project URL:</span>
                        <span class="font-mono text-gray-700 truncate block">{{ $project->configuration['supabase_url'] ?? '-' }}</span>
                    </div>
                </div>
            </div>

            {{-- Riwayat Revisi --}}
            <div class="p-6 rounded-2xl border border-gray-200 bg-white">
                <h2 class="text-base font-bold text-gray-900 pb-3 border-b border-gray-100">Riwayat Revisi (Snapshots)</h2>
                <div class="mt-4 divide-y divide-gray-100">
                    @foreach ($project->revisions as $rev)
                        <div class="py-3 text-xs">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-gray-800">Revisi #{{ $rev->revision_number }}</span>
                                <span class="text-[11px] text-gray-400 font-mono">{{ $rev->created_at->diffForHumans() }}</span>
                            </div>
                            <p class="text-gray-500 mt-0.5 text-[11px]">{{ $rev->change_summary }}</p>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Kartu Manajemen Sumber Daya Supabase --}}
            <div class="p-6 rounded-2xl border border-rose-100 bg-white">
                <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                    <h2 class="text-base font-bold text-gray-900">Sumber Daya Supabase</h2>
                    <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-rose-50 text-rose-700">Storage & Cloud</span>
                </div>
                <p class="text-xs text-gray-500 mt-2.5 leading-relaxed">
                    Hapus rekaman database screenshot, file gambar storage, atau akun pengguna di Supabase untuk menghemat kuota cloud.
                </p>
                <div class="mt-4 pt-3 border-t border-gray-100 flex flex-col gap-2">
                    <button
                        type="button"
                        onclick="openCleanupModal()"
                        class="w-full inline-flex items-center justify-center gap-1.5 px-3.5 py-2.5 rounded-xl bg-rose-50 hover:bg-rose-100 border border-rose-200 text-rose-700 text-xs font-semibold transition-colors cursor-pointer"
                    >
                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                        <span>Bersihkan Data Supabase</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

@include('builder.partials.cleanup-modal')

@if ($latestBuild && $latestBuild->status === 'building')
<script>
    // Live polling for ongoing build
    const buildId = {{ $latestBuild->id }};
    const statusUrl = "{{ route('builder.builds.status', $latestBuild) }}";
    const terminalEl = document.getElementById('build-terminal-log');
    const statusTextEl = document.getElementById('build-status-text');
    const indicatorEl = document.getElementById('log-polling-indicator');

    const pollInterval = setInterval(async () => {
        try {
            indicatorEl.textContent = '● polling...';
            const res = await fetch(statusUrl);
            if (!res.ok) return;
            const data = await res.json();

            statusTextEl.textContent = data.status;
            if (data.log) {
                terminalEl.textContent = data.log;
                terminalEl.scrollTop = terminalEl.scrollHeight;
            }

            if (data.status === 'succeeded' || data.status === 'failed' || data.status === 'cancelled') {
                clearInterval(pollInterval);
                indicatorEl.textContent = '● finished';
                setTimeout(() => window.location.reload(), 1200);
            }
        } catch (e) {
            console.error('Polling error', e);
        }
    }, 2000);
</script>
@endif
@endsection
