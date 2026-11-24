{{--
    ==========================================================================
    Komponen Sidebar Navigasi Xoose
    Path: resources/views/components/sidebar.blade.php
    Pemakaian di Blade: <x-sidebar active-key="dashboard" />
    ==========================================================================
--}}

@props([
    'activeKey' => null,
])

@php
    /**
     * Tentukan menu aktif secara otomatis jika belum diset eksplisit.
     */
    if (empty($activeKey) || ($activeKey === 'dashboard' && !request()->routeIs('dashboard') && !request()->is('/') && !request()->is('dashboard'))) {
        if (request()->routeIs('remote.apps.*') || request()->is('remote/apps*')) {
            $activeKey = 'remote.apps';
        } elseif (request()->routeIs('remote.devices.*') || request()->routeIs('remote.services.*') || request()->is('remote/devices*')) {
            $activeKey = 'remote.devices';
        } elseif (request()->routeIs('remote.capture-rules.*') || request()->is('remote/capture-rules*')) {
            $activeKey = 'remote.capture-rules';
        } elseif (request()->routeIs('remote.configs.*') || request()->is('remote/configs*')) {
            $activeKey = 'remote.configs';
        } elseif (request()->routeIs('remote.commands.*') || request()->is('remote/commands*')) {
            $activeKey = 'remote.commands';
        } elseif (request()->routeIs('remote.logs.*') || request()->is('remote/logs*')) {
            $activeKey = 'remote.logs';
        } elseif (request()->routeIs('remote.releases.*') || request()->is('remote/releases*')) {
            $activeKey = 'remote.releases';
        } elseif (request()->routeIs('builder.*') || request()->is('builder*')) {
            $activeKey = 'builder';
        } elseif (request()->routeIs('folders.*') || request()->is('folders*')) {
            $activeKey = 'folders';
        } else {
            $activeKey = 'dashboard';
        }
    }

    /**
     * Helper aman untuk mengambil URL route.
     */
    $resolveUrl = function (string $routeName, string $fallbackUrl): string {
        return \Illuminate\Support\Facades\Route::has($routeName)
            ? route($routeName)
            : url($fallbackUrl);
    };

    /**
     * Menu Utama Workspace Xoose.
     */
    $workspaceItems = [
        [
            'key'   => 'dashboard',
            'label' => 'Dashboard',
            'icon'  => 'dashboard',
            'url'   => $resolveUrl('dashboard', '/'),
        ],
        [
            'key'   => 'folders',
            'label' => 'Folders',
            'icon'  => 'folders',
            'url'   => $resolveUrl('folders.index', '/folders'),
        ],
        [
            'key'   => 'builder',
            'label' => 'App Builder',
            'icon'  => 'builder',
            'url'   => $resolveUrl('builder.projects.index', '/builder/projects'),
        ],
    ];

    /**
     * Menu Remote Control Plane (/remote/*).
     */
    $remoteItems = [
        [
            'key'   => 'remote.apps',
            'label' => 'Applications',
            'icon'  => 'apps',
            'url'   => $resolveUrl('remote.apps.index', '/remote/apps'),
        ],
        [
            'key'   => 'remote.devices',
            'label' => 'Devices',
            'icon'  => 'devices',
            'url'   => $resolveUrl('remote.devices.index', '/remote/devices'),
        ],
        [
            'key'         => 'remote.capture-rules',
            'label'       => 'Capture Rules',
            'icon'        => 'capture',
            'url'         => $resolveUrl('remote.capture-rules.index', '/remote/capture-rules'),
            'badge'       => config('remote-control.capture_rules_enabled', false) ? null : 'BETA',
            'badge_color' => 'amber',
        ],
        [
            'key'   => 'remote.configs',
            'label' => 'Service Config',
            'icon'  => 'configs',
            'url'   => $resolveUrl('remote.configs.index', '/remote/configs'),
        ],
        [
            'key'   => 'remote.commands',
            'label' => 'Commands',
            'icon'  => 'commands',
            'url'   => $resolveUrl('remote.commands.index', '/remote/commands'),
        ],
        [
            'key'   => 'remote.logs',
            'label' => 'Logs & Audit',
            'icon'  => 'logs',
            'url'   => $resolveUrl('remote.logs.index', '/remote/logs'),
        ],
        [
            'key'   => 'remote.releases',
            'label' => 'Releases',
            'icon'  => 'releases',
            'url'   => $resolveUrl('remote.releases.index', '/remote/releases'),
        ],
    ];

    $isRemoteActive = collect($remoteItems)->contains(fn ($item) => $item['key'] === $activeKey);

    $userInitials = 'AD';
    if (auth()->check()) {
        $parts = preg_split('/\s+/', trim((string) auth()->user()->name)) ?: [];
        $initials = collect($parts)->filter()->take(2)->map(fn ($part) => strtoupper(substr($part, 0, 1)))->implode('');
        if ($initials !== '') {
            $userInitials = $initials;
        }
    }
