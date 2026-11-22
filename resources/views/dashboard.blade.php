{{--
    ==========================================================================
    Halaman Dashboard "My Workflows" (Terpisah)
    Path: resources/views/dashboard.blade.php
    Extends: layouts.app (otomatis memuat <x-sidebar />)
    ==========================================================================
--}}

@extends('layouts.app')

@section('title', config('app.name', 'Xoose') . ' — My Workflows')

@php
    $activeMenu = 'dashboard';
    $creditsRemaining = $creditsRemaining ?? '2,450';

    /**
     * Data Folders & Workflows dari Supabase.
     * Tidak menggunakan dummy data: hanya muncul saat ada inputan dari database Supabase.
     */
    $folders = $folders ?? [];
    $workflows = $workflows ?? [];

    $folderThemes = [
        'lavender' => [
            'back'     => '#DDD8F8',
            'frontTop' => '#F3F1FE',
            'frontBot' => '#E8E4FB',
            'border'   => '#E2DDF9',
            'extraBg'  => '#DDD7F7',
        ],
        'sky' => [
            'back'     => '#CBE6F8',
            'frontTop' => '#EBF6FE',
            'frontBot' => '#D8EEFC',
            'border'   => '#D0E9FA',
            'extraBg'  => '#CBE6F8',
        ],
        'peach' => [
            'back'     => '#FADCC5',
            'frontTop' => '#FFF4EC',
            'frontBot' => '#FCE7D6',
            'border'   => '#F8DEC9',
            'extraBg'  => '#EFE1D5',
        ],
    ];

    $avatarProfiles = [
        'alex'   => ['bg' => '#DCE4EE', 'skin' => '#F1C5A8', 'hair' => '#2E2622', 'shirt' => '#364152', 'beard' => true],
        'sara'   => ['bg' => '#FCE7D6', 'skin' => '#F4CBB2', 'hair' => '#3B2820', 'shirt' => '#D96B43', 'beard' => false],
        'elena'  => ['bg' => '#E5E0F5', 'skin' => '#ECC1A6', 'hair' => '#252129', 'shirt' => '#2D3748', 'beard' => false],
        'marcus' => ['bg' => '#D9F0F8', 'skin' => '#E6B491', 'hair' => '#4A3122', 'shirt' => '#E07A38', 'beard' => true],
    ];
@endphp

