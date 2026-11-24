{{--
    ==========================================================================
    Halaman Folders Detail — Galeri Tangkapan Layar (hasil_ss) Realtime
    Terkonfigurasi Terisolasi per User (user_name)
    Path: resources/views/folders.blade.php
    Extends: layouts.app (memuat sidebar, vite css/js, dll.)
    ==========================================================================
--}}

@extends('layouts.app')

@section('title', config('app.name', 'Xoose') . ' — Folders (' . $folderName . ')')

@php
    $activeMenu = 'folders';
    $isAllFolders = ($folderName === 'all' || $folderName === 'hasil_ss');
    $displayFolderTitle = $isAllFolders ? 'Semua User' : $folderName;
@endphp

@push('styles')
    <style>
        .screenshot-img-wrapper {
            aspect-ratio: 16 / 9;
        }

        .filter-dropdown-menu {
            transform-origin: top right;
            transition: opacity 0.14s ease, transform 0.14s ease;
        }

        #lightbox-modal {
            transition: opacity 0.18s ease, visibility 0.18s ease;
        }

        #lightbox-modal.is-hidden {
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
        }

        @keyframes newCardEnter {
            0% {
                opacity: 0;
                transform: translateY(-10px);
            }
            100% {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .new-screenshot-highlight {
            animation: newCardEnter 0.32s cubic-bezier(0.16, 1, 0.3, 1);
            border-color: #10B981 !important;
        }
    </style>
@endpush

@section('content')
    {{-- =====================================================================
         TOP BAR — Minimalist Operational Header
         ===================================================================== --}}
    <header class="flex items-center justify-between gap-4 px-5 sm:px-8 h-[62px] border-b border-[#EAECEF] bg-white shrink-0">
        <div class="flex items-center gap-3 flex-1 min-w-0 max-w-[540px]">
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

            <nav class="hidden md:flex items-center gap-1.5 text-[12.5px] text-[#667085] shrink-0" aria-label="Breadcrumb">
                <a href="{{ route('dashboard') }}" class="hover:text-[#111315] transition-colors">Overview</a>
                <span class="text-[#D0D5DD]">/</span>
                <span class="text-[#111315] font-medium truncate max-w-[140px]">{{ $displayFolderTitle }}</span>
            </nav>

            <label class="relative flex items-center w-full h-[36px] rounded-lg bg-[#F7F8FA] border border-[#E6E8EC] px-3 gap-2 focus-within:bg-white focus-within:border-[#98A2B3] transition-colors cursor-text">
                <svg class="w-3.5 h-3.5 text-[#8E959F] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="7"/>
                    <path d="M20 20L16.2 16.2"/>
                </svg>
                <input
                    id="ss-search-input"
                    type="text"
                    placeholder="Cari active window atau nama file..."
                    autocomplete="off"
                    class="w-full bg-transparent text-[13px] text-[#111315] placeholder-[#8E959F] focus:outline-none"
                />
                <span class="hidden sm:inline-flex items-center shrink-0 select-none">
                    <kbd class="inline-flex items-center justify-center px-1.5 h-[20px] rounded bg-white border border-[#E4E7EC] font-mono text-[10.5px] text-[#667085]">⌘F</kbd>
                </span>
            </label>
        </div>

        <div class="flex items-center gap-2 shrink-0">
            {{-- Switcher User / Folder --}}
            <div class="relative filter-dropdown" data-dropdown="user-switch">
                <button
                    type="button"
                    class="filter-trigger inline-flex items-center gap-1.5 h-[34px] px-3 rounded-lg bg-white hover:bg-[#F7F8FA] border border-[#E6E8EC] text-[12.5px] font-medium text-[#111315] transition-colors cursor-pointer"
                    title="Ganti User / Folder"
                >
                    <svg class="w-3.5 h-3.5 text-[#667085]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
                    </svg>
                    <span class="max-w-[90px] sm:max-w-[130px] truncate">{{ $displayFolderTitle }}</span>
                    <svg class="w-3.5 h-3.5 text-[#8E959F]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M6 9l6 6 6-6"/>
                    </svg>
                </button>
                <div class="filter-dropdown-menu hidden absolute right-0 mt-1.5 w-60 bg-white rounded-xl border border-[#E6E8EC] shadow-lg py-1.5 z-30">
                    <div class="px-3 py-1 text-[10.5px] font-semibold text-[#8E959F] uppercase tracking-[0.07em]">
                        User Folders
                    </div>
                    <div class="max-h-60 overflow-y-auto">
                        @foreach ($folders as $f)
                            <a
                                href="{{ route('folders.index', ['folder' => $f['title']]) }}"
                                class="flex items-center justify-between gap-2 px-3 py-1.5 text-[12.5px] transition-colors {{ $folderName === $f['title'] ? 'bg-[#F4F5F7] font-semibold text-[#111315]' : 'text-[#475467] hover:bg-[#FAFAFB] hover:text-[#111315]' }}"
                            >
                                <span class="truncate">{{ $f['title'] }}</span>
                                <span class="font-mono text-[11px] text-[#667085] tabular-nums shrink-0">{{ $f['items'] }}</span>
                            </a>
                        @endforeach
                    </div>
                    <div class="border-t border-[#F0F1F3] my-1"></div>
                    <a
                        href="{{ route('folders.index', ['folder' => 'all']) }}"
                        class="flex items-center justify-between px-3 py-1.5 text-[12.5px] transition-colors {{ $isAllFolders ? 'bg-[#F4F5F7] font-semibold text-[#111315]' : 'text-[#667085] hover:bg-[#FAFAFB] hover:text-[#111315]' }}"
                    >
                        <span>Semua User (All)</span>
                    </a>
                </div>
            </div>

            {{-- Status Realtime & Jumlah Screenshot --}}
            <div
                id="realtime-status-pill"
                class="inline-flex items-center gap-2 h-[34px] px-3 rounded-lg border border-[#E6E8EC] bg-[#FAFAFB] text-[12px] font-medium text-[#344054]"
                title="Supabase Realtime aktif untuk tabel hasil_ss"
            >
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                <span id="ss-total-count" class="font-mono tabular-nums">{{ count($screenshots) }} ss</span>
            </div>

            {{-- Bersihkan Resource Supabase --}}
            <button
                type="button"
                onclick="openCleanupModal('{{ !$isAllFolders ? $folderName : '' }}')"
                class="inline-flex items-center gap-1.5 h-[34px] px-3 rounded-lg border border-[#E6E8EC] hover:border-rose-200 bg-white hover:bg-rose-50 text-[#475467] hover:text-rose-700 text-[12.5px] font-medium transition-colors cursor-pointer"
                title="Kelola & bersihkan data screenshot di Supabase"
            >
                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="3 6 5 6 21 6"></polyline>
                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                </svg>
                <span class="hidden sm:inline">Bersihkan</span>
            </button>
        </div>
    </header>

    {{-- =====================================================================
         KONTEN UTAMA GALERI TANGKAPAN LAYAR (hasil_ss)
         ===================================================================== --}}
    <div class="flex-1 overflow-y-auto bg-white">
        <div class="max-w-[1360px] mx-auto px-5 sm:px-8 py-7 space-y-6">

            {{-- Unified Header + Telemetry Panel (Zero Redundancy) --}}
            <section class="rounded-xl border border-[#E6E8EC] bg-white overflow-hidden" aria-label="Detail Folder & Filter">
                {{-- Row 1: Identitas Folder + Filter Toolbar --}}
                <div class="px-5 py-4 flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2.5">
                            <h1 id="detail-user-name" class="text-[20px] sm:text-[22px] font-semibold tracking-[-0.02em] text-[#111315] leading-tight truncate">
                                {{ $displayFolderTitle }}
                            </h1>

                            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md bg-[#F4F5F7] border border-[#EAECEF] text-[11px] font-medium text-[#475467]">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                <span>{{ $isAllFolders ? 'Semua Folder' : 'Terisolasi per User' }}</span>
                            </span>

                            @if (!empty($folderUserStatus))
                                @php $isPositiveStatus = in_array(strtolower($folderUserStatus), ['yes', 'active', 'success']); @endphp
                                <span id="detail-user-status" class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-[11px] font-medium border {{ $isPositiveStatus ? 'bg-emerald-50 text-emerald-800 border-emerald-200/80' : 'bg-amber-50 text-amber-800 border-amber-200/80' }}">
                                    <span>Respon: {{ $folderUserStatus }}</span>
                                </span>
                            @endif
                        </div>

                        <p class="text-[12.5px] text-[#667085] mt-1 flex flex-wrap items-center gap-x-2 gap-y-1">
                            @if (!$isAllFolders)
                                <span>Stream <code class="font-mono text-[11.5px] text-[#344054]">hasil_ss</code> terfilter khusus <strong class="font-medium text-[#111315]">{{ $folderName }}</strong></span>
                            @else
                                <span>Menampilkan seluruh tangkapan layar dari tabel <code class="font-mono text-[11.5px] text-[#344054]">hasil_ss</code></span>
                            @endif

                            @if (!empty($userResponse['tanggal_date']))
                                <span class="text-[#D0D5DD]">·</span>
                                <span>Tanggal target: <span class="font-mono text-[#344054]">{{ $userResponse['tanggal_date'] }}</span></span>
                            @endif
                        </p>
                    </div>

                    {{-- Filter Controls --}}
                    <div class="flex flex-wrap items-center gap-2 shrink-0">
                        {{-- Filter: Active Window --}}
                        <div class="relative filter-dropdown" data-filter="window">
                            <button
                                type="button"
                                class="filter-trigger inline-flex items-center gap-1.5 h-[32px] px-3 rounded-lg bg-[#FAFAFB] hover:bg-[#F2F4F7] border border-[#E6E8EC] text-[12px] transition-colors cursor-pointer"
                            >
                                <span class="text-[#667085]">Window:</span>
                                <span class="filter-value font-medium text-[#111315] max-w-[110px] truncate">All</span>
                                <svg class="w-3.5 h-3.5 text-[#8E959F]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M6 9l6 6 6-6"/>
                                </svg>
                            </button>
                            <div class="filter-dropdown-menu hidden absolute right-0 mt-1.5 w-60 max-h-64 overflow-y-auto bg-white rounded-xl border border-[#E6E8EC] shadow-lg py-1 z-30">
                                <button type="button" data-value="All" class="filter-option w-full text-left px-3 py-1.5 text-[12px] text-[#111315] hover:bg-[#F7F8FA] font-semibold bg-[#F4F5F7]">
                                    All Windows
                                </button>
                                @foreach ($activeWindows as $w)
                                    <button type="button" data-value="{{ $w }}" class="filter-option w-full text-left px-3 py-1.5 text-[12px] text-[#344054] hover:bg-[#F7F8FA] truncate" title="{{ $w }}">
                                        {{ $w }}
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        {{-- Filter: Status --}}
                        <div class="relative filter-dropdown" data-filter="status">
                            <button
                                type="button"
                                class="filter-trigger inline-flex items-center gap-1.5 h-[32px] px-3 rounded-lg bg-[#FAFAFB] hover:bg-[#F2F4F7] border border-[#E6E8EC] text-[12px] transition-colors cursor-pointer"
                            >
                                <span class="text-[#667085]">Status:</span>
                                <span class="filter-value font-medium text-[#111315]">All</span>
                                <svg class="w-3.5 h-3.5 text-[#8E959F]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M6 9l6 6 6-6"/>
                                </svg>
                            </button>
                            <div class="filter-dropdown-menu hidden absolute right-0 mt-1.5 w-36 bg-white rounded-xl border border-[#E6E8EC] shadow-lg py-1 z-30">
                                @foreach (['All', 'success', 'pending', 'failed'] as $opt)
                                    <button type="button" data-value="{{ $opt }}" class="filter-option w-full text-left px-3 py-1.5 text-[12px] text-[#344054] hover:bg-[#F7F8FA] {{ $opt === 'All' ? 'font-semibold bg-[#F4F5F7] text-[#111315]' : '' }}">
                                        {{ ucfirst($opt) }}
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        {{-- Filter: Sorting --}}
                        <div class="relative filter-dropdown" data-filter="sort">
                            <button
                                type="button"
                                class="filter-trigger inline-flex items-center gap-1.5 h-[32px] px-3 rounded-lg bg-[#FAFAFB] hover:bg-[#F2F4F7] border border-[#E6E8EC] text-[12px] transition-colors cursor-pointer"
                            >
                                <span class="text-[#667085]">Urutan:</span>
                                <span class="filter-value font-medium text-[#111315]">Terbaru</span>
                                <svg class="w-3.5 h-3.5 text-[#8E959F]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M6 9l6 6 6-6"/>
                                </svg>
                            </button>
                            <div class="filter-dropdown-menu hidden absolute right-0 mt-1.5 w-44 bg-white rounded-xl border border-[#E6E8EC] shadow-lg py-1 z-30">
                                <button type="button" data-value="newest" data-label="Terbaru" class="filter-option w-full text-left px-3 py-1.5 text-[12px] text-[#111315] hover:bg-[#F7F8FA] font-semibold bg-[#F4F5F7]">
                                    Terbaru (ID Desc)
                                </button>
                                <button type="button" data-value="oldest" data-label="Terlama" class="filter-option w-full text-left px-3 py-1.5 text-[12px] text-[#344054] hover:bg-[#F7F8FA]">
                                    Terlama (ID Asc)
                                </button>
                            </div>
                        </div>

                        {{-- Reset Filter --}}
                        <button
                            type="button"
                            id="reset-ss-filters"
                            title="Reset semua filter"
                            aria-label="Reset Filter"
                            class="inline-flex items-center justify-center w-[32px] h-[32px] rounded-lg bg-white hover:bg-[#F4F5F7] border border-[#E6E8EC] text-[#667085] hover:text-[#111315] transition-colors cursor-pointer"
                        >
                            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M3 12a9 9 0 1 0 3-6.7L3 8"/>
                                <path d="M3 3v5h5"/>
                            </svg>
                        </button>
                    </div>
                </div>

                {{-- Row 2: Segmented Metadata Strip (Device Name, Resolution, Status) --}}
                <div class="grid grid-cols-1 sm:grid-cols-3 border-t border-[#EAECEF] bg-[#FAFAFB]/70 divide-y sm:divide-y-0 sm:divide-x divide-[#EAECEF]">
                    <div class="px-5 py-3 flex items-center justify-between gap-3">
                        <span class="text-[11.5px] font-medium text-[#667085]">Device Name</span>
                        <span id="detail-device-name" class="font-mono text-[12.5px] font-medium text-[#111315] truncate transition-colors" title="{{ $folderDeviceName ?? 'Menunggu koneksi device...' }}">
                            {{ $folderDeviceName ?? 'Menunggu...' }}
                        </span>
                    </div>

                    <div class="px-5 py-3 flex items-center justify-between gap-3">
                        <span class="text-[11.5px] font-medium text-[#667085]">Resolution</span>
                        <span id="detail-resolution" class="font-mono text-[12.5px] font-medium text-[#111315] tabular-nums truncate transition-colors" title="{{ $folderResolution ?? 'Menunggu resolusi...' }}">
                            {{ $folderResolution ?? 'Menunggu...' }}
                        </span>
                    </div>

                    <div class="px-5 py-3 flex items-center justify-between gap-3">
                        <span class="text-[11.5px] font-medium text-[#667085]">Status</span>
                        <span class="inline-flex items-center gap-1.5 min-w-0">
                            <span class="w-1.5 h-1.5 rounded-full shrink-0 {{ strtolower($folderSsStatus ?? 'active') === 'failed' ? 'bg-rose-500' : 'bg-emerald-500' }}"></span>
                            <span id="detail-ss-status" class="text-[12.5px] font-medium capitalize text-[#111315] truncate transition-colors">
                                {{ $folderSsStatus ?? 'Active' }}
                            </span>
                        </span>
                    </div>
                </div>
            </section>

            {{-- =================================================================
                 GRID GALLERY SCREENSHOTS
                 ================================================================= --}}
            <section aria-label="Daftar Tangkapan Layar">
                <div
                    id="screenshots-grid"
                    class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4"
                >
                    @forelse ($screenshots as $ss)
                        @php
                            $imgUrl = $ss['image_url'] ?? '';
                            $filename = $ss['filename'] ?? ('screenshot_' . $ss['id'] . '.png');
                            $activeWindow = !empty(trim((string) ($ss['active_window'] ?? ''))) ? $ss['active_window'] : 'General Desktop';
                            $resolution = $ss['resolution'] ?? '1920x1080';
                            $fileSize = isset($ss['file_size_kb']) ? number_format((float) $ss['file_size_kb'], 1) . ' KB' : '-';
                            $ssUser = $ss['user_name'] ?? 'unknown';

                            $createdAt = !empty($ss['created_at'])
                                ? \Illuminate\Support\Carbon::parse($ss['created_at'])->setTimezone('Asia/Jakarta')->locale('id')->translatedFormat('d M Y, H:i:s') . ' WIB'
                                : '-';
                            $fullDateTip = !empty($ss['created_at'])
                                ? \Illuminate\Support\Carbon::parse($ss['created_at'])->setTimezone('Asia/Jakarta')->locale('id')->translatedFormat('l, d F Y - H:i:s') . ' WIB'
                                : '-';
                            $status = strtolower($ss['status'] ?? 'success');
                            $statusDot = match ($status) {
                                'success' => 'bg-emerald-500',
                                'pending' => 'bg-amber-500',
                                'failed'  => 'bg-rose-500',
                                default   => 'bg-[#98A2B3]',
                            };
                        @endphp

                        <article
                            class="screenshot-card group bg-white rounded-xl border border-[#E6E8EC] hover:border-[#BFC6D0] overflow-hidden flex flex-col transition-colors duration-150"
                            data-id="{{ $ss['id'] }}"
                            data-filename="{{ strtolower($filename) }}"
                            data-filename-raw="{{ $filename }}"
                            data-window="{{ strtolower($activeWindow) }}"
                            data-window-raw="{{ $activeWindow }}"
                            data-user="{{ strtolower($ssUser) }}"
                            data-status="{{ $status }}"
                            data-img="{{ $imgUrl }}"
                            data-resolution="{{ $resolution }}"
                            data-size="{{ $fileSize }}"
                            data-date="{{ $createdAt }}"
                        >
                            {{-- Thumbnail --}}
                            <div class="relative screenshot-img-wrapper w-full bg-[#111315] overflow-hidden cursor-pointer border-b border-[#EAECEF]" data-preview-trigger>
                                @if (!empty($imgUrl))
                                    <img
                                        src="{{ $imgUrl }}"
                                        alt="{{ $filename }}"
                                        loading="lazy"
                                        class="w-full h-full object-cover object-top"
                                        onerror="this.onerror=null; this.src='https://placehold.co/600x338/111315/98a2b3?text=No+Preview';"
                                    />
                                @else
                                    <div class="w-full h-full flex items-center justify-center text-[#8E959F] text-[12px] font-mono">
                                        No preview
                                    </div>
                                @endif

                                {{-- Subtle hover preview action --}}
                                <div class="absolute inset-0 bg-[#111315]/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-white text-[#111315] text-[12px] font-medium shadow-sm">
                                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <circle cx="11" cy="11" r="8"/>
                                            <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                                        </svg>
                                        <span>Perbesar</span>
                                    </span>
                                </div>

                                {{-- Resolusi (understated monospace tag) --}}
                                <div class="absolute bottom-2 right-2">
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded bg-[#111315]/75 backdrop-blur-xs text-white/95 font-mono text-[10.5px] tabular-nums">
                                        {{ $resolution }}
                                    </span>
                                </div>
                            </div>

                            {{-- Card Body --}}
                            <div class="p-3.5 flex-1 flex flex-col justify-between gap-3">
                                <div class="min-w-0">
                                    <h3 class="text-[13px] font-semibold text-[#111315] truncate" title="{{ $activeWindow }}">
                                        {{ $activeWindow }}
                                    </h3>
                                    <p class="font-mono text-[11px] text-[#7A818A] truncate mt-0.5" title="{{ $filename }}">
                                        {{ $filename }}
                                    </p>
                                </div>

                                <div class="pt-2.5 border-t border-[#F2F3F5] flex items-center justify-between gap-2 text-[11px] text-[#667085]">
                                    <div class="flex items-center gap-1.5 min-w-0">
                                        <span class="w-1.5 h-1.5 rounded-full shrink-0 {{ $statusDot }}" title="Status: {{ $status }}"></span>
                                        <span class="truncate tabular-nums" title="{{ $fullDateTip }}">{{ $createdAt }}</span>
                                    </div>

                                    <div class="flex items-center gap-1.5 shrink-0">
                                        <span class="font-mono text-[11px] text-[#344054] tabular-nums">{{ $fileSize }}</span>
                                        @if (!empty($imgUrl))
                                            <button
                                                type="button"
                                                class="copy-url-btn inline-flex items-center justify-center w-6 h-6 rounded-md hover:bg-[#F2F4F7] text-[#667085] hover:text-[#111315] transition-colors cursor-pointer"
                                                data-url="{{ $imgUrl }}"
                                                title="Salin URL gambar"
                                                aria-label="Salin URL gambar"
                                            >
                                                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                                                    <rect x="9" y="9" width="13" height="13" rx="2" ry="2"/>
                                                    <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                                                </svg>
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </article>
                    @empty
                        <div id="ss-initial-empty" class="col-span-full py-14 px-6 text-center rounded-xl border border-dashed border-[#E4E7EC] bg-[#FAFAFB]/50">
                            <p class="text-[14px] font-semibold text-[#111315]">
                                Belum ada tangkapan layar untuk {{ $displayFolderTitle }}
                            </p>
                            <p class="text-[12.5px] text-[#667085] mt-1.5 max-w-md mx-auto leading-relaxed">
                                Folder ini terisolasi per user. Saat klien LoveYou mengunggah screenshot baru ke <code class="font-mono text-[11.5px] text-[#344054]">hasil_ss</code>, gambar akan langsung muncul di sini secara realtime.
                            </p>
                            <div class="mt-4 inline-flex items-center gap-2 px-3 py-1 rounded-lg bg-white border border-[#E6E8EC] text-[12px] text-[#475467]">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                <span>Menunggu stream Supabase Realtime</span>
                            </div>
                        </div>
                    @endforelse
                </div>

                {{-- State Kosong Hasil Filter --}}
                <div id="ss-empty-state" class="hidden py-12 px-6 text-center rounded-xl border border-[#EAECEF] bg-[#FAFAFB]/50 mt-4">
                    <p class="text-[13.5px] font-medium text-[#111315]">Tidak ada screenshot yang cocok dengan filter</p>
                    <p class="text-[12.5px] text-[#667085] mt-1">Sesuaikan kata kunci pencarian atau reset filter di atas.</p>
                </div>
            </section>
        </div>
    </div>

    {{-- =====================================================================
         LIGHTBOX MODAL PREVIEW SCREENSHOT (Dengan Navigasi ← / →)
         ===================================================================== --}}
    <div
        id="lightbox-modal"
        class="fixed inset-0 z-50 bg-[#0B0D10]/92 backdrop-blur-xs flex items-center justify-center p-4 sm:p-6 is-hidden"
        role="dialog"
        aria-modal="true"
        aria-label="Preview Screenshot"
    >
        <div class="relative max-w-5xl w-full max-h-[92vh] flex flex-col">
            {{-- Top Info Bar --}}
            <div class="flex items-center justify-between gap-4 pb-3 text-white">
                <div class="min-w-0">
                    <div id="lb-window" class="text-[14px] font-semibold truncate">Window Title</div>
                    <div id="lb-meta" class="font-mono text-[11.5px] text-white/60 mt-0.5 tabular-nums">1920x1080 · 0 KB · WIB</div>
                </div>

                <div class="flex items-center gap-2 shrink-0">
                    <button
                        type="button"
                        id="lb-prev"
                        class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-white/10 hover:bg-white/20 text-white transition-colors cursor-pointer"
                        title="Sebelumnya (←)"
                        aria-label="Screenshot sebelumnya"
                    >
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M15 18l-6-6 6-6"/>
                        </svg>
                    </button>
                    <button
                        type="button"
                        id="lb-next"
                        class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-white/10 hover:bg-white/20 text-white transition-colors cursor-pointer"
                        title="Berikutnya (→)"
                        aria-label="Screenshot berikutnya"
                    >
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9 18l6-6-6-6"/>
                        </svg>
                    </button>
                    <button
                        type="button"
                        id="lightbox-close"
                        class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-white/10 hover:bg-white/20 text-white transition-colors cursor-pointer ml-1"
                        title="Tutup (Esc)"
                        aria-label="Tutup"
                    >
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 6L6 18M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </div>

            {{-- Frame Gambar --}}
            <div class="relative w-full rounded-xl overflow-hidden border border-white/10 bg-black flex items-center justify-center max-h-[76vh]">
                <img
                    id="lb-image"
                    src=""
                    alt="Preview"
                    class="max-w-full max-h-[76vh] object-contain"
                />
            </div>

            {{-- Bottom Action Bar --}}
            <div class="flex items-center justify-between gap-4 pt-3 text-[12px] text-white/70">
                <span id="lb-filename" class="font-mono truncate">filename.png</span>
                <div class="flex items-center gap-2 shrink-0">
                    <button
                        type="button"
                        id="lb-copy-url"
                        class="inline-flex items-center gap-1.5 h-8 px-3 rounded-lg bg-white/10 hover:bg-white/20 text-white text-[12px] font-medium transition-colors cursor-pointer"
                    >
                        Salin URL
                    </button>
                    <a
                        id="lb-open-raw"
                        href="#"
                        target="_blank"
                        rel="noopener"
                        class="inline-flex items-center gap-1.5 h-8 px-3 rounded-lg bg-white text-[#111315] hover:bg-white/90 text-[12px] font-medium transition-colors"
                    >
                        <span>Buka Asli</span>
                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
                            <polyline points="15 3 21 3 21 9"/>
                            <line x1="10" y1="14" x2="21" y2="3"/>
                        </svg>
                    </a>
                </div>
            </div>
        </div>
    </div>

    @include('builder.partials.cleanup-modal')
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const currentFolder = @json($folderName);
            const searchInput = document.getElementById('ss-search-input');
            const grid = document.getElementById('screenshots-grid');
            let cards = Array.from(document.querySelectorAll('.screenshot-card'));
            const emptyState = document.getElementById('ss-empty-state');
            const dropdowns = document.querySelectorAll('.filter-dropdown');
            const resetBtn = document.getElementById('reset-ss-filters');
            const countBadge = document.getElementById('ss-total-count');

            // Lightbox Modal
            const lightbox = document.getElementById('lightbox-modal');
            const lbImage = document.getElementById('lb-image');
            const lbWindow = document.getElementById('lb-window');
            const lbMeta = document.getElementById('lb-meta');
            const lbFilename = document.getElementById('lb-filename');
            const lbOpenRaw = document.getElementById('lb-open-raw');
            const lbCopyUrl = document.getElementById('lb-copy-url');
            const lbPrev = document.getElementById('lb-prev');
            const lbNext = document.getElementById('lb-next');
            const lbClose = document.getElementById('lightbox-close');
            let currentLightboxCard = null;

            const filters = {
                query: '',
                window: 'all',
                status: 'all',
                sort: 'newest',
            };

            const escapeHtml = (str) => String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');

            const formatWIB = (isoStr) => {
                if (!isoStr) return '-';
                try {
                    const d = new Date(isoStr);
                    const parts = new Intl.DateTimeFormat('id-ID', {
                        timeZone: 'Asia/Jakarta',
                        day: '2-digit',
                        month: 'short',
                        year: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit',
                        second: '2-digit',
                        hour12: false,
                    }).formatToParts(d);

                    const day = parts.find((p) => p.type === 'day')?.value || '';
                    const month = parts.find((p) => p.type === 'month')?.value || '';
                    const year = parts.find((p) => p.type === 'year')?.value || '';
                    const hour = parts.find((p) => p.type === 'hour')?.value || '00';
                    const minute = parts.find((p) => p.type === 'minute')?.value || '00';
                    const second = parts.find((p) => p.type === 'second')?.value || '00';

                    return `${day} ${month} ${year}, ${hour}:${minute}:${second} WIB`;
                } catch {
                    return isoStr;
                }
            };

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

            const applyFilters = () => {
                const q = filters.query.toLowerCase().trim();
                let visibleCount = 0;

                const sortedCards = [...cards].sort((a, b) => {
                    const idA = Number(a.dataset.id || 0);
                    const idB = Number(b.dataset.id || 0);
                    return filters.sort === 'oldest' ? idA - idB : idB - idA;
                });

                sortedCards.forEach((card) => grid.appendChild(card));

                sortedCards.forEach((card) => {
                    const fn = card.dataset.filename || '';
                    const win = card.dataset.window || '';
                    const st = card.dataset.status || '';

                    const matchQuery = !q || fn.includes(q) || win.includes(q);
                    const matchWindow = filters.window === 'all' || win === filters.window;
                    const matchStatus = filters.status === 'all' || st === filters.status;

                    const isVisible = matchQuery && matchWindow && matchStatus;
                    card.classList.toggle('hidden', !isVisible);
                    if (isVisible) visibleCount++;
                });

                if (emptyState) {
                    emptyState.classList.toggle('hidden', cards.length === 0 || visibleCount > 0);
                }
            };

            if (searchInput) {
                searchInput.addEventListener('input', (e) => {
                    filters.query = e.target.value;
                    applyFilters();
                });
            }

            const closeAllDropdowns = () => {
                dropdowns.forEach((dd) => {
                    const menu = dd.querySelector('.filter-dropdown-menu');
                    if (menu) menu.classList.add('hidden');
                });
            };

            dropdowns.forEach((dd) => {
                const trigger = dd.querySelector('.filter-trigger');
                const menu = dd.querySelector('.filter-dropdown-menu');
                const valLabel = dd.querySelector('.filter-value');
                const fKey = dd.dataset.filter;

                if (!trigger || !menu) return;

                trigger.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const isHidden = menu.classList.contains('hidden');
                    closeAllDropdowns();
                    if (isHidden) menu.classList.remove('hidden');
                });

                if (fKey && valLabel) {
                    menu.querySelectorAll('.filter-option').forEach((optBtn) => {
                        optBtn.addEventListener('click', (e) => {
                            e.stopPropagation();
                            const val = optBtn.dataset.value || 'All';
                            valLabel.textContent = optBtn.dataset.label || val;
                            filters[fKey] = val.toLowerCase();

                            menu.querySelectorAll('.filter-option').forEach((b) => {
                                b.classList.remove('font-semibold', 'bg-[#F4F5F7]', 'text-[#111315]');
                            });
                            optBtn.classList.add('font-semibold', 'bg-[#F4F5F7]', 'text-[#111315]');

                            closeAllDropdowns();
                            applyFilters();
                        });
                    });
                }
            });

            document.addEventListener('click', closeAllDropdowns);

            if (resetBtn) {
                resetBtn.addEventListener('click', () => {
                    filters.query = '';
                    filters.window = 'all';
                    filters.status = 'all';
                    filters.sort = 'newest';

                    if (searchInput) searchInput.value = '';
                    dropdowns.forEach((dd) => {
                        const valLabel = dd.querySelector('.filter-value');
                        const fKey = dd.dataset.filter;
                        if (valLabel) {
                            valLabel.textContent = fKey === 'sort' ? 'Terbaru' : 'All';
                        }
                    });

                    applyFilters();
                    notify('Filter direset');
                });
            }

            // Lightbox modal preview + navigasi antar screenshot
            const getVisibleCards = () => Array.from(grid.querySelectorAll('.screenshot-card:not(.hidden)'));

            const openLightbox = (card) => {
                if (!lightbox || !card) return;
                currentLightboxCard = card;
                const img = card.dataset.img || '';
                const win = card.dataset.windowRaw || card.dataset.window || 'Screenshot';
                const res = card.dataset.resolution || '';
                const size = card.dataset.size || '';
                const date = card.dataset.date || '';
                const filename = card.dataset.filenameRaw || card.dataset.filename || '';

                if (lbImage) lbImage.src = img;
                if (lbWindow) lbWindow.textContent = win;
                if (lbMeta) lbMeta.textContent = `${res} · ${size} · ${date}`;
                if (lbFilename) lbFilename.textContent = filename;
                if (lbOpenRaw) lbOpenRaw.href = img || '#';

                lightbox.classList.remove('is-hidden');
            };

            const navigateLightbox = (delta) => {
                if (!currentLightboxCard) return;
                const visible = getVisibleCards();
                if (visible.length <= 1) return;
                const idx = visible.indexOf(currentLightboxCard);
                if (idx === -1) return;
                const nextIdx = (idx + delta + visible.length) % visible.length;
                openLightbox(visible[nextIdx]);
            };

            const closeLightbox = () => {
                if (lightbox) lightbox.classList.add('is-hidden');
                currentLightboxCard = null;
            };

            document.querySelectorAll('[data-preview-trigger]').forEach((wrapper) => {
                wrapper.addEventListener('click', () => {
                    const card = wrapper.closest('.screenshot-card');
                    openLightbox(card);
                });
            });

            document.querySelectorAll('.copy-url-btn').forEach((btn) => {
                btn.addEventListener('click', async (e) => {
                    e.stopPropagation();
                    const url = btn.dataset.url;
                    if (!url) return;
                    try {
                        await navigator.clipboard.writeText(url);
                        notify('URL gambar disalin');
                    } catch {
                        notify('Gagal menyalin URL');
                    }
                });
            });

            lbCopyUrl?.addEventListener('click', async () => {
                const url = currentLightboxCard?.dataset.img;
                if (!url) return;
                try {
                    await navigator.clipboard.writeText(url);
                    notify('URL gambar disalin');
                } catch {
                    notify('Gagal menyalin URL');
                }
            });

            lbPrev?.addEventListener('click', () => navigateLightbox(-1));
            lbNext?.addEventListener('click', () => navigateLightbox(1));
            lbClose?.addEventListener('click', closeLightbox);
            lightbox?.addEventListener('click', (e) => {
                if (e.target === lightbox) closeLightbox();
            });

            document.addEventListener('keydown', (e) => {
                const isLightboxOpen = lightbox && !lightbox.classList.contains('is-hidden');
                if (e.key === 'Escape') {
                    closeLightbox();
                    closeAllDropdowns();
                } else if (isLightboxOpen && e.key === 'ArrowLeft') {
                    e.preventDefault();
                    navigateLightbox(-1);
                } else if (isLightboxOpen && e.key === 'ArrowRight') {
                    e.preventDefault();
                    navigateLightbox(1);
                } else if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'f' && searchInput) {
                    e.preventDefault();
                    searchInput.focus();
                    searchInput.select();
                }
            });

            // =================================================================
            // REALTIME SCREENSHOT INSERTER (Supabase WebSocket + Auto-Poll)
            // =================================================================
            const insertNewCard = (ss) => {
                if (!ss || !ss.id) return;
                if (document.querySelector(`.screenshot-card[data-id="${ss.id}"]`)) return;

                if (currentFolder && currentFolder !== 'all' && currentFolder !== 'hasil_ss') {
                    const ssUser = (ss.user_name || '').trim().toLowerCase();
                    const targetUser = currentFolder.trim().toLowerCase();
                    if (ssUser !== targetUser) return;
                }

                // Hapus placeholder kosong awal jika ada
                document.getElementById('ss-initial-empty')?.remove();

                const filename = ss.filename || `screenshot_${ss.id}.png`;
                const activeWin = (ss.active_window || '').trim() || 'General Desktop';
                const res = ss.resolution || '1920x1080';
                const size = ss.file_size_kb ? `${Number(ss.file_size_kb).toFixed(1)} KB` : '-';
                const dateWIB = formatWIB(ss.created_at);
                const status = (ss.status || 'success').toLowerCase();
                const imgUrl = ss.image_url || '';

                const statusDot = status === 'success'
                    ? 'bg-emerald-500'
                    : (status === 'pending' ? 'bg-amber-500' : (status === 'failed' ? 'bg-rose-500' : 'bg-[#98A2B3]'));

                const safeFilename = escapeHtml(filename);
                const safeActiveWin = escapeHtml(activeWin);
                const safeRes = escapeHtml(res);
                const safeDate = escapeHtml(dateWIB);
                const safeImg = escapeHtml(imgUrl);

                const card = document.createElement('article');
                card.className = 'screenshot-card new-screenshot-highlight group bg-white rounded-xl border border-[#E6E8EC] hover:border-[#BFC6D0] overflow-hidden flex flex-col transition-colors duration-150';
                card.dataset.id = ss.id;
                card.dataset.filename = filename.toLowerCase();
                card.dataset.filenameRaw = filename;
                card.dataset.window = activeWin.toLowerCase();
                card.dataset.windowRaw = activeWin;
                card.dataset.user = (ss.user_name || '').toLowerCase();
                card.dataset.status = status;
                card.dataset.img = imgUrl;
                card.dataset.resolution = res;
                card.dataset.size = size;
                card.dataset.date = dateWIB;

                card.innerHTML = `
                    <div class="relative screenshot-img-wrapper w-full bg-[#111315] overflow-hidden cursor-pointer border-b border-[#EAECEF]" data-preview-trigger>
                        <img
                            src="${safeImg}"
                            alt="${safeFilename}"
                            loading="lazy"
                            class="w-full h-full object-cover object-top"
                            onerror="this.onerror=null; this.src='https://placehold.co/600x338/111315/98a2b3?text=No+Preview';"
                        />
                        <div class="absolute inset-0 bg-[#111315]/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-white text-[#111315] text-[12px] font-medium shadow-sm">
                                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="11" cy="11" r="8"/>
                                    <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                                </svg>
                                <span>Perbesar</span>
                            </span>
                        </div>
                        <div class="absolute bottom-2 right-2">
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded bg-[#111315]/75 backdrop-blur-xs text-white/95 font-mono text-[10.5px] tabular-nums">
                                ${safeRes}
                            </span>
                        </div>
                    </div>
                    <div class="p-3.5 flex-1 flex flex-col justify-between gap-3">
                        <div class="min-w-0">
                            <h3 class="text-[13px] font-semibold text-[#111315] truncate" title="${safeActiveWin}">${safeActiveWin}</h3>
                            <p class="font-mono text-[11px] text-[#7A818A] truncate mt-0.5" title="${safeFilename}">${safeFilename}</p>
                        </div>
                        <div class="pt-2.5 border-t border-[#F2F3F5] flex items-center justify-between gap-2 text-[11px] text-[#667085]">
                            <div class="flex items-center gap-1.5 min-w-0">
                                <span class="w-1.5 h-1.5 rounded-full shrink-0 ${statusDot}" title="Status: ${escapeHtml(status)}"></span>
                                <span class="truncate tabular-nums" title="${safeDate}">${safeDate}</span>
                            </div>
                            <div class="flex items-center gap-1.5 shrink-0">
                                <span class="font-mono text-[11px] text-[#344054] tabular-nums">${escapeHtml(size)}</span>
                                ${imgUrl ? `
                                    <button
                                        type="button"
                                        class="copy-url-btn inline-flex items-center justify-center w-6 h-6 rounded-md hover:bg-[#F2F4F7] text-[#667085] hover:text-[#111315] transition-colors cursor-pointer"
                                        data-url="${safeImg}"
                                        title="Salin URL gambar"
                                        aria-label="Salin URL gambar"
                                    >
                                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                                            <rect x="9" y="9" width="13" height="13" rx="2" ry="2"/>
                                            <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                                        </svg>
                                    </button>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                `;

                card.querySelector('[data-preview-trigger]')?.addEventListener('click', () => openLightbox(card));
                card.querySelector('.copy-url-btn')?.addEventListener('click', async (e) => {
                    e.stopPropagation();
                    if (imgUrl) {
                        await navigator.clipboard.writeText(imgUrl);
                        notify('URL gambar disalin');
                    }
                });

                grid.prepend(card);
                cards.unshift(card);

                if (countBadge) {
                    const currentCount = parseInt(countBadge.textContent, 10) || 0;
                    countBadge.textContent = `${currentCount + 1} ss`;
                }

                if (ss.device_name) {
                    const devEl = document.getElementById('detail-device-name');
                    if (devEl) {
                        devEl.textContent = ss.device_name;
                        devEl.title = ss.device_name;
                        devEl.classList.add('text-emerald-600');
                        setTimeout(() => devEl.classList.remove('text-emerald-600'), 1400);
                    }
                }
                if (ss.resolution) {
                    const resEl = document.getElementById('detail-resolution');
                    if (resEl) {
                        resEl.textContent = ss.resolution;
                        resEl.title = ss.resolution;
                        resEl.classList.add('text-emerald-600');
                        setTimeout(() => resEl.classList.remove('text-emerald-600'), 1400);
                    }
                }
                if (ss.status) {
                    const stEl = document.getElementById('detail-ss-status');
                    if (stEl) {
                        stEl.textContent = ss.status;
                    }
                }

                if (emptyState) emptyState.classList.add('hidden');
                notify(`Capture baru: ${activeWin}`);
            };

            if (window.supabase) {
                const channelName = `hasil_ss_channel_${currentFolder.replace(/[^a-zA-Z0-9_]/g, '_')}`;
                let channelBuilder = window.supabase.channel(channelName);

                if (currentFolder && currentFolder !== 'all' && currentFolder !== 'hasil_ss') {
                    channelBuilder = channelBuilder.on('postgres_changes', {
                        event: 'INSERT',
                        schema: 'public',
                        table: 'hasil_ss',
                        filter: `user_name=eq.${currentFolder}`,
                    }, (payload) => {
                        insertNewCard(payload.new);
                    });
                } else {
                    channelBuilder = channelBuilder.on('postgres_changes', {
                        event: 'INSERT',
                        schema: 'public',
                        table: 'hasil_ss',
                    }, (payload) => {
                        insertNewCard(payload.new);
                    });
                }

                channelBuilder.subscribe();

                setInterval(async () => {
                    try {
                        const topCard = grid.querySelector('.screenshot-card');
                        const maxId = topCard ? Number(topCard.dataset.id || 0) : 0;

                        let query = window.supabase
                            .from('hasil_ss')
                            .select('*')
                            .gt('id', maxId)
                            .order('id', { ascending: true });

                        if (currentFolder && currentFolder !== 'all' && currentFolder !== 'hasil_ss') {
                            query = query.eq('user_name', currentFolder);
                        }

                        const { data, error } = await query;
                        if (!error && Array.isArray(data) && data.length > 0) {
                            data.forEach((item) => insertNewCard(item));
                        }
                    } catch (_) {
                        // silent fallback
                    }
                }, 3500);
            }
        });
    </script>
@endpush