@endphp

{{-- Overlay gelap khusus layar mobile saat sidebar dibuka --}}
<div
    id="sidebar-overlay"
    class="fixed inset-0 bg-[#1A1D1F]/30 backdrop-blur-[2px] z-30 hidden lg:hidden transition-opacity"
    aria-hidden="true"
></div>

<aside
    id="app-sidebar"
    class="fixed lg:sticky top-0 left-0 z-40 h-screen w-[268px] shrink-0 bg-[#F7F8FA] border-r border-[#EAECEF] flex flex-col select-none -translate-x-full lg:translate-x-0 transition-transform duration-300 ease-out"
    aria-label="Sidebar Navigation"
>
    {{-- Header Branding Xoose --}}
    <div class="flex items-center justify-between px-4 pt-5 pb-4 shrink-0">
        <a href="{{ $resolveUrl('dashboard', '/') }}" class="inline-flex items-center gap-3 group focus:outline-none min-w-0">
            <div class="relative flex items-center justify-center w-9 h-9 rounded-xl bg-[#111315] text-white shadow-[0_1px_2px_rgba(16,24,40,0.12)] group-hover:bg-black transition-colors duration-200 shrink-0">
                <x-brand-logo class="w-[21px] h-[21px] transition-transform duration-200 group-hover:scale-105" />
            </div>
            <div class="min-w-0">
                <div class="text-[15.5px] font-bold tracking-tight text-[#1A1D1F] leading-none">
                    {{ config('app.name', 'Xoose') }}
                </div>
                <div class="text-[11px] font-medium text-[#8B919A] mt-1 leading-none truncate">
                    Control Plane
                </div>
            </div>
        </a>

        <div class="flex items-center gap-1 shrink-0">
            {{-- Tombol Collapse Sidebar di Desktop --}}
            <button
                type="button"
                data-sidebar-toggle
                class="hidden lg:inline-flex items-center justify-center w-8 h-8 rounded-lg text-[#7A818A] hover:text-[#1A1D1F] hover:bg-black/[0.05] transition-colors cursor-pointer"
                aria-label="Collapse Sidebar"
                title="Collapse Sidebar (Ctrl+B)"
            >
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="4" width="18" height="16" rx="2.5"/>
                    <line x1="9" y1="4" x2="9" y2="20"/>
                </svg>
            </button>

            {{-- Tombol Tutup Sidebar di Mobile --}}
            <button
                type="button"
                id="sidebar-close-btn"
                class="lg:hidden inline-flex items-center justify-center w-8 h-8 rounded-lg text-[#7A818A] hover:text-[#1A1D1F] hover:bg-black/[0.05] transition-colors cursor-pointer"
                aria-label="Tutup Sidebar"
            >
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M18 6L6 18M6 6l12 12"/>
                </svg>
            </button>
        </div>
    </div>

    {{-- Daftar Menu Navigasi (Scrollable Area) --}}
    <nav class="flex-1 overflow-y-auto sidebar-scroll px-3.5 pb-4 space-y-5" aria-label="Main Menu">
        {{-- Section 1: Workspace --}}
        <div>
            <div class="px-2.5 mb-2 flex items-center justify-between">
                <span class="text-[10.5px] font-semibold uppercase tracking-[0.08em] text-[#8E959F]">
                    Workspace
                </span>
            </div>

            <div class="space-y-0.5">
                @foreach ($workspaceItems as $item)
                    @php
                        $isActive = $item['key'] === $activeKey;
                    @endphp
                    <a
                        href="{{ $item['url'] }}"
                        @if ($isActive) aria-current="page" @endif
                        class="group relative flex items-center gap-3 h-[38px] px-2.5 rounded-xl text-[13.5px] transition-all duration-150 {{ $isActive ? 'bg-white text-[#1A1D1F] font-semibold shadow-active-pill ring-1 ring-black/[0.05]' : 'text-[#5C636E] font-medium hover:text-[#1A1D1F] hover:bg-black/[0.035]' }}"
                    >
                        @if ($isActive)
                            <span class="absolute left-0 top-1/2 -translate-y-1/2 w-[3px] h-4 rounded-r-full bg-[#1A1D1F]" aria-hidden="true"></span>
                        @endif

                        <span class="w-[18px] h-[18px] flex items-center justify-center shrink-0 transition-colors {{ $isActive ? 'text-[#1A1D1F]' : 'text-[#88909B] group-hover:text-[#1A1D1F]' }}">
                            @switch($item['icon'])
                                @case('dashboard')
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="3" y="3" width="7" height="7" rx="1.8"/>
                                        <rect x="14" y="3" width="7" height="7" rx="1.8"/>
                                        <rect x="3" y="14" width="7" height="7" rx="1.8"/>
                                        <rect x="14" y="14" width="7" height="7" rx="1.8"/>
                                    </svg>
                                    @break

                                @case('folders')
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
                                    </svg>
                                    @break

                                @case('builder')
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                                        <polyline points="3.27 6.96 12 12.01 20.73 6.96"/>
                                        <line x1="12" y1="22.08" x2="12" y2="12"/>
                                    </svg>
                                    @break
                            @endswitch
                        </span>

                        <span class="truncate">{{ $item['label'] }}</span>
                    </a>
                @endforeach
            </div>
        </div>

        <div class="mx-2 border-t border-[#EAECEF]"></div>

        {{-- Section 2: Remote Control Plane (Collapsible, Expanded by Default) --}}
        <div class="sidebar-group is-expanded" data-group="remote" data-active-group="{{ $isRemoteActive ? 'true' : 'false' }}">
            <button
                type="button"
                class="sidebar-toggle w-full flex items-center justify-between px-2.5 py-1 mb-1.5 rounded-lg text-[10.5px] font-semibold uppercase tracking-[0.08em] text-[#8E959F] hover:text-[#1A1D1F] transition-colors focus:outline-none cursor-pointer"
                aria-expanded="true"
            >
                <span class="inline-flex items-center gap-2">
                    <span>Remote Manager</span>
                    @auth
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500" title="Admin terautentikasi"></span>
                    @else
                        <svg class="w-3 h-3 text-[#A0A7B0]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" title="Memerlukan login admin">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                        </svg>
                    @endauth
                </span>

                <svg class="chevron-icon w-3.5 h-3.5 text-[#9AA1AB]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M6 9L12 15L18 9"/>
                </svg>
            </button>

            <div class="submenu-panel is-open">
                <div class="submenu-inner">
                    <ul class="space-y-0.5 pt-0.5">
                        @foreach ($remoteItems as $item)
                            @php
                                $isActive = $item['key'] === $activeKey;
                                $badgeColorClass = match($item['badge_color'] ?? '') {
                                    'amber' => 'bg-amber-100/90 text-amber-800 ring-1 ring-amber-200/80',
                                    'mint'  => 'bg-emerald-100 text-emerald-800 ring-1 ring-emerald-200/80',
                                    default => 'bg-gray-200 text-[#1A1D1F]',
                                };
                            @endphp

                            <li>
                                <a
                                    href="{{ $item['url'] }}"
                                    @if ($isActive) aria-current="page" @endif
                                    class="group relative flex items-center justify-between gap-2 h-[38px] px-2.5 rounded-xl text-[13.5px] transition-all duration-150 {{ $isActive ? 'bg-white text-[#1A1D1F] font-semibold shadow-active-pill ring-1 ring-black/[0.05]' : 'text-[#5C636E] font-medium hover:text-[#1A1D1F] hover:bg-black/[0.035]' }}"
                                >
                                    @if ($isActive)
                                        <span class="absolute left-0 top-1/2 -translate-y-1/2 w-[3px] h-4 rounded-r-full bg-[#1A1D1F]" aria-hidden="true"></span>
                                    @endif

                                    <span class="flex items-center gap-3 min-w-0">
                                        <span class="w-[18px] h-[18px] flex items-center justify-center shrink-0 transition-colors {{ $isActive ? 'text-[#1A1D1F]' : 'text-[#88909B] group-hover:text-[#1A1D1F]' }}">
                                            @switch($item['icon'])
                                                @case('apps')
                                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                                        <polygon points="12 2 2 7 12 12 22 7 12 2"/>
                                                        <polyline points="2 17 12 22 22 17"/>
                                                        <polyline points="2 12 12 17 22 12"/>
                                                    </svg>
                                                    @break

                                                @case('devices')
                                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                                        <rect x="2" y="3" width="20" height="14" rx="2.5"/>
                                                        <line x1="8" y1="21" x2="16" y2="21"/>
                                                        <line x1="12" y1="17" x2="12" y2="21"/>
                                                    </svg>
                                                    @break

                                                @case('capture')
                                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                                        <path d="M3 7V5a2 2 0 0 1 2-2h2"/>
                                                        <path d="M17 3h2a2 2 0 0 1 2 2v2"/>
                                                        <path d="M21 17v2a2 2 0 0 1-2 2h-2"/>
                                                        <path d="M7 21H5a2 2 0 0 1-2-2v-2"/>
                                                        <circle cx="12" cy="12" r="3"/>
                                                    </svg>
                                                    @break

                                                @case('configs')
                                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                                        <line x1="4" y1="21" x2="4" y2="14"/>
                                                        <line x1="4" y1="10" x2="4" y2="3"/>
                                                        <line x1="12" y1="21" x2="12" y2="12"/>
                                                        <line x1="12" y1="8" x2="12" y2="3"/>
                                                        <line x1="20" y1="21" x2="20" y2="16"/>
                                                        <line x1="20" y1="12" x2="20" y2="3"/>
                                                        <line x1="1" y1="14" x2="7" y2="14"/>
                                                        <line x1="9" y1="8" x2="15" y2="8"/>
                                                        <line x1="17" y1="16" x2="23" y2="16"/>
                                                    </svg>
                                                    @break

                                                @case('commands')
                                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                                        <polyline points="4 17 10 11 4 5"/>
                                                        <line x1="12" y1="19" x2="20" y2="19"/>
                                                    </svg>
                                                    @break

                                                @case('logs')
                                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                                        <polyline points="14 2 14 8 20 8"/>
                                                        <line x1="16" y1="13" x2="8" y2="13"/>
                                                        <line x1="16" y1="17" x2="8" y2="17"/>
                                                    </svg>
                                                    @break

                                                @case('releases')
                                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                                        <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/>
                                                        <line x1="7" y1="7" x2="7.01" y2="7"/>
                                                    </svg>
                                                    @break
                                            @endswitch
                                        </span>
                                        <span class="truncate">{{ $item['label'] }}</span>
                                    </span>

                                    @if (!empty($item['badge']))
                                        <span class="inline-flex items-center justify-center px-1.5 py-0.5 rounded-md text-[10px] font-bold tracking-wider leading-none shrink-0 {{ $badgeColorClass }}">
                                            {{ $item['badge'] }}
                                        </span>
                                    @endif
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    {{-- Admin Session Footer --}}
    <div class="p-3.5 pt-3 border-t border-[#EAECEF] shrink-0 bg-[#F7F8FA]">
        @auth
            <div class="flex items-center justify-between gap-2.5 p-2 rounded-2xl bg-white border border-[#E6E8EC] shadow-[0_1px_2px_rgba(16,24,40,0.03)]">
                <div class="flex items-center gap-2.5 min-w-0">
                    <div class="relative flex items-center justify-center w-8 h-8 rounded-xl bg-[#1A1D1F] text-white text-[11px] font-bold tracking-wide shrink-0">
                        {{ $userInitials }}
                        <span class="absolute -bottom-0.5 -right-0.5 w-2.5 h-2.5 rounded-full bg-emerald-500 ring-2 ring-white"></span>
                    </div>
                    <div class="min-w-0">
                        <div class="text-[12.5px] font-semibold text-[#1A1D1F] truncate leading-tight">
                            {{ auth()->user()->name }}
                        </div>
                        <div class="text-[11px] text-[#7A818A] truncate leading-tight mt-0.5">
                            {{ auth()->user()->email }}
                        </div>
                    </div>
                </div>

                <form method="POST" action="{{ route('logout') }}" class="shrink-0">
                    @csrf
                    <button
                        type="submit"
                        class="inline-flex items-center justify-center w-8 h-8 rounded-xl text-[#7A818A] hover:text-rose-600 hover:bg-rose-50 transition-colors cursor-pointer"
                        title="Keluar sesi admin"
                        aria-label="Keluar"
                    >
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                            <polyline points="16 17 21 12 16 7"/>
                            <line x1="21" y1="12" x2="9" y2="12"/>
                        </svg>
                    </button>
                </form>
            </div>
        @else
            <a
                href="{{ $resolveUrl('login', '/login') }}"
                class="flex items-center justify-center gap-2 w-full h-10 px-3 rounded-xl bg-[#1A1D1F] hover:bg-black text-white text-[12.5px] font-semibold shadow-sm transition-colors"
            >
                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                </svg>
                <span>Masuk Admin Control Plane</span>
            </a>
        @endauth
    </div>
</aside>
