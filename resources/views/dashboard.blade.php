{{--
    ==========================================================================
    Halaman Dashboard Operasional Xoose Control Plane
    Path: resources/views/dashboard.blade.php
    Extends: layouts.app (otomatis memuat <x-sidebar />)
    ==========================================================================
--}}

@extends('layouts.app')

@section('title', config('app.name', 'Xoose') . ' — Overview')

@php
    $activeMenu = 'dashboard';
    $folders = $folders ?? [];
    $recentProjects = $recentProjects ?? collect();
    $recentDevices = $recentDevices ?? collect();
    $metrics = $metrics ?? [
        'folders_count'          => count($folders),
        'total_screenshots'      => collect($folders)->sum(fn ($f) => (int) ($f['items'] ?? 0)),
        'projects_count'         => 0,
        'built_projects_count'   => 0,
        'managed_apps_count'     => 0,
        'devices_count'          => 0,
        'online_devices_count'   => 0,
        'pending_commands_count' => 0,
    ];
    $ecosystem = $ecosystem ?? [
        'supabase_host'  => 'supabase.co',
        'template_ready' => false,
        'engine_ready'   => false,
    ];
@endphp

@push('styles')
    <style>
        @keyframes folderEntrance {
            0% { opacity: 0; transform: translateY(8px); }
            100% { opacity: 1; transform: translateY(0); }
        }

        .new-folder-enter {
            animation: folderEntrance 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        @keyframes pulseBorder {
            0% { border-color: #10B981; box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.16); }
            100% { border-color: #E6E8EC; box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
        }

        .folder-pulse {
            animation: pulseBorder 1.1s ease-out;
        }

        .counter-pulse {
            color: #059669 !important;
        }
    </style>
@endpush

@section('content')
    {{-- =====================================================================
         TOP BAR — Minimalist Operational Header
         ===================================================================== --}}
    <header class="flex items-center justify-between gap-4 px-5 sm:px-8 h-[62px] border-b border-[#EAECEF] bg-white shrink-0">
        <div class="flex items-center gap-3 flex-1 min-w-0 max-w-[460px]">
            <button
                type="button"
                data-sidebar-toggle
                class="inline-flex items-center justify-center w-8 h-8 rounded-lg border border-[#E6E8EC] bg-white text-[#475467] hover:text-[#111315] hover:bg-[#F7F8FA] transition-colors shrink-0 cursor-pointer"
                aria-label="Toggle Sidebar"
                title="Collapse / Expand Sidebar (Ctrl+B)"
            >
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="4" width="18" height="16" rx="2.5"/>
                    <line x1="9" y1="4" x2="9" y2="20"/>
                </svg>
            </button>

            <label class="relative flex items-center w-full h-[36px] rounded-lg bg-[#F7F8FA] border border-[#E6E8EC] px-3 gap-2 focus-within:bg-white focus-within:border-[#98A2B3] transition-colors cursor-text">
                <svg class="w-3.5 h-3.5 text-[#8E959F] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="7"/>
                    <path d="M20 20L16.2 16.2"/>
                </svg>
                <input
                    id="dashboard-search-input"
                    type="text"
                    placeholder="Cari folder user, proyek, atau perangkat..."
                    autocomplete="off"
                    class="w-full bg-transparent text-[13px] text-[#111315] placeholder-[#8E959F] focus:outline-none"
                />
                <span class="hidden sm:inline-flex items-center gap-0.5 shrink-0 select-none">
                    <kbd class="inline-flex items-center justify-center px-1.5 h-[20px] rounded bg-white border border-[#E4E7EC] font-mono text-[10.5px] text-[#667085]">⌘F</kbd>
                </span>
            </label>
        </div>

        <div class="flex items-center gap-2.5 shrink-0">
            {{-- Indikator Status Supabase Realtime --}}
            <div
                id="dashboard-realtime-pill"
                class="inline-flex items-center gap-2 h-[34px] px-3 rounded-lg border border-[#E6E8EC] bg-[#FAFAFB] text-[12px] font-medium text-[#475467]"
                title="Koneksi Supabase Realtime untuk user_responses & hasil_ss"
            >
                <span id="realtime-dot" class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                <span id="realtime-label" class="hidden sm:inline">Supabase Live</span>
                <span class="sm:hidden">Live</span>
            </div>

            <button
                type="button"
                id="open-new-folder-modal-btn"
                class="inline-flex items-center gap-1.5 h-[34px] px-3 rounded-lg border border-[#E6E8EC] bg-white hover:bg-[#F7F8FA] text-[#111315] text-[12.5px] font-medium transition-colors cursor-pointer"
            >
                <svg class="w-3.5 h-3.5 text-[#667085]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                    <path d="M12 5v14M5 12h14"/>
                </svg>
                <span class="hidden sm:inline">Folder</span>
            </button>

            <a
                href="{{ route('builder.projects.create') }}"
                class="inline-flex items-center gap-1.5 h-[34px] px-3.5 rounded-lg bg-[#111315] hover:bg-black text-white text-[12.5px] font-medium transition-colors"
            >
                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                    <path d="M12 5v14M5 12h14"/>
                </svg>
                <span>Proyek Baru</span>
            </a>
        </div>
    </header>

    {{-- =====================================================================
         MAIN OPERATIONAL WORKSPACE
         ===================================================================== --}}
    <div class="flex-1 overflow-y-auto bg-white">
        <div class="max-w-[1360px] mx-auto px-5 sm:px-8 py-7 space-y-7">

            {{-- Page Title --}}
            <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-3">
                <div>
                    <h1 class="text-[22px] sm:text-[24px] font-semibold tracking-[-0.025em] text-[#111315] leading-tight">
                        Overview
                    </h1>
                    <p class="text-[13px] text-[#667085] mt-1">
                        Ringkasan tangkapan layar Supabase, proyek App Builder, dan status agen LoveYou.
                    </p>
                </div>

                <div class="flex items-center gap-2 text-[12px] text-[#667085]">
                    <span class="font-mono text-[11.5px] px-2.5 py-1 rounded-md bg-[#F7F8FA] border border-[#EAECEF] text-[#475467]">
                        {{ $ecosystem['supabase_host'] }}
                    </span>
                </div>
            </div>

            {{-- Segmented Telemetry Strip (Hairline-divided, zero decorative clutter) --}}
            <section aria-label="Ringkasan Metrik">
                <div class="grid grid-cols-2 lg:grid-cols-4 rounded-xl border border-[#E6E8EC] bg-[#FAFAFB]/70 divide-y lg:divide-y-0 sm:divide-x divide-[#EAECEF] overflow-hidden">
                    <div class="px-5 py-4">
                        <div class="text-[11.5px] font-medium text-[#667085]">User Folders</div>
                        <div class="mt-1.5 flex items-baseline gap-2">
                            <span id="metric-folders-count" class="text-[24px] font-semibold tracking-[-0.02em] text-[#111315] tabular-nums">
                                {{ $metrics['folders_count'] }}
                            </span>
                            <span class="text-[11.5px] font-mono text-[#8E959F]">user_responses</span>
                        </div>
                    </div>

                    <div class="px-5 py-4">
                        <div class="text-[11.5px] font-medium text-[#667085]">Total Captures</div>
                        <div class="mt-1.5 flex items-baseline gap-2">
                            <span id="metric-captures-count" class="text-[24px] font-semibold tracking-[-0.02em] text-[#111315] tabular-nums">
                                {{ number_format($metrics['total_screenshots']) }}
                            </span>
                            <span class="text-[11.5px] font-mono text-[#8E959F]">hasil_ss</span>
                        </div>
                    </div>

                    <div class="px-5 py-4">
                        <div class="text-[11.5px] font-medium text-[#667085]">Builder Projects</div>
                        <div class="mt-1.5 flex items-baseline gap-2">
                            <span class="text-[24px] font-semibold tracking-[-0.02em] text-[#111315] tabular-nums">
                                {{ $metrics['projects_count'] }}
                            </span>
                            <span class="text-[11.5px] text-[#667085] tabular-nums">
                                {{ $metrics['built_projects_count'] }} ter-build
                            </span>
                        </div>
                    </div>

                    <div class="px-5 py-4">
                        <div class="text-[11.5px] font-medium text-[#667085]">Remote Devices</div>
                        <div class="mt-1.5 flex items-baseline gap-2">
                            @auth
                                <span class="text-[24px] font-semibold tracking-[-0.02em] text-[#111315] tabular-nums">
                                    {{ $metrics['online_devices_count'] }}
                                </span>
                                <span class="text-[12px] text-[#667085] tabular-nums">
                                    / {{ $metrics['devices_count'] }} online
                                </span>
                                @if ($metrics['pending_commands_count'] > 0)
                                    <span class="ml-auto text-[11px] font-medium text-amber-700 bg-amber-50 border border-amber-200/80 px-1.5 py-0.5 rounded tabular-nums">
                                        {{ $metrics['pending_commands_count'] }} antre
                                    </span>
                                @endif
                            @else
                                <span class="text-[15px] font-medium text-[#667085]">Terkunci</span>
                                <a href="{{ route('login') }}" class="text-[12px] font-medium text-[#111315] underline underline-offset-4 hover:text-black">
                                    Login
                                </a>
                            @endauth
                        </div>
                    </div>
                </div>
            </section>

            {{-- Asymmetric Main Grid: 8 cols Folders + 4 cols Operations Rail --}}
            <div class="grid grid-cols-1 xl:grid-cols-12 gap-7 items-start">

                {{-- =========================================================
                     KOLOM KIRI (8): SUPABASE USER FOLDERS
                     ========================================================= --}}
                <section class="xl:col-span-8 space-y-4" aria-labelledby="folders-heading">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex items-center gap-2.5">
                            <h2 id="folders-heading" class="text-[15px] font-semibold text-[#111315]">
                                User Folders
                            </h2>
                            <span id="folders-count" class="inline-flex items-center justify-center min-w-[22px] h-[20px] px-1.5 rounded-md bg-[#F2F4F7] text-[#475467] font-mono text-[11.5px] font-medium tabular-nums">
                                {{ count($folders) }}
                            </span>
                        </div>

                        <div class="flex items-center gap-2">
                            {{-- Segmented Filter --}}
                            <div class="inline-flex items-center p-0.5 rounded-lg bg-[#F4F5F7] border border-[#EAECEF]" role="tablist" aria-label="Filter folder">
                                <button
                                    type="button"
                                    data-folder-filter="all"
                                    class="folder-filter-tab px-2.5 py-1 rounded-md text-[12px] font-medium bg-white text-[#111315] shadow-[0_1px_2px_rgba(16,24,40,0.06)] transition-all cursor-pointer"
                                >
                                    Semua
                                </button>
                                <button
                                    type="button"
                                    data-folder-filter="with-items"
                                    class="folder-filter-tab px-2.5 py-1 rounded-md text-[12px] font-medium text-[#667085] hover:text-[#111315] transition-all cursor-pointer"
                                >
                                    Ada Capture
                                </button>
                                <button
                                    type="button"
                                    data-folder-filter="empty"
                                    class="folder-filter-tab px-2.5 py-1 rounded-md text-[12px] font-medium text-[#667085] hover:text-[#111315] transition-all cursor-pointer"
                                >
                                    Kosong
                                </button>
                            </div>

                            <a
                                href="{{ route('folders.index') }}"
                                class="inline-flex items-center gap-1 text-[12.5px] font-medium text-[#475467] hover:text-[#111315] px-2 py-1 rounded-md hover:bg-[#F7F8FA] transition-colors"
                            >
                                <span>Galeri</span>
                                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M5 12h14M12 5l7 7-7 7"/>
                                </svg>
                            </a>
                        </div>
                    </div>

                    <div id="folders-grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3.5">
                        @foreach ($folders as $folder)
                            <x-folder-card :folder="$folder" />
                        @endforeach

                        {{-- Slot Tambah Folder Baru --}}
                        <button
                            type="button"
                            id="new-folder-btn"
                            class="min-h-[116px] rounded-xl border border-dashed border-[#D6DCE5] hover:border-[#98A2B3] hover:bg-[#FAFAFB] flex flex-col items-center justify-center gap-1.5 text-[#667085] hover:text-[#111315] transition-colors cursor-pointer"
                            aria-label="Buat Folder Baru"
                        >
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                                <path d="M12 5v14M5 12h14"/>
                            </svg>
                            <span class="text-[12.5px] font-medium">Tambah Folder</span>
                        </button>
                    </div>

                    {{-- Empty state jika belum ada data di Supabase --}}
                    <div id="folders-empty-hint" class="{{ count($folders) === 0 ? '' : 'hidden' }} py-10 px-6 text-center rounded-xl border border-dashed border-[#E4E7EC] bg-[#FAFAFB]/50">
                        <p class="text-[13.5px] font-medium text-[#111315]">Belum ada folder user dari Supabase</p>
                        <p class="text-[12.5px] text-[#667085] mt-1">
                            Folder muncul otomatis saat klien LoveYou mengirim data ke tabel <code class="font-mono text-[11.5px] px-1 py-0.5 rounded bg-[#F2F4F7]">user_responses</code>.
                        </p>
                    </div>

                    {{-- Empty state hasil filter/pencarian --}}
                    <div id="folders-no-match" class="hidden py-10 px-6 text-center rounded-xl border border-[#EAECEF] bg-[#FAFAFB]/50">
                        <p class="text-[13.5px] font-medium text-[#111315]">Tidak ada folder yang cocok</p>
                        <button
                            type="button"
                            id="clear-folder-search-btn"
                            class="mt-2 text-[12.5px] font-medium text-[#475467] underline underline-offset-4 hover:text-[#111315] cursor-pointer"
                        >
                            Reset pencarian & filter
                        </button>
                    </div>
                </section>

                {{-- =========================================================
                     KOLOM KANAN (4): OPERATIONS RAIL (BUILDER + REMOTE + SISTEM)
                     ========================================================= --}}
                <aside class="xl:col-span-4 space-y-5" aria-label="Panel Operasional">

                    {{-- Panel 1: App Builder Projects --}}
                    <div class="rounded-xl border border-[#E6E8EC] bg-white overflow-hidden">
                        <div class="flex items-center justify-between px-4 py-3 border-b border-[#F0F1F3]">
                            <div class="flex items-center gap-2">
                                <h2 class="text-[13.5px] font-semibold text-[#111315]">App Builder</h2>
                                <span class="font-mono text-[11px] text-[#667085] bg-[#F4F5F7] px-1.5 py-0.5 rounded tabular-nums">
                                    {{ $metrics['projects_count'] }}
                                </span>
                            </div>
                            <a href="{{ route('builder.projects.index') }}" class="text-[12px] font-medium text-[#667085] hover:text-[#111315] transition-colors">
                                Kelola →
                            </a>
                        </div>

                        @if ($recentProjects->isEmpty())
                            <div class="p-5 text-center">
                                <p class="text-[12.5px] text-[#667085]">Belum ada proyek build LoveYou.</p>
                                <a
                                    href="{{ route('builder.projects.create') }}"
                                    class="inline-flex items-center gap-1 mt-2.5 text-[12.5px] font-medium text-[#111315] underline underline-offset-4 hover:text-black"
                                >
                                    Buat proyek pertama
                                </a>
                            </div>
                        @else
                            <ul class="divide-y divide-[#F2F3F5]">
                                @foreach ($recentProjects as $project)
                                    @php
                                        $dotColor = match ($project->status) {
                                            'built'    => 'bg-emerald-500',
                                            'building' => 'bg-amber-500',
                                            'failed'   => 'bg-rose-500',
                                            default    => 'bg-[#98A2B3]',
                                        };
                                    @endphp
                                    <li class="dashboard-searchable-row px-4 py-3 hover:bg-[#FAFAFB] transition-colors" data-search-text="{{ strtolower($project->name . ' ' . $project->slug) }}">
                                        <div class="flex items-center justify-between gap-2">
                                            <a href="{{ route('builder.projects.show', $project) }}" class="min-w-0 flex-1 group">
                                                <div class="flex items-center gap-2">
                                                    <span class="w-1.5 h-1.5 rounded-full shrink-0 {{ $dotColor }}"></span>
                                                    <span class="text-[13px] font-medium text-[#111315] group-hover:underline truncate">
                                                        {{ $project->name }}
                                                    </span>
                                                </div>
                                                <div class="mt-0.5 pl-3.5 flex items-center gap-2 text-[11.5px] text-[#7A818A] font-mono truncate">
                                                    <span>{{ $project->slug }}</span>
                                                    @if ($project->latestBuild)
                                                        <span>·</span>
                                                        <span>{{ $project->latestBuild->format }}</span>
                                                    @endif
                                                </div>
                                            </a>

                                            <a
                                                href="{{ route('builder.editor.show', $project) }}"
                                                class="shrink-0 px-2 py-1 rounded-md border border-[#E6E8EC] bg-white hover:bg-[#F4F5F7] text-[11.5px] font-medium text-[#475467] hover:text-[#111315] transition-colors"
                                            >
                                                Editor
                                            </a>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>

                    {{-- Panel 2: Remote Control Plane --}}
                    <div class="rounded-xl border border-[#E6E8EC] bg-white overflow-hidden">
                        <div class="flex items-center justify-between px-4 py-3 border-b border-[#F0F1F3]">
                            <div class="flex items-center gap-2">
                                <h2 class="text-[13.5px] font-semibold text-[#111315]">Remote Control</h2>
                                @auth
                                    <span class="font-mono text-[11px] text-[#667085] bg-[#F4F5F7] px-1.5 py-0.5 rounded tabular-nums">
                                        {{ $metrics['online_devices_count'] }}/{{ $metrics['devices_count'] }}
                                    </span>
                                @endauth
                            </div>
                            @auth
                                <a href="{{ route('remote.devices.index') }}" class="text-[12px] font-medium text-[#667085] hover:text-[#111315] transition-colors">
                                    Perangkat →
                                </a>
                            @endauth
                        </div>

                        @auth
                            @if ($recentDevices->isEmpty())
                                <div class="p-5 text-center">
                                    <p class="text-[12.5px] text-[#667085]">Belum ada perangkat LoveYou terdaftar.</p>
                                    <a href="{{ route('remote.apps.index') }}" class="inline-flex items-center mt-2 text-[12.5px] font-medium text-[#111315] underline underline-offset-4">
                                        Kelola Aplikasi & Enrollment
                                    </a>
                                </div>
                            @else
                                <ul class="divide-y divide-[#F2F3F5]">
                                    @foreach ($recentDevices as $device)
                                        @php $online = $device->isOnline(); @endphp
                                        <li class="dashboard-searchable-row" data-search-text="{{ strtolower(($device->hostname ?? '') . ' ' . ($device->managedApp?->name ?? '')) }}">
                                            <a href="{{ route('remote.devices.show', $device) }}" class="flex items-center justify-between gap-2 px-4 py-3 hover:bg-[#FAFAFB] transition-colors">
                                                <div class="min-w-0">
                                                    <div class="flex items-center gap-2">
                                                        <span class="w-1.5 h-1.5 rounded-full shrink-0 {{ $online ? 'bg-emerald-500' : 'bg-[#CBD5E1]' }}"></span>
                                                        <span class="text-[13px] font-medium text-[#111315] truncate">
                                                            {{ $device->hostname ?: $device->device_code }}
                                                        </span>
                                                    </div>
                                                    <div class="mt-0.5 pl-3.5 text-[11.5px] text-[#7A818A] truncate">
                                                        {{ $device->managedApp?->name ?? 'LoveYou' }} · v{{ $device->app_version ?: '1.0.0' }}
                                                    </div>
                                                </div>
                                                <span class="shrink-0 text-[11px] font-mono text-[#667085] tabular-nums">
                                                    {{ $device->last_seen_at ? $device->last_seen_at->diffForHumans(short: true) : '—' }}
                                                </span>
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif

                            {{-- Quick links ke halaman Control Plane --}}
                            <div class="grid grid-cols-3 divide-x divide-[#F0F1F3] border-t border-[#F0F1F3] bg-[#FAFAFB] text-center">
                                <a href="{{ route('remote.apps.index') }}" class="py-2.5 text-[11.5px] font-medium text-[#475467] hover:text-[#111315] hover:bg-white transition-colors">Apps</a>
                                <a href="{{ route('remote.configs.index') }}" class="py-2.5 text-[11.5px] font-medium text-[#475467] hover:text-[#111315] hover:bg-white transition-colors">Configs</a>
                                <a href="{{ route('remote.logs.index') }}" class="py-2.5 text-[11.5px] font-medium text-[#475467] hover:text-[#111315] hover:bg-white transition-colors">Logs</a>
                            </div>
                        @else
                            <div class="p-5">
                                <p class="text-[12.5px] text-[#667085] leading-relaxed">
                                    Masuk sebagai admin untuk mengontrol layanan agen, konfigurasi runtime, perintah remote, dan rilis LoveYou.
                                </p>
                                <a
                                    href="{{ route('login') }}"
                                    class="mt-3.5 inline-flex items-center justify-center w-full h-9 px-3 rounded-lg bg-[#111315] hover:bg-black text-white text-[12.5px] font-medium transition-colors"
                                >
                                    Masuk Admin Control Plane
                                </a>
                            </div>
                        @endauth
                    </div>

                    {{-- Panel 3: Status 3 Sistem Terintegrasi --}}
                    <div class="rounded-xl border border-[#E6E8EC] bg-[#FAFAFB]/60 p-4">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.07em] text-[#8E959F] mb-3">
                            Integrasi Sistem
                        </div>
                        <dl class="space-y-2.5 text-[12.5px]">
                            <div class="flex items-center justify-between gap-2">
                                <dt class="text-[#475467]">Supabase API</dt>
                                <dd class="inline-flex items-center gap-1.5 font-mono text-[11.5px] text-[#111315]">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                    <span>Terhubung</span>
                                </dd>
                            </div>
                            <div class="flex items-center justify-between gap-2">
                                <dt class="text-[#475467]">Template LoveYou</dt>
                                <dd class="inline-flex items-center gap-1.5 font-mono text-[11.5px] {{ $ecosystem['template_ready'] ? 'text-[#111315]' : 'text-rose-600' }}">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $ecosystem['template_ready'] ? 'bg-emerald-500' : 'bg-rose-500' }}"></span>
                                    <span>{{ $ecosystem['template_ready'] ? 'Siap' : 'Tidak ditemukan' }}</span>
                                </dd>
                            </div>
                            <div class="flex items-center justify-between gap-2">
                                <dt class="text-[#475467]">Builder Engine</dt>
                                <dd class="inline-flex items-center gap-1.5 font-mono text-[11.5px] {{ $ecosystem['engine_ready'] ? 'text-[#111315]' : 'text-rose-600' }}">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $ecosystem['engine_ready'] ? 'bg-emerald-500' : 'bg-rose-500' }}"></span>
                                    <span>{{ $ecosystem['engine_ready'] ? 'Siap' : 'Tidak ditemukan' }}</span>
                                </dd>
                            </div>
                        </dl>
                    </div>
                </aside>
            </div>
        </div>
    </div>

    {{-- =====================================================================
         MODAL TAMBAH FOLDER BARU (Pengganti window.prompt)
         ===================================================================== --}}
    <div
        id="new-folder-modal"
        class="fixed inset-0 z-50 hidden items-center justify-center bg-[#111315]/35 backdrop-blur-[2px] p-4"
        role="dialog"
        aria-modal="true"
        aria-labelledby="new-folder-modal-title"
    >
        <div class="w-full max-w-[400px] rounded-2xl bg-white border border-[#E6E8EC] shadow-xl overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4 border-b border-[#F0F1F3]">
                <h3 id="new-folder-modal-title" class="text-[15px] font-semibold text-[#111315]">
                    Buat Folder User Baru
                </h3>
                <button
                    type="button"
                    data-close-folder-modal
                    class="inline-flex items-center justify-center w-7 h-7 rounded-lg text-[#667085] hover:text-[#111315] hover:bg-[#F4F5F7] transition-colors cursor-pointer"
                    aria-label="Tutup modal"
                >
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                        <path d="M18 6L6 18M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <form id="new-folder-form" class="p-5 space-y-4">
                <div>
                    <label for="new-folder-name" class="block text-[12.5px] font-medium text-[#344054] mb-1.5">
                        Nama User / Folder
                    </label>
                    <input
                        id="new-folder-name"
                        name="nama"
                        type="text"
                        required
                        maxlength="255"
                        placeholder="contoh: Roedi"
                        autocomplete="off"
                        class="w-full h-10 px-3 rounded-lg border border-[#D0D5DD] text-[13.5px] text-[#111315] placeholder-[#98A2B3] focus:outline-none focus:border-[#111315] transition-colors"
                    />
                    <p class="text-[11.5px] text-[#667085] mt-1.5">
                        Akan disimpan ke tabel <code class="font-mono">user_responses</code> di Supabase.
                    </p>
                </div>

                <div id="new-folder-error" class="hidden text-[12px] text-rose-700 bg-rose-50 border border-rose-200 rounded-lg px-3 py-2"></div>

                <div class="flex items-center justify-end gap-2 pt-1">
                    <button
                        type="button"
                        data-close-folder-modal
                        class="h-9 px-3.5 rounded-lg border border-[#E6E8EC] bg-white hover:bg-[#F7F8FA] text-[12.5px] font-medium text-[#475467] transition-colors cursor-pointer"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        id="new-folder-submit-btn"
                        class="h-9 px-4 rounded-lg bg-[#111315] hover:bg-black text-white text-[12.5px] font-medium transition-colors cursor-pointer disabled:opacity-50"
                    >
                        Simpan Folder
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            try {
                const searchInput = document.getElementById('dashboard-search-input');
                const foldersGrid = document.getElementById('folders-grid');
                const foldersCountEl = document.getElementById('folders-count');
                const metricFoldersEl = document.getElementById('metric-folders-count');
                const metricCapturesEl = document.getElementById('metric-captures-count');
                const emptyHintEl = document.getElementById('folders-empty-hint');
                const noMatchEl = document.getElementById('folders-no-match');
                const newFolderCardBtn = document.getElementById('new-folder-btn');
                const openModalBtn = document.getElementById('open-new-folder-modal-btn');
                const modal = document.getElementById('new-folder-modal');
                const folderForm = document.getElementById('new-folder-form');
                const folderNameInput = document.getElementById('new-folder-name');
                const folderErrorEl = document.getElementById('new-folder-error');
                const folderSubmitBtn = document.getElementById('new-folder-submit-btn');
                const filterTabs = document.querySelectorAll('.folder-filter-tab');
                const realtimeDot = document.getElementById('realtime-dot');
                const realtimeLabel = document.getElementById('realtime-label');

                const accentClasses = [
                    'bg-indigo-50 text-indigo-700 border-indigo-100',
                    'bg-sky-50 text-sky-700 border-sky-100',
                    'bg-amber-50 text-amber-700 border-amber-100',
                ];

                let activeQuery = '';
                let activeFilter = 'all';

                const escapeHtml = (str) => String(str)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#39;');

                const notify = (msg) => {
                    let box = document.getElementById('app-toast');
                    if (!box) {
                        box = document.createElement('div');
                        box.id = 'app-toast';
                        box.className = 'fixed bottom-5 right-5 z-50 px-3.5 py-2.5 rounded-xl bg-[#111315] text-white text-[12.5px] font-medium shadow-lg border border-white/10 transition-all duration-200 opacity-0 translate-y-2 pointer-events-none';
                        document.body.appendChild(box);
                    }
                    box.textContent = msg;
                    box.classList.remove('opacity-0', 'translate-y-2');
                    clearTimeout(box._timer);
                    box._timer = setTimeout(() => {
                        box.classList.add('opacity-0', 'translate-y-2');
                    }, 3000);
                };

                const recomputeTelemetry = () => {
                    const allCards = document.querySelectorAll('.folder-card[data-searchable="folder"]');
                    let totalCaptures = 0;
                    allCards.forEach((card) => {
                        totalCaptures += parseInt(card.dataset.items || '0', 10) || 0;
                    });
                    if (metricFoldersEl) metricFoldersEl.textContent = String(allCards.length);
                    if (metricCapturesEl) metricCapturesEl.textContent = totalCaptures.toLocaleString('en-US');
                };

                const applyFilters = () => {
                    const q = activeQuery.trim().toLowerCase();
                    const allCards = document.querySelectorAll('.folder-card[data-searchable="folder"]');
                    let visibleFolders = 0;

                    allCards.forEach((card) => {
                        const title = card.dataset.title || '';
                        const items = parseInt(card.dataset.items || '0', 10) || 0;

                        const matchQuery = !q || title.includes(q);
                        let matchTab = true;
                        if (activeFilter === 'with-items') matchTab = items > 0;
                        else if (activeFilter === 'empty') matchTab = items === 0;

                        const visible = matchQuery && matchTab;
                        card.classList.toggle('hidden', !visible);
                        if (visible) visibleFolders++;
                    });

                    if (foldersCountEl) foldersCountEl.textContent = String(visibleFolders);

                    // Filter right-rail searchable rows (projects & devices)
                    document.querySelectorAll('.dashboard-searchable-row').forEach((row) => {
                        const hay = row.dataset.searchText || '';
                        row.classList.toggle('hidden', Boolean(q) && !hay.includes(q));
                    });

                    if (allCards.length === 0) {
                        emptyHintEl?.classList.remove('hidden');
                        noMatchEl?.classList.add('hidden');
                    } else {
                        emptyHintEl?.classList.add('hidden');
                        noMatchEl?.classList.toggle('hidden', visibleFolders > 0);
                    }
                };

                if (searchInput) {
                    searchInput.addEventListener('input', (e) => {
                        activeQuery = e.target.value;
                        applyFilters();
                    });
                }

                filterTabs.forEach((tab) => {
                    tab.addEventListener('click', () => {
                        activeFilter = tab.dataset.folderFilter || 'all';
                        filterTabs.forEach((btn) => {
                            const isSelected = btn === tab;
                            btn.classList.toggle('bg-white', isSelected);
                            btn.classList.toggle('text-[#111315]', isSelected);
                            btn.classList.toggle('shadow-[0_1px_2px_rgba(16,24,40,0.06)]', isSelected);
                            btn.classList.toggle('text-[#667085]', !isSelected);
                        });
                        applyFilters();
                    });
                });

                document.getElementById('clear-folder-search-btn')?.addEventListener('click', () => {
                    activeQuery = '';
                    activeFilter = 'all';
                    if (searchInput) searchInput.value = '';
                    filterTabs[0]?.click();
                });

                // Modal Buat Folder Baru
                const openModal = () => {
                    if (!modal) return;
                    modal.classList.remove('hidden');
                    modal.classList.add('flex');
                    folderErrorEl?.classList.add('hidden');
                    if (folderNameInput) {
                        folderNameInput.value = '';
                        setTimeout(() => folderNameInput.focus(), 30);
                    }
                };

                const closeModal = () => {
                    if (!modal) return;
                    modal.classList.add('hidden');
                    modal.classList.remove('flex');
                };

                newFolderCardBtn?.addEventListener('click', openModal);
                openModalBtn?.addEventListener('click', openModal);
                modal?.querySelectorAll('[data-close-folder-modal]').forEach((btn) => btn.addEventListener('click', closeModal));
                modal?.addEventListener('click', (e) => {
                    if (e.target === modal) closeModal();
                });

                folderForm?.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    const nama = (folderNameInput?.value || '').trim();
                    if (!nama) return;

                    folderErrorEl?.classList.add('hidden');
                    if (folderSubmitBtn) {
                        folderSubmitBtn.disabled = true;
                        folderSubmitBtn.textContent = 'Menyimpan...';
                    }

                    try {
                        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                        const res = await fetch('/api/folders', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken || '',
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({ nama }),
                        });

                        const data = await res.json();
                        if (res.ok && data.success) {
                            closeModal();
                            insertNewFolderCard(nama, 0);
                            notify(`Folder "${nama}" berhasil disimpan ke Supabase`);
                        } else if (folderErrorEl) {
                            folderErrorEl.textContent = data.message || 'Gagal menyimpan folder.';
                            folderErrorEl.classList.remove('hidden');
                        }
                    } catch (err) {
                        if (folderErrorEl) {
                            folderErrorEl.textContent = 'Terjadi kesalahan jaringan: ' + err.message;
                            folderErrorEl.classList.remove('hidden');
                        }
                    } finally {
                        if (folderSubmitBtn) {
                            folderSubmitBtn.disabled = false;
                            folderSubmitBtn.textContent = 'Simpan Folder';
                        }
                    }
                });

                // Shortcut Keyboard ⌘F / Ctrl+F & Escape
                document.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape') {
                        closeModal();
                    }
                    if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'f' && searchInput) {
                        event.preventDefault();
                        searchInput.focus();
                        searchInput.select();
                    }
                });

                // =================================================================
                // REALTIME DASHBOARD HANDLERS (Supabase WebSocket + Polling)
                // =================================================================
                const createFolderElement = (title, itemsCount = 0, index = 0) => {
                    const cleanTitle = (title || `Folder ${index + 1}`).trim();
                    const userKey = cleanTitle.toLowerCase();
                    const folderUrl = `/folders?folder=${encodeURIComponent(cleanTitle)}`;
                    const accentClass = accentClasses[index % accentClasses.length];
                    const safeTitle = escapeHtml(cleanTitle);

                    const a = document.createElement('a');
                    a.href = folderUrl;
                    a.className = 'folder-card new-folder-enter group flex flex-col justify-between rounded-xl border border-[#E6E8EC] bg-white p-4 hover:border-[#C2C9D2] hover:bg-[#FCFCFD] transition-all duration-150 cursor-pointer select-none text-inherit no-underline';
                    a.dataset.searchable = 'folder';
                    a.dataset.title = userKey;
                    a.dataset.user = userKey;
                    a.dataset.items = String(itemsCount);
                    a.dataset.url = folderUrl;

                    a.innerHTML = `
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-center gap-3 min-w-0">
                                <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg border shrink-0 ${accentClass}">
                                    <svg class="w-[17px] h-[17px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
                                    </svg>
                                </span>
                                <div class="min-w-0">
                                    <h3 class="text-[14px] font-semibold text-[#111315] tracking-[-0.01em] truncate group-hover:text-black">${safeTitle}</h3>
                                    <p class="text-[11.5px] text-[#7A818A] truncate mt-0.5">user_responses</p>
                                </div>
                            </div>
                            <svg class="w-4 h-4 text-[#B0B7C3] group-hover:text-[#111315] group-hover:translate-x-0.5 transition-all shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M9 18l6-6-6-6"/>
                            </svg>
                        </div>
                        <div class="mt-4 pt-3 border-t border-[#F2F3F5] flex items-center justify-between gap-2">
                            <div class="flex items-center gap-1.5 min-w-0">
                                <span class="folder-sync-dot w-1.5 h-1.5 rounded-full ${itemsCount > 0 ? 'bg-emerald-500' : 'bg-[#D0D5DD]'}" aria-hidden="true"></span>
                                <span class="folder-sync-label text-[11.5px] text-[#667085] truncate">${itemsCount > 0 ? 'Tersinkron' : 'Belum ada capture'}</span>
                            </div>
                            <span class="folder-items-count font-mono text-[12px] font-medium text-[#344054] tabular-nums shrink-0 transition-colors" data-user="${escapeHtml(userKey)}">${itemsCount} items</span>
                        </div>
                    `;

                    return a;
                };

                const findFolderCardByUser = (userKey) => {
                    return Array.from(document.querySelectorAll('.folder-card[data-user]'))
                        .find((el) => el.dataset.user === userKey) || null;
                };

                const insertNewFolderCard = (nama, itemsCount = 0) => {
                    if (!nama || !nama.trim()) return;
                    const userKey = nama.trim().toLowerCase();
                    if (findFolderCardByUser(userKey)) return;
                    if (!foldersGrid || !newFolderCardBtn) return;

                    const existingCount = foldersGrid.querySelectorAll('.folder-card').length;
                    const newCard = createFolderElement(nama.trim(), itemsCount, existingCount);
                    foldersGrid.insertBefore(newCard, newFolderCardBtn);

                    recomputeTelemetry();
                    applyFilters();
                };

                const updateFolderItemCount = (card, nextCount, animate = true) => {
                    card.dataset.items = String(nextCount);
                    const counterEl = card.querySelector('.folder-items-count');
                    if (counterEl) {
                        counterEl.textContent = `${nextCount} items`;
                        if (animate) {
                            counterEl.classList.add('counter-pulse');
                            setTimeout(() => counterEl.classList.remove('counter-pulse'), 1100);
                        }
                    }

                    const dot = card.querySelector('.folder-sync-dot');
                    const label = card.querySelector('.folder-sync-label');
                    if (nextCount > 0) {
                        dot?.classList.remove('bg-[#D0D5DD]');
                        dot?.classList.add('bg-emerald-500');
                        if (label) label.textContent = 'Tersinkron';
                    }

                    if (animate) {
                        card.classList.remove('folder-pulse');
                        void card.offsetWidth;
                        card.classList.add('folder-pulse');
                    }
                };

                const handleNewScreenshot = (ss) => {
                    if (!ss) return;
                    const userName = (ss.user_name || '').trim();
                    if (!userName) return;
                    const userKey = userName.toLowerCase();

                    const card = findFolderCardByUser(userKey);
                    if (!card) {
                        insertNewFolderCard(userName, 1);
                        notify(`Capture baru diterima untuk "${userName}"`);
                        return;
                    }

                    const currentCount = parseInt(card.dataset.items || '0', 10) || 0;
                    const nextCount = currentCount + 1;
                    updateFolderItemCount(card, nextCount, true);
                    recomputeTelemetry();
                    notify(`Capture baru: ${userName} (${nextCount} items)`);
                };

                if (window.supabase) {
                    window.supabase
                        .channel('dashboard_user_responses')
                        .on('postgres_changes', {
                            event: 'INSERT',
                            schema: 'public',
                            table: 'user_responses',
                        }, (payload) => {
                            if (payload.new && payload.new.nama) {
                                insertNewFolderCard(payload.new.nama, 0);
                                notify(`Folder baru "${payload.new.nama}" ditambahkan`);
                            }
                        })
                        .subscribe((status) => {
                            if (status === 'SUBSCRIBED') {
                                realtimeDot?.classList.remove('bg-amber-500');
                                realtimeDot?.classList.add('bg-emerald-500');
                                if (realtimeLabel) realtimeLabel.textContent = 'Supabase Live';
                            }
                        });

                    window.supabase
                        .channel('dashboard_hasil_ss')
                        .on('postgres_changes', {
                            event: 'INSERT',
                            schema: 'public',
                            table: 'hasil_ss',
                        }, (payload) => {
                            handleNewScreenshot(payload.new);
                        })
                        .subscribe();

                    // Polling sinkronisasi cadangan setiap 5 detik
                    setInterval(async () => {
                        try {
                            const { data: users, error: uErr } = await window.supabase
                                .from('user_responses')
                                .select('nama')
                                .order('id', { ascending: true });

                            if (!uErr && Array.isArray(users)) {
                                users.forEach((u) => {
                                    if (u.nama) {
                                        const k = u.nama.trim().toLowerCase();
                                        if (!findFolderCardByUser(k)) {
                                            insertNewFolderCard(u.nama, 0);
                                        }
                                    }
                                });
                            }

                            const { data: ssList, error: sErr } = await window.supabase
                                .from('hasil_ss')
                                .select('user_name');

                            if (!sErr && Array.isArray(ssList)) {
                                const counts = {};
                                ssList.forEach((s) => {
                                    const u = (s.user_name || '').trim().toLowerCase();
                                    if (u) counts[u] = (counts[u] || 0) + 1;
                                });

                                let changed = false;
                                Object.keys(counts).forEach((u) => {
                                    const card = findFolderCardByUser(u);
                                    if (card) {
                                        const current = parseInt(card.dataset.items || '0', 10) || 0;
                                        if (current !== counts[u]) {
                                            updateFolderItemCount(card, counts[u], false);
                                            changed = true;
                                        }
                                    }
                                });
                                if (changed) {
                                    recomputeTelemetry();
                                    applyFilters();
                                }
                            }
                        } catch (_) {
                            // silent fallback
                        }
                    }, 5000);
                }
            } catch (error) {
                console.error('Gagal menginisialisasi dashboard:', error);
            }
        });
    </script>
@endpush
