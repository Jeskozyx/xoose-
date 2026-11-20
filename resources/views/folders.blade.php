{{--
    ==========================================================================
    Halaman Folders Detail — Menampilkan Data Tangkapan Layar (hasil_ss) Realtime
    Terkonfigurasi Terisolasi per User (user_name)
    Path: resources/views/folders.blade.php
    Extends: layouts.app (memuat sidebar, vite css/js, dll.)
    ==========================================================================
--}}

@extends('layouts.app')

@section('title', config('app.name', 'Xoose') . ' — Folders (' . $folderName . ')')

@php
    $activeMenu = 'folders';
@endphp

@push('styles')
    <style>
        .screenshot-card {
            box-shadow: 0 4px 20px -6px rgba(16, 24, 40, 0.05), 0 1px 3px rgba(16, 24, 40, 0.03);
            transition: transform 0.22s cubic-bezier(0.22, 1, 0.36, 1), box-shadow 0.22s ease;
        }

        .screenshot-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 14px 28px -10px rgba(16, 24, 40, 0.12), 0 2px 8px rgba(16, 24, 40, 0.04);
        }

        .screenshot-img-wrapper {
            aspect-ratio: 16 / 9;
        }

        .screenshot-img-wrapper img {
            transition: transform 0.35s cubic-bezier(0.22, 1, 0.36, 1);
        }

        .screenshot-card:hover .screenshot-img-wrapper img {
            transform: scale(1.035);
        }

        .filter-dropdown-menu {
            transform-origin: top right;
            transition: opacity 0.15s ease, transform 0.15s ease;
        }

        /* Lightbox modal animation */
        #lightbox-modal {
            transition: opacity 0.2s ease, visibility 0.2s ease;
        }
        #lightbox-modal.is-hidden {
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
        }

        @keyframes newCardSlide {
            0% {
                opacity: 0;
                transform: translateY(-24px) scale(0.94);
            }
            100% {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .new-screenshot-highlight {
            animation: newCardSlide 0.5s cubic-bezier(0.22, 1, 0.36, 1);
            box-shadow: 0 0 0 2px #3BB77E, 0 14px 28px -10px rgba(16, 24, 40, 0.15) !important;
        }
    </style>
@endpush

@section('content')
    {{-- =====================================================================
         TOP BAR FOLDERS (Breadcrumb + User Switcher + Search ⌘ F + Stats)
         ===================================================================== --}}
    <header class="flex items-center justify-between gap-4 px-5 sm:px-8 h-[68px] border-b border-[#F0F1F3] shrink-0 bg-white">
        <div class="flex items-center gap-3.5 flex-1 max-w-[480px]">
            {{-- Tombol Toggle Sidebar (Desktop & Mobile) --}}
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

            {{-- Breadcrumb Navigasi --}}
            <nav class="flex items-center gap-2 text-[13px] text-[#6F767E] shrink-0" aria-label="Breadcrumb">
                <a href="{{ route('dashboard') }}" class="hover:text-[#1A1D1F] transition-colors font-medium">
                    Dashboard
                </a>
                <span class="text-[#D3D7DF]">/</span>
                <span class="text-[#1A1D1F] font-semibold truncate max-w-[110px] sm:max-w-[150px]">
                    {{ $folderName }}
                </span>
            </nav>

            {{-- Kotak Pencarian Screenshot --}}
            <label class="relative flex items-center w-full h-[38px] rounded-[11px] bg-[#FAFAFB] border border-[#E7E9ED] px-3 gap-2 focus-within:bg-white focus-within:border-[#CFD4DC] focus-within:ring-2 focus-within:ring-black/[0.03] transition-all cursor-text ml-1">
                <svg class="w-[15px] h-[15px] text-[#9A9FA8] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="7"/>
                    <path d="M20 20L16.2 16.2"/>
                </svg>
                <input
                    id="ss-search-input"
                    type="text"
                    placeholder="Cari window / file..."
                    autocomplete="off"
                    class="w-full bg-transparent text-[13px] text-[#1A1D1F] placeholder-[#9A9FA8] focus:outline-none"
                />
            </label>
        </div>

        {{-- Toolbar Kanan: Switcher User, Indikator Total, & Tombol Kembali --}}
        <div class="flex items-center gap-2.5 shrink-0">
            {{-- Dropdown Pilih User (Switch Folder) --}}
            <div class="relative filter-dropdown" data-dropdown="user-switch">
                <button
                    type="button"
                    class="filter-trigger inline-flex items-center gap-1.5 h-[34px] px-3 rounded-xl bg-[#FAFAFB] hover:bg-[#F2F3F5] border border-[#E6E8EC] text-[12.5px] transition-colors font-medium text-[#1A1D1F] cursor-pointer"
                    title="Ganti User / Folder"
                >
                    <svg class="w-3.5 h-3.5 text-[#6F767E]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                        <circle cx="12" cy="7" r="4"/>
                    </svg>
                    <span class="max-w-[85px] sm:max-w-[120px] truncate">{{ $folderName }}</span>
                    <svg class="w-3.5 h-3.5 text-[#7C828D]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M6 9l6 6 6-6"/>
                    </svg>
                </button>
                <div class="filter-dropdown-menu hidden absolute right-0 mt-1.5 w-56 bg-white rounded-xl border border-[#EAECEF] shadow-lg py-1.5 z-30">
                    <div class="px-3 py-1 text-[11px] font-semibold text-[#9A9FA8] uppercase tracking-wider">
                        Pilih User (Folder)
                    </div>
                    @foreach ($folders as $f)
                        <a
                            href="{{ route('folders.index', ['folder' => $f['title']]) }}"
                            class="flex items-center justify-between px-3 py-1.5 text-[12.5px] text-[#1A1D1F] hover:bg-[#F5F6F8] {{ $folderName === $f['title'] ? 'bg-[#F0F7FF] font-semibold text-[#2B78C5]' : '' }}"
                        >
                            <span class="truncate">{{ $f['title'] }}</span>
                            <span class="text-[11px] px-1.5 py-0.5 rounded-full bg-[#F4F5F7] text-[#6F767E] shrink-0">
                                {{ $f['items'] }} ss
                            </span>
                        </a>
                    @endforeach
                    <div class="border-t border-[#F0F1F3] my-1"></div>
                    <a
                        href="{{ route('folders.index', ['folder' => 'all']) }}"
                        class="flex items-center justify-between px-3 py-1.5 text-[12px] text-[#6F767E] hover:text-[#1A1D1F] hover:bg-[#F5F6F8] {{ $folderName === 'all' ? 'font-semibold text-[#2B78C5]' : '' }}"
                    >
                        <span>Semua Screenshot (All)</span>
                    </a>
                </div>
            </div>

            {{-- Badge Indikator Realtime --}}
            <span id="realtime-status-pill" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-[#EBF6FE] text-[#2B78C5] text-[12.5px] font-semibold">
                <span class="w-2 h-2 rounded-full bg-[#3BB77E] animate-pulse"></span>
                <span id="ss-total-count">{{ count($screenshots) }} ss</span>
            </span>

            {{-- Tombol Kembali ke Dashboard --}}
            <a
                href="{{ route('dashboard') }}"
                class="hidden sm:inline-flex items-center gap-1.5 px-3 h-[34px] rounded-xl border border-[#E6E8EC] hover:bg-[#FAFAFB] text-[12.5px] font-medium text-[#1A1D1F] transition-colors"
            >
                <svg class="w-3.5 h-3.5 text-[#6F767E]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M19 12H5M12 19l-7-7 7-7"/>
                </svg>
                <span>Dashboard</span>
            </a>
        </div>
    </header>

    {{-- =====================================================================
         KONTEN UTAMA GALERI TANGKAPAN LAYAR (hasil_ss)
         ===================================================================== --}}
    <div class="flex-1 px-5 sm:px-8 py-7 overflow-y-auto">

        {{-- Header Judul & Toolbar Filter --}}
        <div class="flex flex-col xl:flex-row xl:items-center justify-between gap-4 mb-7">
            <div>
                <div class="flex items-center gap-2.5">
                    <h1 class="text-[26px] sm:text-[28px] font-semibold tracking-[-0.025em] text-[#1A1D1F] leading-tight">
                        Folder User: <span class="text-[#2B78C5]">{{ $folderName }}</span>
                    </h1>
                    <span id="live-indicator-pill" class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-[#DEF7EC] text-[#03543F] text-[11.5px] font-semibold">
                        <span class="w-2 h-2 rounded-full bg-[#3BB77E] animate-ping"></span>
                        <span>User Isolated</span>
                    </span>
                </div>
                <p class="text-[13.5px] text-[#7C828D] mt-1">
                    @if ($folderName !== 'all' && $folderName !== 'hasil_ss')
                        Hanya menampilkan tangkapan layar milik user <strong class="text-[#1A1D1F]">{{ $folderName }}</strong>. Screenshot dari user lain tidak akan bercampur di sini.
                    @else
                        Menampilkan seluruh tangkapan layar dari tabel <code class="px-1.5 py-0.5 rounded bg-[#F4F5F7] text-[#1A1D1F] font-mono text-[12px]">hasil_ss</code>.
                    @endif
                </p>
            </div>

            {{-- Filter Pills --}}
            <div class="flex flex-wrap items-center gap-2">
                {{-- Filter: Active Window --}}
                <div class="relative filter-dropdown" data-filter="window">
                    <button
                        type="button"
                        class="filter-trigger inline-flex items-center gap-1.5 h-[34px] px-3.5 rounded-full bg-white border border-[#E6E8EC] hover:border-[#D3D7DF] hover:bg-[#FAFAFB] text-[12.5px] transition-colors"
                    >
                        <span class="text-[#7C828D]">Window:</span>
                        <span class="filter-value font-medium text-[#1A1D1F]">All</span>
                        <svg class="w-3.5 h-3.5 text-[#7C828D] ml-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M6 9l6 6 6-6"/>
                        </svg>
                    </button>
                    <div class="filter-dropdown-menu hidden absolute right-0 mt-1.5 w-56 max-h-64 overflow-y-auto bg-white rounded-xl border border-[#EAECEF] shadow-lg py-1 z-30">
                        <button type="button" data-value="All" class="filter-option w-full text-left px-3.5 py-1.5 text-[12.5px] text-[#1A1D1F] hover:bg-[#F5F6F8] font-semibold bg-[#F8F9FB]">
                            All Windows
                        </button>
                        @foreach ($activeWindows as $w)
                            <button type="button" data-value="{{ $w }}" class="filter-option w-full text-left px-3.5 py-1.5 text-[12.5px] text-[#1A1D1F] hover:bg-[#F5F6F8] truncate" title="{{ $w }}">
                                {{ $w }}
                            </button>
                        @endforeach
                    </div>
                </div>

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
                        @foreach (['All', 'success', 'pending', 'failed'] as $opt)
                            <button type="button" data-value="{{ $opt }}" class="filter-option w-full text-left px-3.5 py-1.5 text-[12.5px] text-[#1A1D1F] hover:bg-[#F5F6F8] {{ $opt === 'All' ? 'font-semibold bg-[#F8F9FB]' : '' }}">
                                {{ ucfirst($opt) }}
                            </button>
                        @endforeach
                    </div>
                </div>

                {{-- Filter: Sorting --}}
                <div class="relative filter-dropdown" data-filter="sort">
                    <button
                        type="button"
                        class="filter-trigger inline-flex items-center gap-1.5 h-[34px] px-3.5 rounded-full bg-white border border-[#E6E8EC] hover:border-[#D3D7DF] hover:bg-[#FAFAFB] text-[12.5px] transition-colors"
                    >
                        <span class="text-[#7C828D]">Urutkan:</span>
                        <span class="filter-value font-medium text-[#1A1D1F]">Terbaru</span>
                        <svg class="w-3.5 h-3.5 text-[#7C828D] ml-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M6 9l6 6 6-6"/>
                        </svg>
                    </button>
                    <div class="filter-dropdown-menu hidden absolute right-0 mt-1.5 w-40 bg-white rounded-xl border border-[#EAECEF] shadow-lg py-1 z-30">
                        <button type="button" data-value="newest" class="filter-option w-full text-left px-3.5 py-1.5 text-[12.5px] text-[#1A1D1F] hover:bg-[#F5F6F8] font-semibold bg-[#F8F9FB]">
                            Terbaru (ID Desc)
                        </button>
                        <button type="button" data-value="oldest" class="filter-option w-full text-left px-3.5 py-1.5 text-[12.5px] text-[#1A1D1F] hover:bg-[#F5F6F8]">
                            Terlama (ID Asc)
                        </button>
                    </div>
                </div>

                {{-- Tombol Reset Filter --}}
                <button
                    type="button"
                    id="reset-ss-filters"
                    title="Reset semua filter"
                    class="inline-flex items-center justify-center w-[34px] h-[34px] rounded-full bg-white border border-[#E6E8EC] hover:border-[#D3D7DF] hover:bg-[#FAFAFB] text-[#525866] transition-colors cursor-pointer"
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
             GRID GALLERY SCREENSHOTS
             ================================================================= --}}
        <div
            id="screenshots-grid"
            class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5"
        >
            @forelse ($screenshots as $ss)
                @php
                    $imgUrl = $ss['image_url'] ?? '';
                    $filename = $ss['filename'] ?? ('screenshot_' . $ss['id'] . '.png');
                    $activeWindow = !empty(trim((string)($ss['active_window'] ?? ''))) ? $ss['active_window'] : 'General Desktop';
                    $resolution = $ss['resolution'] ?? '1920x1080';
                    $fileSize = isset($ss['file_size_kb']) ? number_format((float)$ss['file_size_kb'], 1) . ' KB' : '-';
                    $ssUser = $ss['user_name'] ?? 'unknown';
                    
                    // Format Jam Indonesia (WIB - Asia/Jakarta)
                    $createdAt = !empty($ss['created_at'])
                        ? \Illuminate\Support\Carbon::parse($ss['created_at'])->setTimezone('Asia/Jakarta')->locale('id')->translatedFormat('d M Y, H:i') . ' WIB'
                        : '-';
                    $fullDateTip = !empty($ss['created_at'])
                        ? \Illuminate\Support\Carbon::parse($ss['created_at'])->setTimezone('Asia/Jakarta')->locale('id')->translatedFormat('l, d F Y - H:i:s') . ' WIB'
                        : '-';
                    $status = strtolower($ss['status'] ?? 'success');
                @endphp

                <article
                    class="screenshot-card group bg-white rounded-[20px] border border-[#ECEEF2] overflow-hidden flex flex-col"
                    data-id="{{ $ss['id'] }}"
                    data-filename="{{ strtolower($filename) }}"
                    data-window="{{ strtolower($activeWindow) }}"
                    data-user="{{ strtolower($ssUser) }}"
                    data-status="{{ $status }}"
                    data-img="{{ $imgUrl }}"
                    data-resolution="{{ $resolution }}"
                    data-size="{{ $fileSize }}"
                    data-date="{{ $createdAt }}"
                >
                    {{-- Thumbnail Gambar --}}
                    <div class="relative screenshot-img-wrapper w-full bg-[#1A1D1F] overflow-hidden cursor-pointer" data-preview-trigger>
                        @if (!empty($imgUrl))
                            <img
                                src="{{ $imgUrl }}"
                                alt="{{ $filename }}"
                                loading="lazy"
                                class="w-full h-full object-cover object-top"
                                onerror="this.onerror=null; this.src='https://placehold.co/600x338/1f242d/white?text=No+Preview';"
                            />
                        @else
                            <div class="w-full h-full flex items-center justify-center text-[#7C828D] text-[13px]">
                                Preview Tidak Tersedia
                            </div>
                        @endif

                        {{-- Overlay Hover Preview --}}
                        <div class="absolute inset-0 bg-black/35 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-2">
                            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-white/90 text-[#1A1D1F] text-[12px] font-semibold shadow-sm backdrop-blur-sm">
                                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                                    <circle cx="11" cy="11" r="8"/>
                                    <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                                </svg>
                                <span>Perbesar</span>
                            </span>
                        </div>

                        {{-- Badge Resolusi Pojok Kiri Atas --}}
                        <div class="absolute top-2.5 left-2.5">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-black/60 backdrop-blur-md text-white text-[11px] font-medium tracking-tight">
                                {{ $resolution }}
                            </span>
                        </div>

                        {{-- Badge Status Pojok Kanan Atas --}}
                        <div class="absolute top-2.5 right-2.5">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-md {{ $status === 'success' ? 'bg-[#3BB77E]/90 text-white' : 'bg-[#FFBC99] text-[#1A1D1F]' }} text-[11px] font-semibold capitalize backdrop-blur-sm">
                                {{ $status }}
                            </span>
                        </div>
                    </div>

                    {{-- Informasi Kartu Screenshot --}}
                    <div class="p-4 flex-1 flex flex-col justify-between">
                        <div>
                            {{-- Header Window Title --}}
                            <div class="flex items-center gap-1.5 mb-1.5">
                                <svg class="w-3.5 h-3.5 text-[#6F767E] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="2" y="3" width="20" height="14" rx="2"/>
                                    <line x1="8" y1="21" x2="16" y2="21"/>
                                    <line x1="12" y1="17" x2="12" y2="21"/>
                                </svg>
                                <h3 class="text-[13.5px] font-semibold text-[#1A1D1F] truncate" title="{{ $activeWindow }}">
                                    {{ $activeWindow }}
                                </h3>
                            </div>

                            {{-- Nama File Monospace --}}
                            <p class="font-mono text-[11.5px] text-[#7C828D] truncate mb-3" title="{{ $filename }}">
                                {{ $filename }}
                            </p>
                        </div>

                        {{-- Footer Metadata & Aksi --}}
                        <div class="pt-3 border-t border-[#F2F3F5] flex items-center justify-between text-[11.5px] text-[#7C828D]">
                            <span class="truncate" title="{{ $fullDateTip }}">{{ $createdAt }}</span>
                            <div class="flex items-center gap-2 shrink-0">
                                <span class="font-medium text-[#1A1D1F]">{{ $fileSize }}</span>

                                {{-- Tombol Salin URL --}}
                                @if (!empty($imgUrl))
                                    <button
                                        type="button"
                                        class="copy-url-btn p-1 rounded-md hover:bg-[#F4F5F7] text-[#6F767E] hover:text-[#1A1D1F] transition-colors cursor-pointer"
                                        data-url="{{ $imgUrl }}"
                                        title="Salin URL gambar"
                                    >
                                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
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
                <div class="col-span-full py-16 px-6 text-center rounded-2xl border border-dashed border-[#E5E7EB] bg-[#FAFAFC]/60">
                    <div class="w-14 h-14 mx-auto rounded-2xl bg-[#EBF6FE] text-[#2B78C5] flex items-center justify-center mb-4">
                        <svg class="w-7 h-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
                            <circle cx="12" cy="13" r="4"/>
                        </svg>
                    </div>
                    <h3 class="text-[16px] font-semibold text-[#1A1D1F]">Belum Ada Tangkapan Layar untuk User "{{ $folderName }}"</h3>
                    <p class="text-[13px] text-[#7C828D] mt-1.5 max-w-md mx-auto">
                        Akun <strong class="text-[#1A1D1F]">{{ $folderName }}</strong> terpisah dari user lainnya. Tangkapan layar dari user sebelumnya tidak terikut ke akun ini. Saat LoveYou mulai mengambil tangkapan layar untuk user <strong class="text-[#1A1D1F]">{{ $folderName }}</strong>, hasilnya akan langsung tampil di sini secara realtime.
                    </p>
                    <div class="mt-4 inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-white border border-[#E6E8EC] text-[12px] text-[#2B78C5] font-medium shadow-2xs">
                        <span class="w-2 h-2 rounded-full bg-[#3BB77E] animate-pulse"></span>
                        <span>Menunggu tangkapan layar LoveYou (Realtime Aktif)</span>
                    </div>
                </div>
            @endforelse
        </div>

        {{-- State Kosong Jika Filter Pencarian Tidak Cocok --}}
        <div id="ss-empty-state" class="hidden py-16 text-center rounded-2xl border border-dashed border-[#E5E7EB] mt-4">
            <p class="text-[14.5px] font-medium text-[#1A1D1F]">Tidak ada screenshot yang cocok dengan filter</p>
            <p class="text-[13px] text-[#7C828D] mt-1">Coba sesuaikan kata kunci pencarian atau reset filter di atas.</p>
        </div>
    </div>

    {{-- =====================================================================
         LIGHTBOX MODAL PREVIEW SCREENSHOT
         ===================================================================== --}}
    <div
        id="lightbox-modal"
        class="fixed inset-0 z-50 bg-black/85 backdrop-blur-sm flex items-center justify-center p-4 sm:p-7 is-hidden"
        role="dialog"
        aria-modal="true"
        aria-label="Preview Screenshot"
    >
        {{-- Tombol Tutup --}}
        <button
            type="button"
            id="lightbox-close"
            class="absolute top-5 right-5 w-10 h-10 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition-colors cursor-pointer z-10"
            aria-label="Tutup"
        >
            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M18 6L6 18M6 6l12 12"/>
            </svg>
        </button>

        {{-- Kontainer Gambar Modal --}}
        <div class="relative max-w-5xl w-full max-h-[90vh] flex flex-col items-center">
            <div class="w-full flex items-center justify-between text-white/80 text-[13px] mb-2 px-1">
                <span id="lb-window" class="font-semibold text-white truncate max-w-[60%]">Window Title</span>
                <span id="lb-meta" class="font-mono text-white/70">1920x1080 • 212 KB • WIB</span>
            </div>

            <div class="relative w-full rounded-2xl overflow-hidden border border-white/10 shadow-2xl bg-black flex items-center justify-center max-h-[78vh]">
                <img
                    id="lb-image"
                    src=""
                    alt="Preview"
                    class="max-w-full max-h-[78vh] object-contain rounded-xl"
                />
            </div>

            <div class="w-full flex items-center justify-between text-white/70 text-[12px] mt-2.5 px-1">
                <span id="lb-filename" class="font-mono truncate max-w-[50%]">filename.png</span>
                <div class="flex items-center gap-3">
                    <a
                        id="lb-open-raw"
                        href="#"
                        target="_blank"
                        rel="noopener"
                        class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg bg-white/10 hover:bg-white/20 text-white transition-colors"
                    >
                        <span>Buka Gambar Asli</span>
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
            const statusPill = document.getElementById('realtime-status-pill');

            // Lightbox Modal
            const lightbox = document.getElementById('lightbox-modal');
            const lbImage = document.getElementById('lb-image');
            const lbWindow = document.getElementById('lb-window');
            const lbMeta = document.getElementById('lb-meta');
            const lbFilename = document.getElementById('lb-filename');
            const lbOpenRaw = document.getElementById('lb-open-raw');
            const lbClose = document.getElementById('lightbox-close');

            const filters = {
                query: '',
                window: 'all',
                status: 'all',
                sort: 'newest',
            };

            // Format Tanggal Indonesia (WIB - Asia/Jakarta)
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
                        hour12: false
                    }).formatToParts(d);

                    const day = parts.find(p => p.type === 'day')?.value || '';
                    const month = parts.find(p => p.type === 'month')?.value || '';
                    const year = parts.find(p => p.type === 'year')?.value || '';
                    const hour = parts.find(p => p.type === 'hour')?.value || '00';
                    const minute = parts.find(p => p.type === 'minute')?.value || '00';

                    return `${day} ${month} ${year}, ${hour}:${minute} WIB`;
                } catch {
                    return isoStr;
                }
            };

            // Notifikasi Toast Ringan
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

            // Filter & Sort Fungsi
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
                    emptyState.classList.toggle('hidden', visibleCount > 0);
                }
            };

            // Search input listener
            if (searchInput) {
                searchInput.addEventListener('input', (e) => {
                    filters.query = e.target.value;
                    applyFilters();
                });
            }

            // Dropdown filter listener
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
                            valLabel.textContent = val;
                            filters[fKey] = val.toLowerCase();

                            menu.querySelectorAll('.filter-option').forEach((b) => {
                                b.classList.remove('font-semibold', 'bg-[#F8F9FB]');
                            });
                            optBtn.classList.add('font-semibold', 'bg-[#F8F9FB]');

                            closeAllDropdowns();
                            applyFilters();
                        });
                    });
                }
            });

            document.addEventListener('click', closeAllDropdowns);

            // Reset filters
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
                    notify('Filter berhasil direset');
                });
            }

            // Lightbox modal preview
            const openLightbox = (card) => {
                if (!lightbox || !card) return;
                const img = card.dataset.img;
                const win = card.dataset.window;
                const res = card.dataset.resolution;
                const size = card.dataset.size;
                const date = card.dataset.date;
                const filename = card.dataset.filename;

                if (lbImage) lbImage.src = img || '';
                if (lbWindow) lbWindow.textContent = win || 'Screenshot';
                if (lbMeta) lbMeta.textContent = `${res} • ${size} • ${date}`;
                if (lbFilename) lbFilename.textContent = filename || '';
                if (lbOpenRaw) lbOpenRaw.href = img || '#';

                lightbox.classList.remove('is-hidden');
            };

            const closeLightbox = () => {
                if (lightbox) lightbox.classList.add('is-hidden');
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
                        notify('URL gambar disalin ke clipboard');
                    } catch {
                        notify('Gagal menyalin link');
                    }
                });
            });

            if (lbClose) lbClose.addEventListener('click', closeLightbox);
            if (lightbox) {
                lightbox.addEventListener('click', (e) => {
                    if (e.target === lightbox) closeLightbox();
                });
            }

            // Keyboard Shortcuts
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    closeLightbox();
                    closeAllDropdowns();
                }
                if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'f' && searchInput) {
                    e.preventDefault();
                    searchInput.focus();
                    searchInput.select();
                }
            });

            // =================================================================
            // REALTIME SCREENSHOT INSERTER (Supabase WebSocket + Auto-Poll)
            // Terisolasi Khusus per User (user_name)
            // =================================================================
            const insertNewCard = (ss) => {
                if (!ss || !ss.id) return;
                // Hindari duplikasi jika sudah tampil
                if (document.querySelector(`.screenshot-card[data-id="${ss.id}"]`)) return;

                // Isolasi: Jika sedang membuka folder spesifik (bukan 'all'), pastikan user_name cocok
                if (currentFolder && currentFolder !== 'all' && currentFolder !== 'hasil_ss') {
                    const ssUser = (ss.user_name || '').trim().toLowerCase();
                    const targetUser = currentFolder.trim().toLowerCase();
                    if (ssUser !== targetUser) {
                        return; // Tangkapan layar ini milik user lain, jangan tampilkan di sini!
                    }
                }

                const filename = ss.filename || `screenshot_${ss.id}.png`;
                const activeWin = (ss.active_window || '').trim() || 'General Desktop';
                const res = ss.resolution || '1920x1080';
                const size = ss.file_size_kb ? `${Number(ss.file_size_kb).toFixed(1)} KB` : '-';
                const dateWIB = formatWIB(ss.created_at);
                const status = (ss.status || 'success').toLowerCase();
                const imgUrl = ss.image_url || '';

                const card = document.createElement('article');
                card.className = 'screenshot-card new-screenshot-highlight group bg-white rounded-[20px] border border-[#ECEEF2] overflow-hidden flex flex-col';
                card.dataset.id = ss.id;
                card.dataset.filename = filename.toLowerCase();
                card.dataset.window = activeWin.toLowerCase();
                card.dataset.user = (ss.user_name || '').toLowerCase();
                card.dataset.status = status;
                card.dataset.img = imgUrl;
                card.dataset.resolution = res;
                card.dataset.size = size;
                card.dataset.date = dateWIB;

                card.innerHTML = `
                    <div class="relative screenshot-img-wrapper w-full bg-[#1A1D1F] overflow-hidden cursor-pointer" data-preview-trigger>
                        <img
                            src="${imgUrl}"
                            alt="${filename}"
                            loading="lazy"
                            class="w-full h-full object-cover object-top"
                            onerror="this.onerror=null; this.src='https://placehold.co/600x338/1f242d/white?text=No+Preview';"
                        />
                        <div class="absolute inset-0 bg-black/35 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-2">
                            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-white/90 text-[#1A1D1F] text-[12px] font-semibold shadow-sm backdrop-blur-sm">
                                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                                    <circle cx="11" cy="11" r="8"/>
                                    <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                                </svg>
                                <span>Perbesar</span>
                            </span>
                        </div>
                        <div class="absolute top-2.5 left-2.5">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-black/60 backdrop-blur-md text-white text-[11px] font-medium tracking-tight">
                                ${res}
                            </span>
                        </div>
                        <div class="absolute top-2.5 right-2.5">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-md ${status === 'success' ? 'bg-[#3BB77E]/90 text-white' : 'bg-[#FFBC99] text-[#1A1D1F]'} text-[11px] font-semibold capitalize backdrop-blur-sm">
                                ${status}
                            </span>
                        </div>
                    </div>
                    <div class="p-4 flex-1 flex flex-col justify-between">
                        <div>
                            <div class="flex items-center gap-1.5 mb-1.5">
                                <svg class="w-3.5 h-3.5 text-[#6F767E] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="2" y="3" width="20" height="14" rx="2"/>
                                    <line x1="8" y1="21" x2="16" y2="21"/>
                                    <line x1="12" y1="17" x2="12" y2="21"/>
                                </svg>
                                <h3 class="text-[13.5px] font-semibold text-[#1A1D1F] truncate" title="${activeWin}">
                                    ${activeWin}
                                </h3>
                            </div>
                            <p class="font-mono text-[11.5px] text-[#7C828D] truncate mb-3" title="${filename}">
                                ${filename}
                            </p>
                        </div>
                        <div class="pt-3 border-t border-[#F2F3F5] flex items-center justify-between text-[11.5px] text-[#7C828D]">
                            <span class="truncate" title="${dateWIB}">${dateWIB}</span>
                            <div class="flex items-center gap-2 shrink-0">
                                <span class="font-medium text-[#1A1D1F]">${size}</span>
                                ${imgUrl ? `
                                    <button
                                        type="button"
                                        class="copy-url-btn p-1 rounded-md hover:bg-[#F4F5F7] text-[#6F767E] hover:text-[#1A1D1F] transition-colors cursor-pointer"
                                        data-url="${imgUrl}"
                                        title="Salin URL gambar"
                                    >
                                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <rect x="9" y="9" width="13" height="13" rx="2" ry="2"/>
                                            <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                                        </svg>
                                    </button>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                `;

                // Pasang trigger preview & copy
                card.querySelector('[data-preview-trigger]')?.addEventListener('click', () => openLightbox(card));
                card.querySelector('.copy-url-btn')?.addEventListener('click', async (e) => {
                    e.stopPropagation();
                    if (imgUrl) {
                        await navigator.clipboard.writeText(imgUrl);
                        notify('URL gambar disalin ke clipboard');
                    }
                });

                // Sisipkan di baris teratas grid
                grid.prepend(card);
                cards.unshift(card);

                // Update total counter
                if (countBadge) {
                    const currentCount = parseInt(countBadge.textContent) || 0;
                    countBadge.textContent = `${currentCount + 1} ss`;
                }

                if (emptyState) emptyState.classList.add('hidden');

                notify(`📸 Screenshot baru diterima untuk ${currentFolder}: ${activeWin}`);
            };

            // Inisialisasi Realtime Subscription
            if (window.supabase) {
                const channelName = `hasil_ss_channel_${currentFolder.replace(/[^a-zA-Z0-9_]/g, '_')}`;

                let channelBuilder = window.supabase.channel(channelName);

                // Filter Realtime Supabase jika folder spesifik
                if (currentFolder && currentFolder !== 'all' && currentFolder !== 'hasil_ss') {
                    channelBuilder = channelBuilder.on('postgres_changes', {
                        event: 'INSERT',
                        schema: 'public',
                        table: 'hasil_ss',
                        filter: `user_name=eq.${currentFolder}`
                    }, (payload) => {
                        console.log('Realtime new screenshot for ' + currentFolder + ':', payload.new);
                        insertNewCard(payload.new);
                    });
                } else {
                    channelBuilder = channelBuilder.on('postgres_changes', {
                        event: 'INSERT',
                        schema: 'public',
                        table: 'hasil_ss'
                    }, (payload) => {
                        insertNewCard(payload.new);
                    });
                }

                channelBuilder.subscribe((status) => {
                    if (statusPill && status === 'SUBSCRIBED') {
                        statusPill.className = 'inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-[#EBF6FE] text-[#2B78C5] text-[12.5px] font-semibold';
                    }
                });

                // Polling cadangan (setiap 3.5 detik) dengan filter user_name yang sama
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
                    } catch (e) {
                        // ignore error
                    }
                }, 3500);
            }
        });
    </script>
@endpush