@push('styles')
    <style>
        .workflow-card {
            box-shadow: 0 4px 20px -6px rgba(16, 24, 40, 0.06), 0 1px 3px rgba(16, 24, 40, 0.03);
            transition: transform 0.2s cubic-bezier(0.22, 1, 0.36, 1), box-shadow 0.2s ease;
        }

        .workflow-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 28px -10px rgba(16, 24, 40, 0.11), 0 2px 6px rgba(16, 24, 40, 0.04);
        }

        .folder-card {
            transition: transform 0.22s cubic-bezier(0.22, 1, 0.36, 1);
        }

        .folder-card:hover {
            transform: translateY(-3px);
        }

        .filter-dropdown-menu {
            transform-origin: top right;
            transition: opacity 0.15s ease, transform 0.15s ease;
        }

        /* Realtime Animations */
        @keyframes pulseHighlight {
            0% { transform: scale(1); filter: drop-shadow(0 0 0 rgba(59, 183, 126, 0)); }
            50% { transform: scale(1.02); filter: drop-shadow(0 0 14px rgba(59, 183, 126, 0.45)); }
            100% { transform: scale(1); filter: drop-shadow(0 0 0 rgba(59, 183, 126, 0)); }
        }

        .new-folder-enter {
            animation: folderEntrance 0.45s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        @keyframes folderEntrance {
            0% { opacity: 0; transform: translateY(14px) scale(0.95); }
            100% { opacity: 1; transform: translateY(0) scale(1); }
        }

        .counter-pulse {
            animation: counterGlow 1.2s ease-out;
        }

        @keyframes counterGlow {
            0% { color: #3BB77E; font-weight: 700; transform: scale(1.15); }
            100% { color: #7A7E87; font-weight: 400; transform: scale(1); }
        }
    </style>
@endpush

@section('content')
    {{-- =====================================================================
         TOP BAR DASHBOARD (Search ⌘ F & Credits remaining & Live Realtime)
         ===================================================================== --}}
    <header class="flex items-center justify-between gap-4 px-5 sm:px-8 h-[68px] border-b border-[#F0F1F3] shrink-0">
        <div class="flex items-center gap-3 flex-1 max-w-[380px]">
            {{-- Tombol buka/tutup Sidebar (Mobile & Desktop) --}}
            <button
                type="button"
                data-sidebar-toggle
                class="inline-flex items-center justify-center w-9 h-9 rounded-xl bg-[#F4F5F7] text-[#1A1D1F] hover:bg-[#EAECEF] transition-colors shrink-0 cursor-pointer"
                aria-label="Toggle Sidebar"
                title="Collapse / Expand Sidebar (Ctrl+B)"
            >
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="4" width="18" height="16" rx="2.5"/>
                    <line x1="9" y1="4" x2="9" y2="20"/>
                </svg>
            </button>

            {{-- Kotak Pencarian + Keycap ⌘ F --}}
            <label class="relative flex items-center w-full h-[40px] rounded-[11px] bg-[#FAFAFB] border border-[#E7E9ED] px-3 gap-2.5 focus-within:bg-white focus-within:border-[#CFD4DC] focus-within:ring-2 focus-within:ring-black/[0.03] transition-all cursor-text">
                <svg class="w-[16px] h-[16px] text-[#9A9FA8] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="7"/>
                    <path d="M20 20L16.2 16.2"/>
                </svg>
                <input
                    id="workflow-search-input"
                    type="text"
                    placeholder="Search..."
                    autocomplete="off"
                    class="w-full bg-transparent text-[13.5px] text-[#1A1D1F] placeholder-[#9A9FA8] focus:outline-none"
                />
                <span class="hidden sm:inline-flex items-center gap-1 shrink-0 select-none">
                    <kbd class="inline-flex items-center justify-center w-[22px] h-[22px] rounded-[6px] bg-white border border-[#E6E8EC] text-[11px] font-medium text-[#7C828D] shadow-[0_1px_0_rgba(0,0,0,0.03)]">⌘</kbd>
                    <kbd class="inline-flex items-center justify-center w-[22px] h-[22px] rounded-[6px] bg-white border border-[#E6E8EC] text-[11px] font-medium text-[#7C828D] shadow-[0_1px_0_rgba(0,0,0,0.03)]">F</kbd>
                </span>
            </label>
        </div>

        {{-- Indikator Realtime & Credits Remaining --}}
        <div class="flex items-center gap-3 shrink-0">
            <span id="dashboard-realtime-pill" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-[#EBF6FE] text-[#2B78C5] text-[12px] font-semibold transition-all">
                <span class="w-2 h-2 rounded-full bg-[#3BB77E] animate-pulse"></span>
                <span class="hidden sm:inline">Live Realtime</span>
                <span class="sm:hidden">Live</span>
            </span>

            <div class="flex items-center gap-2 text-[13px] text-[#6F767E]">
                <span class="hidden sm:inline">Credits remaining</span>
                <span class="inline-flex items-center px-2.5 py-1 rounded-full bg-[#F4F5F7] text-[#1A1D1F] font-semibold text-[12px] tabular-nums">
                    {{ $creditsRemaining }}
                </span>
            </div>
        </div>
    </header>

    {{-- =====================================================================
         ISI UTAMA DASHBOARD "MY WORKFLOWS"
         ===================================================================== --}}
    <div class="flex-1 px-5 sm:px-8 py-7 overflow-y-auto">

        {{-- Baris Judul + Toolbar Filter Pill --}}
        <div class="flex flex-col xl:flex-row xl:items-center justify-between gap-4 mb-7">
            <h1 class="text-[26px] sm:text-[28px] font-semibold tracking-[-0.025em] text-[#1A1D1F] leading-tight">
                My Workflows
            </h1>

            <div class="flex flex-wrap items-center gap-2">
                {{-- Filter: Status --}}
                <div class="relative filter-dropdown" data-filter="status">
                    <button
                        type="button"
                        class="filter-trigger inline-flex items-center gap-1.5 h-[34px] px-3.5 rounded-full bg-white border border-[#E6E8EC] hover:border-[#D3D7DF] hover:bg-[#FAFAFB] text-[12.5px] transition-colors"
                    >
                        <span class="text-[#7C828D]">Status:</span>
                        <span class="filter-value font-medium text-[#1A1D1F]">All</span>
                        <svg class="w-3.5 h-3.5 text-[#7C828D] ml-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M6 9l6 6 6-6"/>
                        </svg>
                    </button>
                    <div class="filter-dropdown-menu hidden absolute right-0 mt-1.5 w-36 bg-white rounded-xl border border-[#EAECEF] shadow-lg py-1 z-30">
                        @foreach (['All', 'Active', 'Paused'] as $opt)
                            <button type="button" data-value="{{ $opt }}" class="filter-option w-full text-left px-3.5 py-1.5 text-[12.5px] text-[#1A1D1F] hover:bg-[#F5F6F8] {{ $opt === 'All' ? 'font-semibold bg-[#F8F9FB]' : '' }}">
                                {{ $opt }}
                            </button>
                        @endforeach
                    </div>
                </div>

                {{-- Filter: Integrations --}}
                <div class="relative filter-dropdown" data-filter="integration">
                    <button
                        type="button"
                        class="filter-trigger inline-flex items-center gap-1.5 h-[34px] px-3.5 rounded-full bg-white border border-[#E6E8EC] hover:border-[#D3D7DF] hover:bg-[#FAFAFB] text-[12.5px] transition-colors"
                    >
                        <span class="text-[#7C828D]">Integrations:</span>
                        <span class="filter-value font-medium text-[#1A1D1F]">Any</span>
                        <svg class="w-3.5 h-3.5 text-[#7C828D] ml-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M6 9l6 6 6-6"/>
                        </svg>
                    </button>
                    <div class="filter-dropdown-menu hidden absolute right-0 mt-1.5 w-40 bg-white rounded-xl border border-[#EAECEF] shadow-lg py-1 z-30">
                        @foreach (['Any', 'Gmail', 'OpenAI', 'Todoist', 'Notion', 'Slack'] as $opt)
                            <button type="button" data-value="{{ $opt }}" class="filter-option w-full text-left px-3.5 py-1.5 text-[12.5px] text-[#1A1D1F] hover:bg-[#F5F6F8] {{ $opt === 'Any' ? 'font-semibold bg-[#F8F9FB]' : '' }}">
                                {{ $opt }}
                            </button>
                        @endforeach
                    </div>
                </div>

                {{-- Filter: Date --}}
                <div class="relative filter-dropdown" data-filter="date">
                    <button
                        type="button"
                        class="filter-trigger inline-flex items-center gap-1.5 h-[34px] px-3.5 rounded-full bg-white border border-[#E6E8EC] hover:border-[#D3D7DF] hover:bg-[#FAFAFB] text-[12.5px] transition-colors"
                    >
                        <span class="text-[#7C828D]">Date:</span>
                        <span class="filter-value font-medium text-[#1A1D1F]">Any</span>
                        <svg class="w-3.5 h-3.5 text-[#7C828D] ml-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M6 9l6 6 6-6"/>
                        </svg>
                    </button>
                    <div class="filter-dropdown-menu hidden absolute right-0 mt-1.5 w-40 bg-white rounded-xl border border-[#EAECEF] shadow-lg py-1 z-30">
                        @foreach (['Any', 'Last 3 days', 'Last 7 days', 'Last 14 days'] as $opt)
                            <button type="button" data-value="{{ $opt }}" class="filter-option w-full text-left px-3.5 py-1.5 text-[12.5px] text-[#1A1D1F] hover:bg-[#F5F6F8] {{ $opt === 'Any' ? 'font-semibold bg-[#F8F9FB]' : '' }}">
                                {{ $opt }}
                            </button>
                        @endforeach
                    </div>
                </div>

                {{-- Filter: Tags --}}
                <div class="relative filter-dropdown" data-filter="tag">
                    <button
                        type="button"
                        class="filter-trigger inline-flex items-center gap-1.5 h-[34px] px-3.5 rounded-full bg-white border border-[#E6E8EC] hover:border-[#D3D7DF] hover:bg-[#FAFAFB] text-[12.5px] transition-colors"
                    >
                        <span class="text-[#7C828D]">Tags:</span>
                        <span class="filter-value font-medium text-[#1A1D1F]">All</span>
                        <svg class="w-3.5 h-3.5 text-[#7C828D] ml-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M6 9l6 6 6-6"/>
                        </svg>
                    </button>
                    <div class="filter-dropdown-menu hidden absolute right-0 mt-1.5 w-40 bg-white rounded-xl border border-[#EAECEF] shadow-lg py-1 z-30">
                        @foreach (['All', 'Support', 'Personal', 'Finance', 'Productivity'] as $opt)
                            <button type="button" data-value="{{ $opt }}" class="filter-option w-full text-left px-3.5 py-1.5 text-[12.5px] text-[#1A1D1F] hover:bg-[#F5F6F8] {{ $opt === 'All' ? 'font-semibold bg-[#F8F9FB]' : '' }}">
                                {{ $opt }}
                            </button>
                        @endforeach
                    </div>
                </div>

                {{-- Tombol Reset Filter --}}
                <button
                    type="button"
                    id="reset-filters-btn"
                    title="Reset semua filter"
                    class="inline-flex items-center justify-center w-[34px] h-[34px] rounded-full bg-white border border-[#E6E8EC] hover:border-[#D3D7DF] hover:bg-[#FAFAFB] text-[#525866] transition-colors"
                    aria-label="Reset Filter"
                >
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                        <path d="M4 7h16"/>
                        <path d="M7 12h10"/>
                        <path d="M10 17h4"/>
                    </svg>
                </button>
            </div>
        </div>

        {{-- =================================================================
             SECTION 1: FOLDERS
             ================================================================= --}}
        <section class="mb-9" aria-labelledby="folders-heading">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2.5">
                    <h2 id="folders-heading" class="text-[15.5px] font-semibold text-[#1A1D1F]">
                        Folders
                    </h2>
                    <span id="folders-count" class="inline-flex items-center justify-center min-w-[22px] h-[22px] px-1.5 rounded-full bg-[#F3F4F6] text-[#525866] text-[12px] font-medium">
                        {{ count($folders) }}
                    </span>
                </div>
                <button
                    type="button"
                    class="section-collapse-btn inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[12px] text-[#7C828D] hover:text-[#1A1D1F] hover:bg-[#F4F5F7] transition-colors cursor-pointer select-none"
                    data-target="#folders-content"
                    aria-expanded="true"
                    aria-label="Collapse Folders"
                >
                    <span class="collapse-label font-medium text-[11.5px]">Collapse</span>
                    <svg class="chevron-icon w-3.5 h-3.5 transition-transform duration-200" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M6 9l6 6 6-6"/>
                    </svg>
                </button>
            </div>

            <div id="folders-content" class="collapsible-section is-expanded">
                <div class="collapsible-inner">
                    <div id="folders-grid" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-[repeat(3,minmax(0,1fr))_110px] gap-4 pb-1">
                        @foreach ($folders as $folder)
                            <x-folder-card :folder="$folder" />
                        @endforeach

                        {{-- Slot Garis Putus-Putus Tambah Folder Baru --}}
                        <button
                            type="button"
                            id="new-folder-btn"
                            class="mt-[16px] min-h-[126px] rounded-[18px] border border-dashed border-[#DFE2E8] hover:border-[#B9C0CC] hover:bg-[#FAFAFC] flex flex-col items-center justify-center gap-1.5 text-[#9A9FA8] hover:text-[#525866] transition-all cursor-pointer"
                            aria-label="Buat Folder Baru"
                        >
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                                <path d="M12 5v14M5 12h14"/>
                            </svg>
                            <span class="text-[11.5px] font-medium">New</span>
                        </button>
                    </div>

                    {{-- Pesan hint jika belum ada data folder di Supabase --}}
                    <div id="folders-empty-hint" class="{{ count($folders) === 0 ? '' : 'hidden' }} py-6 text-center rounded-2xl border border-dashed border-[#E5E7EB] mt-3">
                        <p class="text-[13.5px] font-medium text-[#1A1D1F]">Belum ada folder dari Supabase</p>
                        <p class="text-[12.5px] text-[#7C828D] mt-0.5">Folder akan otomatis muncul saat ada inputan baru di tabel <code>user_responses</code>.</p>
                    </div>
                </div>
            </div>
        </section>

        {{-- =================================================================
             SECTION 2: WORKFLOWS
             ================================================================= --}}
        <section aria-labelledby="workflows-heading">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2.5">
                    <h2 id="workflows-heading" class="text-[15.5px] font-semibold text-[#1A1D1F]">
                        Workflows
                    </h2>
                    <span id="workflows-count" class="inline-flex items-center justify-center min-w-[22px] h-[22px] px-1.5 rounded-full bg-[#F3F4F6] text-[#525866] text-[12px] font-medium">
                        {{ count($workflows) }}
                    </span>
                </div>
                <button
                    type="button"
                    class="section-collapse-btn inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[12px] text-[#7C828D] hover:text-[#1A1D1F] hover:bg-[#F4F5F7] transition-colors cursor-pointer select-none"
                    data-target="#workflows-content"
                    aria-expanded="true"
                    aria-label="Collapse Workflows"
                >
                    <span class="collapse-label font-medium text-[11.5px]">Collapse</span>
                    <svg class="chevron-icon w-3.5 h-3.5 transition-transform duration-200" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M6 9l6 6 6-6"/>
                    </svg>
                </button>
            </div>

            <div id="workflows-content" class="collapsible-section is-expanded">
                <div class="collapsible-inner">
                    <div id="workflows-grid" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-5 pb-1">
                @foreach ($workflows as $wf)
                    <article
                        class="workflow-card group bg-white rounded-[22px] border border-[#ECEEF2] overflow-hidden flex flex-col cursor-pointer"
                        data-searchable="workflow"
                        data-title="{{ strtolower($wf['title']) }}"
                        data-status="{{ strtolower($wf['status']) }}"
                        data-tag="{{ strtolower($wf['tag']) }}"
                        data-days="{{ $wf['days_num'] }}"
                        data-integrations="{{ implode(',', $wf['integrations']) }}"
                    >
                        {{-- Banner Scenic + Lekukan Putih (Notch) + Tombol ⋮ --}}
                        <div class="relative h-[106px] w-full overflow-hidden bg-[#1F2937]">
                            @switch($wf['banner'])
                                @case('sunset-clouds')
                                    <svg viewBox="0 0 400 140" preserveAspectRatio="xMidYMid slice" class="w-full h-full">
                                        <defs>
                                            <linearGradient id="bgSunset" x1="0" y1="0" x2="0" y2="1">
                                                <stop offset="0%" stop-color="#2B3E7E"/>
                                                <stop offset="52%" stop-color="#5162A6"/>
                                                <stop offset="100%" stop-color="#E5987E"/>
                                            </linearGradient>
                                            <radialGradient id="cloudGlow" cx="65%" cy="62%" r="48%">
                                                <stop offset="0%" stop-color="#FBD0B0" stop-opacity="0.95"/>
                                                <stop offset="60%" stop-color="#D98395" stop-opacity="0.65"/>
                                                <stop offset="100%" stop-color="#48538E" stop-opacity="0"/>
                                            </radialGradient>
                                        </defs>
                                        <rect width="400" height="140" fill="url(#bgSunset)"/>
                                        <ellipse cx="260" cy="86" rx="170" ry="52" fill="url(#cloudGlow)"/>
                                        <path d="M-10 108C35 88 70 96 112 80C156 64 198 86 244 68C288 50 342 64 410 54V145H-10V108Z" fill="#2A346B" opacity="0.55"/>
                                        <path d="M120 96C155 72 195 70 232 82C265 56 322 52 365 76C384 86 398 86 410 82V140H100L120 96Z" fill="#F2AE93" opacity="0.52"/>
                                        <circle cx="88" cy="30" r="1.2" fill="#FFF" opacity="0.8"/>
                                        <circle cx="195" cy="24" r="1" fill="#FFF" opacity="0.7"/>
                                        <circle cx="318" cy="22" r="1.4" fill="#FFF" opacity="0.85"/>
                                    </svg>
                                    @break

                                @case('milky-way')
                                    <svg viewBox="0 0 400 140" preserveAspectRatio="xMidYMid slice" class="w-full h-full">
                                        <defs>
                                            <linearGradient id="bgMilky" x1="0" y1="0" x2="1" y2="1">
                                                <stop offset="0%" stop-color="#3E5485"/>
                                                <stop offset="48%" stop-color="#4C5A96"/>
                                                <stop offset="100%" stop-color="#273A73"/>
                                            </linearGradient>
                                            <radialGradient id="nebulaBand" cx="46%" cy="42%" r="46%">
                                                <stop offset="0%" stop-color="#E6C5DF" stop-opacity="0.78"/>
                                                <stop offset="45%" stop-color="#8FA6DF" stop-opacity="0.45"/>
                                                <stop offset="100%" stop-color="#2C3E7A" stop-opacity="0"/>
                                            </radialGradient>
                                        </defs>
                                        <rect width="400" height="140" fill="url(#bgMilky)"/>
                                        <ellipse cx="180" cy="58" rx="145" ry="62" transform="rotate(-18 180 58)" fill="url(#nebulaBand)"/>
                                        <g fill="#FFFFFF">
                                            <circle cx="64" cy="28" r="1.2" opacity="0.85"/>
                                            <circle cx="118" cy="46" r="1.4" opacity="0.9"/>
                                            <circle cx="158" cy="22" r="1.1" opacity="0.8"/>
                                            <circle cx="186" cy="56" r="1.6" opacity="0.95"/>
                                            <circle cx="212" cy="34" r="1" opacity="0.7"/>
                                            <circle cx="255" cy="68" r="1.3" opacity="0.85"/>
                                            <circle cx="298" cy="38" r="1.1" opacity="0.8"/>
                                            <circle cx="342" cy="64" r="1.4" opacity="0.75"/>
                                        </g>
                                    </svg>
                                    @break

                                @case('iridescent-jar')
                                    <svg viewBox="0 0 400 140" preserveAspectRatio="xMidYMid slice" class="w-full h-full">
                                        <defs>
                                            <linearGradient id="bgPrism" x1="0" y1="0" x2="1" y2="1">
                                                <stop offset="0%" stop-color="#8A7BA8"/>
                                                <stop offset="50%" stop-color="#B093AE"/>
                                                <stop offset="100%" stop-color="#74699C"/>
                                            </linearGradient>
                                            <linearGradient id="glassBody" x1="0" y1="0" x2="1" y2="1">
                                                <stop offset="0%" stop-color="#E7E4F5" stop-opacity="0.85"/>
                                                <stop offset="40%" stop-color="#C5E6F6" stop-opacity="0.75"/>
                                                <stop offset="72%" stop-color="#F7C9DE" stop-opacity="0.8"/>
                                                <stop offset="100%" stop-color="#9A8AC4" stop-opacity="0.7"/>
                                            </linearGradient>
                                        </defs>
                                        <rect width="400" height="140" fill="url(#bgPrism)"/>
                                        <circle cx="205" cy="70" r="95" fill="#FDE1EE" opacity="0.24"/>
                                        <rect x="148" y="34" width="104" height="110" rx="22" fill="url(#glassBody)" stroke="rgba(255,255,255,0.55)" stroke-width="1.5"/>
                                        <rect x="162" y="18" width="76" height="20" rx="8" fill="#DED7EC" stroke="rgba(255,255,255,0.65)" stroke-width="1.2"/>
                                        <line x1="170" y1="54" x2="170" y2="122" stroke="rgba(255,255,255,0.55)" stroke-width="5" stroke-linecap="round"/>
                                        <ellipse cx="222" cy="78" rx="18" ry="26" fill="#FFFFFF" opacity="0.22"/>
                                    </svg>
                                    @break

                                @case('twilight-bloom')
                                    <svg viewBox="0 0 400 140" preserveAspectRatio="xMidYMid slice" class="w-full h-full">
                                        <defs>
                                            <linearGradient id="bgTwilight" x1="0" y1="0" x2="1" y2="1">
                                                <stop offset="0%" stop-color="#3E82B5"/>
                                                <stop offset="55%" stop-color="#2D5F8A"/>
                                                <stop offset="100%" stop-color="#1E3B59"/>
                                            </linearGradient>
                                        </defs>
                                        <rect width="400" height="140" fill="url(#bgTwilight)"/>
                                        <ellipse cx="90" cy="82" rx="110" ry="60" fill="#8CC5E8" opacity="0.25"/>
                                        <path d="M236 140C236 108 242 84 252 60" stroke="#7BA693" stroke-width="2.2" fill="none"/>
                                        <path d="M272 140C268 112 276 86 292 68" stroke="#6E9987" stroke-width="2" fill="none"/>
                                        <circle cx="252" cy="56" r="11" fill="#F4C7A6" opacity="0.9"/>
                                        <circle cx="242" cy="64" r="8" fill="#E8A98E" opacity="0.85"/>
                                        <circle cx="292" cy="64" r="10" fill="#F8DEC6" opacity="0.9"/>
                                        <circle cx="212" cy="78" r="7" fill="#E6B9A1" opacity="0.75"/>
                                    </svg>
                                    @break

                                @case('luminous-petal')
                                    <svg viewBox="0 0 400 140" preserveAspectRatio="xMidYMid slice" class="w-full h-full">
                                        <defs>
                                            <radialGradient id="petalCore" cx="52%" cy="54%" r="54%">
                                                <stop offset="0%" stop-color="#FAF6F2"/>
                                                <stop offset="48%" stop-color="#C8B9B2"/>
                                                <stop offset="100%" stop-color="#2D2726"/>
                                            </radialGradient>
                                        </defs>
                                        <rect width="400" height="140" fill="#2A2423"/>
                                        <ellipse cx="206" cy="76" rx="130" ry="72" fill="url(#petalCore)"/>
                                        <path d="M126 118C136 62 178 24 212 36C246 22 288 60 282 118C242 94 166 94 126 118Z" fill="#EFEAE6" opacity="0.78"/>
                                        <path d="M158 122C168 78 194 46 212 56C232 46 254 78 258 122H158Z" fill="#FFFFFF" opacity="0.72"/>
                                    </svg>
                                    @break

                                @case('starry-peak')
                                    <svg viewBox="0 0 400 140" preserveAspectRatio="xMidYMid slice" class="w-full h-full">
                                        <defs>
                                            <linearGradient id="bgNight" x1="0" y1="0" x2="0" y2="1">
                                                <stop offset="0%" stop-color="#1E2837"/>
                                                <stop offset="65%" stop-color="#314154"/>
                                                <stop offset="100%" stop-color="#4A5A6C"/>
                                            </linearGradient>
                                        </defs>
                                        <rect width="400" height="140" fill="url(#bgNight)"/>
                                        <g fill="#FFF">
                                            <circle cx="52" cy="24" r="1.2" opacity="0.8"/>
                                            <circle cx="112" cy="36" r="1" opacity="0.65"/>
                                            <circle cx="164" cy="18" r="1.4" opacity="0.85"/>
                                            <circle cx="228" cy="30" r="1.1" opacity="0.75"/>
                                            <circle cx="294" cy="20" r="1.3" opacity="0.8"/>
                                            <circle cx="348" cy="36" r="1" opacity="0.7"/>
                                        </g>
                                        <path d="M0 112L66 78L130 98L216 62L292 92L356 68L400 84V140H0V112Z" fill="#18202B" opacity="0.92"/>
                                    </svg>
                                    @break

                                @default
                                    <svg viewBox="0 0 400 140" preserveAspectRatio="xMidYMid slice" class="w-full h-full">
                                        <defs>
                                            <linearGradient id="bgCobalt" x1="0" y1="0" x2="1" y2="1">
                                                <stop offset="0%" stop-color="#2F6EDB"/>
                                                <stop offset="60%" stop-color="#4B8CF0"/>
                                                <stop offset="100%" stop-color="#7AB2FA"/>
                                            </linearGradient>
                                        </defs>
                                        <rect width="400" height="140" fill="url(#bgCobalt)"/>
                                        <ellipse cx="295" cy="94" rx="145" ry="44" fill="#FFFFFF" opacity="0.22"/>
                                    </svg>
                            @endswitch

                            {{-- Tombol Pill ⋮ Mengambang --}}
                            <button
                                type="button"
                                class="card-action-btn absolute right-[12px] bottom-[11px] z-20 inline-flex items-center justify-center w-[38px] h-[24px] rounded-full bg-white text-[#1A1D1F] shadow-[0_2px_10px_rgba(0,0,0,0.16)] hover:scale-105 transition-transform"
                                data-action-label="Opsi workflow {{ $wf['title'] }}"
                                aria-label="Opsi workflow {{ $wf['title'] }}"
                            >
                                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor">
                                    <circle cx="12" cy="6" r="1.8"/>
                                    <circle cx="12" cy="12" r="1.8"/>
                                    <circle cx="12" cy="18" r="1.8"/>
                                </svg>
                            </button>

                            {{-- Lekukan Putih Khas Desain (Notched Shoulder) --}}
                            <div class="absolute bottom-0 left-0 right-[62px] h-[26px] bg-white rounded-tl-[16px] rounded-tr-[18px] z-10"></div>
                            <svg class="absolute bottom-0 right-[46px] w-[16px] h-[16px] z-10 pointer-events-none" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                                <path d="M0 0C0 8.83656 7.16344 16 16 16H0V0Z" fill="#FFFFFF"/>
                            </svg>
                            <svg class="absolute bottom-0 right-0 w-[14px] h-[14px] z-10 pointer-events-none" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                                <path d="M14 0C14 7.73199 7.73199 14 0 14H14V0Z" fill="#FFFFFF"/>
                            </svg>
                        </div>

                        {{-- Body Kartu Putih --}}
                        <div class="relative z-10 bg-white px-5 -mt-[14px] pb-4 flex-1 flex flex-col justify-between">
                            <div>
                                <div class="flex items-center gap-1.5 text-[12px] text-[#8A9099] leading-none">
                                    <span>{{ $wf['time_ago'] }}</span>
                                    <span class="text-[#C8CCD2]">•</span>
                                    <span>{{ $wf['status'] }}</span>
                                </div>

                                <h3 class="mt-2 font-mono text-[15px] font-medium text-[#1A1D1F] tracking-[-0.02em] group-hover:text-black truncate">
                                    {{ $wf['title'] }}
                                </h3>
                            </div>

                            {{-- Avatar Kolaborator + Ikon Integrasi --}}
                            <div class="mt-5 flex items-center justify-between gap-2">
                                <div class="flex items-center -space-x-2">
                                    @foreach ($wf['avatars'] as $avKey)
                                        @php $av = $avatarProfiles[$avKey]; @endphp
                                        <span class="inline-flex w-[26px] h-[26px] rounded-full ring-2 ring-white overflow-hidden">
                                            <svg viewBox="0 0 36 36" class="w-full h-full">
                                                <rect width="36" height="36" fill="{{ $av['bg'] }}"/>
                                                <circle cx="18" cy="14" r="6.5" fill="{{ $av['skin'] }}"/>
                                                <path d="M11.5 13.5C11.5 9.2 14.4 6.5 18 6.5C21.6 6.5 24.5 9.2 24.5 13.5C23 11.2 20.8 10.3 18 10.3C15.2 10.3 13 11.2 11.5 13.5Z" fill="{{ $av['hair'] }}"/>
                                                @if ($av['beard'])
                                                    <path d="M13 16.5C13.8 19.6 15.8 20.8 18 20.8C20.2 20.8 22.2 19.6 23 16.5C21.5 18 19.8 18.4 18 18.4C16.2 18.4 14.5 18 13 16.5Z" fill="{{ $av['hair'] }}" opacity="0.65"/>
                                                @endif
                                                <path d="M6 36C6.8 28.5 11.6 24.5 18 24.5C24.4 24.5 29.2 28.5 30 36H6Z" fill="{{ $av['shirt'] }}"/>
                                            </svg>
                                        </span>
                                    @endforeach
                                </div>

                                <div class="flex items-center -space-x-1">
                                    @foreach ($wf['integrations'] as $app)
                                        @switch($app)
                                            @case('gmail')
                                                <span title="Gmail" class="inline-flex items-center justify-center w-[24px] h-[24px] rounded-[7px] bg-[#141517] ring-2 ring-white">
                                                    <svg class="w-[14px] h-[14px]" viewBox="0 0 24 24" fill="none">
                                                        <path d="M4 7.5V17.5C4 18.3 4.7 19 5.5 19H7V11.2L12 14.9L17 11.2V19H18.5C19.3 19 20 18.3 20 17.5V7.5C20 6.2 18.5 5.4 17.4 6.2L12 10.2L6.6 6.2C5.5 5.4 4 6.2 4 7.5Z" fill="#EA4335"/>
                                                        <path d="M4 7.5V17.5C4 18.3 4.7 19 5.5 19H7V11.2L4 9V7.5Z" fill="#4285F4"/>
                                                        <path d="M20 7.5V17.5C20 18.3 19.3 19 18.5 19H17V11.2L20 9V7.5Z" fill="#34A853"/>
                                                        <path d="M17 11.2V6.5L19.1 5.9C19.6 6.2 20 6.8 20 7.5V9L17 11.2Z" fill="#FBBC05"/>
                                                    </svg>
                                                </span>
                                                @break

                                            @case('openai')
                                                <span title="OpenAI" class="inline-flex items-center justify-center w-[24px] h-[24px] rounded-[7px] bg-[#141517] text-white ring-2 ring-white">
                                                    <svg class="w-[13px] h-[13px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                                        <circle cx="12" cy="12" r="3"/>
                                                        <path d="M12 3a4.5 4.5 0 0 1 4.5 4.5V12"/>
                                                        <path d="M19.8 7.5a4.5 4.5 0 0 1-1.6 6.1L14.3 15.8"/>
                                                        <path d="M19.8 16.5a4.5 4.5 0 0 1-6.1 1.6L9.7 15.8"/>
                                                        <path d="M12 21a4.5 4.5 0 0 1-4.5-4.5V12"/>
                                                        <path d="M4.2 16.5a4.5 4.5 0 0 1 1.6-6.1L9.7 8.2"/>
                                                        <path d="M4.2 7.5a4.5 4.5 0 0 1 6.1-1.6L14.3 8.2"/>
                                                    </svg>
                                                </span>
                                                @break

                                            @case('todoist')
                                                <span title="Todoist" class="inline-flex items-center justify-center w-[24px] h-[24px] rounded-[7px] bg-[#E44332] text-white ring-2 ring-white">
                                                    <svg class="w-[13px] h-[13px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                                        <path d="M5 8.5L9.5 11L19 5.5"/>
                                                        <path d="M5 13.5L9.5 16L19 10.5"/>
                                                        <path d="M5 18.5L9.5 21L19 15.5"/>
                                                    </svg>
                                                </span>
                                                @break

                                            @case('notion')
                                                <span title="Notion" class="inline-flex items-center justify-center w-[24px] h-[24px] rounded-[7px] bg-[#1F2328] text-white ring-2 ring-white text-[11px] font-bold font-serif">
                                                    N
                                                </span>
                                                @break

                                            @case('slack')
                                                <span title="Slack" class="inline-flex items-center justify-center w-[24px] h-[24px] rounded-[7px] bg-[#4A154B] text-white ring-2 ring-white">
                                                    <svg class="w-[13px] h-[13px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
                                                        <path d="M9 5v14M15 5v14M5 9h14M5 15h14"/>
                                                    </svg>
                                                </span>
                                                @break
                                        @endswitch
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>

            {{-- State Kosong jika belum ada workflow atau pencarian tidak cocok --}}
            <div id="workflows-empty-state" class="{{ count($workflows) === 0 ? '' : 'hidden' }} py-14 text-center rounded-2xl border border-dashed border-[#E5E7EB] mt-4">
                <p class="text-[14.5px] font-medium text-[#1A1D1F]">Belum ada workflow dari Supabase</p>
                <p class="text-[13px] text-[#7C828D] mt-1">Workflow akan otomatis muncul saat ada inputan data di database.</p>
            </div>
                </div>
            </div>
        </section>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            try {
                const searchInput = document.getElementById('workflow-search-input');
                const foldersGrid = document.getElementById('folders-grid');
                const foldersCountEl = document.getElementById('folders-count');
                const workflowsCountEl = document.getElementById('workflows-count');
                const emptyStateEl = document.getElementById('workflows-empty-state');
                const dropdowns = document.querySelectorAll('.filter-dropdown');
                const realtimePill = document.getElementById('dashboard-realtime-pill');

                const folderThemes = @json($folderThemes);
                const avatarProfiles = @json($avatarProfiles);
                const themeKeys = ['lavender', 'sky', 'peach'];
                const avatarPresetKeys = [
                    ['alex'],
                    ['elena', 'marcus'],
                    ['alex', 'sara', 'marcus'],
                    ['sara'],
                    ['alex', 'elena'],
                    ['marcus']
                ];

                const activeFilters = {
                    query: '',
                    status: 'all',
                    integration: 'any',
                    date: 'any',
                    tag: 'all',
                };

                // Notifikasi Toast Elegan
                const notify = (msg) => {
                    let box = document.getElementById('app-toast');
                    if (!box) {
                        box = document.createElement('div');
                        box.id = 'app-toast';
                        box.className = 'fixed bottom-5 right-5 z-50 px-4 py-2.5 rounded-xl bg-[#1A1D1F] text-white text-[13px] shadow-xl border border-white/10 transition-all duration-200 opacity-0 translate-y-2 pointer-events-none';
                        document.body.appendChild(box);
                    }
                    box.textContent = msg;
                    box.classList.remove('opacity-0', 'translate-y-2');
                    clearTimeout(box._timer);
                    box._timer = setTimeout(() => {
                        box.classList.add('opacity-0', 'translate-y-2');
                    }, 3000);
                };

                // Filter Folders & Workflows secara dinamis
                const applyDashboardFilters = () => {
                    const q = activeFilters.query.trim().toLowerCase();

                    let visibleFolders = 0;
                    const currentFolderCards = document.querySelectorAll('[data-searchable="folder"]');
                    currentFolderCards.forEach((card) => {
                        const match = !q || (card.dataset.title || '').includes(q);
                        card.classList.toggle('hidden', !match);
                        if (match) visibleFolders++;
                    });
                    if (foldersCountEl) foldersCountEl.textContent = String(visibleFolders);

                    let visibleWorkflows = 0;
                    const workflowCards = document.querySelectorAll('[data-searchable="workflow"]');
                    workflowCards.forEach((card) => {
                        const title = card.dataset.title || '';
                        const status = card.dataset.status || '';
                        const tag = card.dataset.tag || '';
                        const days = Number(card.dataset.days || 999);
                        const integrations = (card.dataset.integrations || '').split(',');

                        const matchQuery = !q || title.includes(q) || tag.includes(q);
                        const matchStatus = activeFilters.status === 'all' || status === activeFilters.status;
                        const matchIntegration = activeFilters.integration === 'any' || integrations.includes(activeFilters.integration);
                        const matchTag = activeFilters.tag === 'all' || tag === activeFilters.tag;

                        let matchDate = true;
                        if (activeFilters.date === 'last 3 days') matchDate = days <= 3;
                        else if (activeFilters.date === 'last 7 days') matchDate = days <= 7;
                        else if (activeFilters.date === 'last 14 days') matchDate = days <= 14;

                        const isVisible = matchQuery && matchStatus && matchIntegration && matchTag && matchDate;
                        card.classList.toggle('hidden', !isVisible);
                        if (isVisible) visibleWorkflows++;
                    });

                    if (workflowsCountEl) workflowsCountEl.textContent = String(visibleWorkflows);
                    if (emptyStateEl) emptyStateEl.classList.toggle('hidden', visibleWorkflows > 0);
                };

                if (searchInput) {
                    searchInput.addEventListener('input', (e) => {
                        activeFilters.query = e.target.value;
                        applyDashboardFilters();
                    });
                }

                // Interaksi Dropdown Filter Pill
                const closeAllDropdowns = () => {
                    dropdowns.forEach((dd) => {
                        const menu = dd.querySelector('.filter-dropdown-menu');
                        if (menu) menu.classList.add('hidden');
                    });
                };

                dropdowns.forEach((dd) => {
                    const trigger = dd.querySelector('.filter-trigger');
                    const menu = dd.querySelector('.filter-dropdown-menu');
                    const valueLabel = dd.querySelector('.filter-value');
                    const filterKey = dd.dataset.filter;

                    if (!trigger || !menu || !valueLabel || !filterKey) return;

                    trigger.addEventListener('click', (e) => {
                        e.stopPropagation();
                        const isHidden = menu.classList.contains('hidden');
                        closeAllDropdowns();
                        if (isHidden) menu.classList.remove('hidden');
                    });

                    menu.querySelectorAll('.filter-option').forEach((optionBtn) => {
                        optionBtn.addEventListener('click', (e) => {
                            e.stopPropagation();
                            const selected = optionBtn.dataset.value || 'All';
                            valueLabel.textContent = selected;
                            activeFilters[filterKey] = selected.toLowerCase();

                            menu.querySelectorAll('.filter-option').forEach((btn) => {
                                btn.classList.remove('font-semibold', 'bg-[#F8F9FB]');
                            });
                            optionBtn.classList.add('font-semibold', 'bg-[#F8F9FB]');

                            closeAllDropdowns();
                            applyDashboardFilters();
                        });
                    });
                });

                document.addEventListener('click', closeAllDropdowns);

                // Tombol Reset Filter
                const resetBtn = document.getElementById('reset-filters-btn');
                if (resetBtn) {
                    resetBtn.addEventListener('click', () => {
                        activeFilters.query = '';
                        activeFilters.status = 'all';
                        activeFilters.integration = 'any';
                        activeFilters.date = 'any';
                        activeFilters.tag = 'all';

                        if (searchInput) searchInput.value = '';
                        dropdowns.forEach((dd) => {
                            const key = dd.dataset.filter;
                            const valEl = dd.querySelector('.filter-value');
                            const defaultVal = (key === 'integration' || key === 'date') ? 'Any' : 'All';
                            if (valEl) valEl.textContent = defaultVal;
                        });

                        applyDashboardFilters();
                        notify('Semua filter berhasil direset');
                    });
                }

                // Handler klik kartu folder statis awal
                document.querySelectorAll('.folder-card').forEach((card) => {
                    card.addEventListener('click', (e) => {
                        if (e.target.closest('.card-action-btn')) return;
                        const url = card.dataset.url;
                        if (url) window.location.href = url;
                    });
                });

                // Aksi Tombol ⋮ Opsi Folder
                document.querySelectorAll('.card-action-btn').forEach((btn) => {
                    btn.addEventListener('click', (e) => {
                        e.stopPropagation();
                        notify(btn.dataset.actionLabel || 'Menu opsi dibuka');
                    });
                });

                // Tombol "+ New Folder"
                const newFolderBtn = document.getElementById('new-folder-btn');
                if (newFolderBtn) {
                    newFolderBtn.addEventListener('click', async () => {
                        const folderName = prompt('Masukkan nama folder baru:');
                        if (!folderName || !folderName.trim()) return;

                        try {
                            notify('Menyimpan folder ke Supabase...');
                            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                            const res = await fetch('/api/folders', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': csrfToken || '',
                                    'Accept': 'application/json',
                                },
                                body: JSON.stringify({ nama: folderName.trim() }),
                            });

                            const data = await res.json();
                            if (res.ok && data.success) {
                                notify('Folder "' + folderName.trim() + '" berhasil disimpan ke Supabase!');
                                insertNewFolderCard(folderName.trim(), 0);
                            } else {
                                notify('Gagal: ' + (data.message || 'Terjadi kesalahan'));
                            }
                        } catch (err) {
                            notify('Gagal menyimpan: ' + err.message);
                        }
                    });
                }

                // Shortcut Keyboard ⌘F / Ctrl+F & Escape
                document.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape') {
                        closeAllDropdowns();
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

                // Generator Elemen DOM Kartu Folder Pastel (Preserve Design 100%)
                const createFolderElement = (title, itemsCount = 0, index = 0) => {
                    const themeKey = themeKeys[index % themeKeys.length];
                    const t = folderThemes[themeKey] || folderThemes['lavender'];
                    const avKeys = avatarPresetKeys[index % avatarPresetKeys.length];
                    const cleanTitle = title || `Folder ${index + 1}`;
                    const userKey = cleanTitle.trim().toLowerCase();
                    const folderUrl = `/folders?folder=${encodeURIComponent(cleanTitle)}`;

                    let avatarHtml = '';
                    avKeys.forEach(avKey => {
                        const av = avatarProfiles[avKey] || avatarProfiles['alex'];
                        const beardSvg = av.beard
                            ? `<path d="M13 16.5C13.8 19.6 15.8 20.8 18 20.8C20.2 20.8 22.2 19.6 23 16.5C21.5 18 19.8 18.4 18 18.4C16.2 18.4 14.5 18 13 16.5Z" fill="${av.hair}" opacity="0.65"/>`
                            : '';
                        avatarHtml += `
                            <span class="inline-flex w-[26px] h-[26px] rounded-full ring-2 ring-white/90 overflow-hidden">
                                <svg viewBox="0 0 36 36" class="w-full h-full">
                                    <rect width="36" height="36" fill="${av.bg}"/>
                                    <circle cx="18" cy="14" r="6.5" fill="${av.skin}"/>
                                    <path d="M11.5 13.5C11.5 9.2 14.4 6.5 18 6.5C21.6 6.5 24.5 9.2 24.5 13.5C23 11.2 20.8 10.3 18 10.3C15.2 10.3 13 11.2 11.5 13.5Z" fill="${av.hair}"/>
                                    ${beardSvg}
                                    <path d="M6 36C6.8 28.5 11.6 24.5 18 24.5C24.4 24.5 29.2 28.5 30 36H6Z" fill="${av.shirt}"/>
                                </svg>
                            </span>
                        `;
                    });

                    const a = document.createElement('a');
                    a.href = folderUrl;
                    a.className = 'folder-card new-folder-enter block relative pt-[16px] cursor-pointer select-none text-inherit no-underline';
                    a.dataset.searchable = 'folder';
                    a.dataset.title = userKey;
                    a.dataset.user = userKey;
                    a.dataset.url = folderUrl;

                    a.innerHTML = `
                        <div class="absolute top-0 left-[6px] w-[39%] h-[28px] rounded-t-[13px]" style="background-color: ${t.back};"></div>
                        <svg class="absolute top-0 left-[calc(39%+2px)] w-[28px] h-[22px]" viewBox="0 0 28 22" fill="none" aria-hidden="true">
                            <path d="M0 0C7 0 11 10 20 14H28V22H0V0Z" fill="${t.back}"/>
                        </svg>
                        <div class="absolute top-[13px] left-[2px] right-[2px] h-[26px] rounded-t-[14px]" style="background-color: ${t.back};"></div>

                        <div
                            class="relative rounded-[18px] rounded-tl-[14px] px-5 pt-4 pb-4 min-h-[126px] flex flex-col justify-between shadow-[0_6px_18px_-8px_rgba(16,24,40,0.09)] border"
                            style="background: linear-gradient(180deg, ${t.frontTop} 0%, ${t.frontBot} 100%); border-color: ${t.border};"
                        >
                            <div
                                class="absolute -top-[10px] -left-[1px] w-[36%] h-[12px] rounded-tl-[13px] rounded-tr-[10px] border-t border-l"
                                style="background-color: ${t.frontTop}; border-color: ${t.border};"
                            ></div>
                            <svg class="absolute -top-[10px] left-[calc(36%-4px)] w-[22px] h-[12px]" viewBox="0 0 22 12" fill="none" aria-hidden="true">
                                <path d="M0 0.5C6 0.5 10 10.5 18 10.5H22V12H0V0.5Z" fill="${t.frontTop}"/>
                                <path d="M0 0.5C6 0.5 10 10.5 18 10.5H22" stroke="${t.border}" stroke-width="1"/>
                            </svg>

                            <div class="flex justify-end">
                                <button
                                    type="button"
                                    class="card-action-btn inline-flex items-center justify-center w-[30px] h-[22px] rounded-full bg-white/45 hover:bg-white/85 border border-black/[0.06] text-[#525866] transition-colors"
                                    data-action-label="Opsi folder ${cleanTitle}"
                                    aria-label="Opsi folder ${cleanTitle}"
                                    onclick="event.preventDefault(); event.stopPropagation();"
                                >
                                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor">
                                        <circle cx="12" cy="6" r="1.7"/>
                                        <circle cx="12" cy="12" r="1.7"/>
                                        <circle cx="12" cy="18" r="1.7"/>
                                    </svg>
                                </button>
                            </div>

                            <h3 class="font-mono text-[14.5px] font-medium text-[#1A1D1F] tracking-[-0.02em] mt-1 mb-4 truncate">
                                ${cleanTitle}
                            </h3>

                            <div class="flex items-center justify-between gap-2">
                                <div class="flex items-center -space-x-2">
                                    ${avatarHtml}
                                </div>

                                <span class="folder-items-count text-[12.5px] text-[#7A7E87] font-normal transition-colors" data-user="${userKey}">
                                    ${itemsCount} items
                                </span>
                            </div>
                        </div>
                    `;

                    // Pasang event klik
                    a.addEventListener('click', (e) => {
                        if (e.target.closest('.card-action-btn')) return;
                        window.location.href = folderUrl;
                    });

                    const actionBtn = a.querySelector('.card-action-btn');
                    if (actionBtn) {
                        actionBtn.addEventListener('click', (e) => {
                            e.preventDefault();
                            e.stopPropagation();
                            notify(actionBtn.dataset.actionLabel || 'Menu opsi dibuka');
                        });
                    }

                    return a;
                };

                // Sisipkan Kartu Folder Baru ke DOM
                const insertNewFolderCard = (nama, itemsCount = 0) => {
                    if (!nama || !nama.trim()) return;
                    const userKey = nama.trim().toLowerCase();
                    if (document.querySelector(`.folder-card[data-user="${userKey}"]`)) return;

                    if (!foldersGrid || !newFolderBtn) return;

                    const existingCards = foldersGrid.querySelectorAll('.folder-card');
                    const newCard = createFolderElement(nama.trim(), itemsCount, existingCards.length);

                    foldersGrid.insertBefore(newCard, newFolderBtn);

                    // Sembunyikan hint kosong
                    document.getElementById('folders-empty-hint')?.classList.add('hidden');

                    // Update badge total folder
                    if (foldersCountEl) {
                        const curCount = parseInt(foldersCountEl.textContent) || existingCards.length;
                        foldersCountEl.textContent = String(curCount + 1);
                    }

                    notify(`📁 Folder baru "${nama.trim()}" ditambahkan secara realtime!`);
                };

                // Handler Tangkapan Layar Baru (Update Jumlah Screenshot per Folder)
                const handleNewScreenshot = (ss) => {
                    if (!ss) return;
                    const userName = (ss.user_name || '').trim();
                    if (!userName) return;
                    const userKey = userName.toLowerCase();

                    let counterEl = document.querySelector(`.folder-items-count[data-user="${userKey}"]`);
                    if (!counterEl) {
                        // Jika folder belum ada di dashboard, buat baru dengan 1 item
                        insertNewFolderCard(userName, 1);
                        return;
                    }

                    const currentCount = parseInt(counterEl.textContent) || 0;
                    const nextCount = currentCount + 1;
                    counterEl.textContent = `${nextCount} items`;

                    counterEl.classList.remove('counter-pulse');
                    void counterEl.offsetWidth;
                    counterEl.classList.add('counter-pulse');

                    const card = counterEl.closest('.folder-card');
                    if (card) {
                        card.style.animation = 'none';
                        void card.offsetWidth;
                        card.style.animation = 'pulseHighlight 1s ease-out';
                    }

                    notify(`📸 Screenshot baru diterima untuk folder "${userName}" (${nextCount} items)`);
                };

                // Inisialisasi Supabase Realtime Channels
                if (window.supabase) {
                    // 1. Channel Realtime untuk user_responses (auto-add folder)
                    const channelUsers = window.supabase
                        .channel('dashboard_user_responses')
                        .on('postgres_changes', {
                            event: 'INSERT',
                            schema: 'public',
                            table: 'user_responses'
                        }, (payload) => {
                            console.log('Realtime new user_response on dashboard:', payload.new);
                            if (payload.new && payload.new.nama) {
                                insertNewFolderCard(payload.new.nama, 0);
                            }
                        })
                        .subscribe((status) => {
                            if (realtimePill && status === 'SUBSCRIBED') {
                                realtimePill.className = 'inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-[#EBF6FE] text-[#2B78C5] text-[12px] font-semibold transition-all';
                            }
                        });

                    // 2. Channel Realtime untuk hasil_ss (auto-increment count)
                    const channelSS = window.supabase
                        .channel('dashboard_hasil_ss')
                        .on('postgres_changes', {
                            event: 'INSERT',
                            schema: 'public',
                            table: 'hasil_ss'
                        }, (payload) => {
                            console.log('Realtime new hasil_ss on dashboard:', payload.new);
                            handleNewScreenshot(payload.new);
                        })
                        .subscribe((status) => {
                            console.log('Realtime dashboard_hasil_ss status:', status);
                        });

                    // 3. Polling Cadangan (setiap 4 detik) untuk menjaga 100% konsistensi
                    setInterval(async () => {
                        try {
                            // Cek user baru yang belum ada di DOM
                            const { data: users, error: uErr } = await window.supabase
                                .from('user_responses')
                                .select('*')
                                .order('id', { ascending: true });

                            if (!uErr && Array.isArray(users)) {
                                users.forEach(u => {
                                    if (u.nama) {
                                        const k = u.nama.trim().toLowerCase();
                                        if (!document.querySelector(`.folder-card[data-user="${k}"]`)) {
                                            insertNewFolderCard(u.nama, 0);
                                        }
                                    }
                                });
                            }

                            // Cek jumlah screenshot per user_name
                            const { data: ssList, error: sErr } = await window.supabase
                                .from('hasil_ss')
                                .select('user_name');

                            if (!sErr && Array.isArray(ssList)) {
                                const counts = {};
                                ssList.forEach(s => {
                                    const u = (s.user_name || '').trim().toLowerCase();
                                    if (u) counts[u] = (counts[u] || 0) + 1;
                                });

                                Object.keys(counts).forEach(u => {
                                    const el = document.querySelector(`.folder-items-count[data-user="${u}"]`);
                                    if (el) {
                                        const count = counts[u];
                                        const text = `${count} items`;
                                        if (el.textContent.trim() !== text) {
                                            el.textContent = text;
                                        }
                                    }
                                });
                            }
                        } catch (e) {
                            // silent catch
                        }
                    }, 4000);
                }
            } catch (error) {
                console.error('Gagal menginisialisasi interaksi halaman dashboard:', error);
            }
        });
    </script>
@endpush