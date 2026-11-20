{{--
    ==========================================================================
    Komponen Sidebar (Terpisah)
    Path: resources/views/components/sidebar.blade.php
    Pemakaian di Blade: <x-sidebar active-key="dashboard" />
    ==========================================================================
--}}

@props([
    'activeKey' => request()->query('menu', 'dashboard'),
])

@php
    /**
     * Helper aman untuk mengambil URL route.
     * Jika named route belum dibuat di routes/web.php, otomatis fallback ke URL default.
     */
    $resolveUrl = function (string $routeName, string $fallbackUrl): string {
        return \Illuminate\Support\Facades\Route::has($routeName)
            ? route($routeName)
            : url($fallbackUrl);
    };

    /**
     * Struktur menu sidebar sesuai desain referensi.
     */
    $menuItems = [
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
            'key'      => 'product',
            'label'    => 'Product',
            'icon'     => 'product',
            'children' => [
                [
                    'key'   => 'product.overview',
                    'label' => 'Overview',
                    'url'   => $resolveUrl('product.overview', '/?menu=product.overview'),
                ],
                [
                    'key'         => 'product.drafts',
                    'label'       => 'Drafts',
                    'url'         => $resolveUrl('product.drafts', '/?menu=product.drafts'),
                    'badge'       => 3,
                    'badge_color' => 'peach', // #FFBC99
                ],
                [
                    'key'   => 'product.released',
                    'label' => 'Released',
                    'url'   => $resolveUrl('product.released', '/?menu=product.released'),
                ],
                [
                    'key'   => 'product.comments',
                    'label' => 'Comments',
                    'url'   => $resolveUrl('product.comments', '/?menu=product.comments'),
                ],
                [
                    'key'         => 'product.scheduled',
                    'label'       => 'Scheduled',
                    'url'         => $resolveUrl('product.scheduled', '/?menu=product.scheduled'),
                    'badge'       => 8,
                    'badge_color' => 'mint', // #B5E4CA
                ],
            ],
        ],
        [
            'key'      => 'customers',
            'label'    => 'Customers',
            'icon'     => 'customers',
            'children' => [
                [
                    'key'   => 'customers.overview',
                    'label' => 'Overview',
                    'url'   => $resolveUrl('customers.overview', '/?menu=customers.overview'),
                ],
                [
                    'key'   => 'customers.list',
                    'label' => 'Customer List',
                    'url'   => $resolveUrl('customers.list', '/?menu=customers.list'),
                ],
            ],
        ],
        [
            'key'   => 'shop',
            'label' => 'Shop',
            'icon'  => 'shop',
            'url'   => $resolveUrl('shop', '/?menu=shop'),
        ],
        [
            'key'      => 'income',
            'label'    => 'Income',
            'icon'     => 'income',
            'children' => [
                [
                    'key'   => 'income.earning',
                    'label' => 'Earning',
                    'url'   => $resolveUrl('income.earning', '/?menu=income.earning'),
                ],
                [
                    'key'   => 'income.payouts',
                    'label' => 'Payouts',
                    'url'   => $resolveUrl('income.payouts', '/?menu=income.payouts'),
                ],
                [
                    'key'   => 'income.statements',
                    'label' => 'Statements',
                    'url'   => $resolveUrl('income.statements', '/?menu=income.statements'),
                ],
            ],
        ],
        [
            'key'   => 'promote',
            'label' => 'Promote',
            'icon'  => 'promote',
            'url'   => $resolveUrl('promote', '/?menu=promote'),
        ],
    ];
@endphp

{{-- Overlay gelap khusus layar mobile saat sidebar dibuka --}}
<div
    id="sidebar-overlay"
    class="fixed inset-0 bg-black/25 backdrop-blur-[1px] z-30 hidden lg:hidden transition-opacity"
    aria-hidden="true"
></div>

<aside
    id="app-sidebar"
    class="fixed lg:sticky top-0 left-0 z-40 h-screen w-[268px] shrink-0 bg-[#F4F4F4] flex flex-col px-5 py-6 overflow-y-auto -translate-x-full lg:translate-x-0 transition-transform duration-300 ease-out"
    aria-label="Sidebar Navigation"
>
    {{-- Header Logo Hexagon Metalik --}}
    <div class="flex items-center justify-between px-2 mb-8">
        <a href="{{ $resolveUrl('dashboard', '/') }}" class="inline-flex items-center gap-3 group focus:outline-none">
            <svg width="42" height="42" viewBox="0 0 44 44" fill="none" xmlns="http://www.w3.org/2000/svg" class="transition-transform duration-200 group-hover:scale-[1.03]">
                <defs>
                    <linearGradient id="hexGradTL" x1="6" y1="6" x2="22" y2="22" gradientUnits="userSpaceOnUse">
                        <stop offset="0%" stop-color="#D4D7DC"/>
                        <stop offset="55%" stop-color="#8A9099"/>
                        <stop offset="100%" stop-color="#4A4F57"/>
                    </linearGradient>
                    <linearGradient id="hexGradTR" x1="22" y1="6" x2="38" y2="22" gradientUnits="userSpaceOnUse">
                        <stop offset="0%" stop-color="#F2F4F7"/>
                        <stop offset="60%" stop-color="#B8BEC7"/>
                        <stop offset="100%" stop-color="#767C85"/>
                    </linearGradient>
                    <linearGradient id="hexGradBL" x1="6" y1="22" x2="22" y2="38" gradientUnits="userSpaceOnUse">
                        <stop offset="0%" stop-color="#9CA2AB"/>
                        <stop offset="55%" stop-color="#5A6068"/>
                        <stop offset="100%" stop-color="#2E3238"/>
                    </linearGradient>
                    <linearGradient id="hexGradBR" x1="22" y1="22" x2="38" y2="38" gradientUnits="userSpaceOnUse">
                        <stop offset="0%" stop-color="#B0B6BF"/>
                        <stop offset="55%" stop-color="#6B717A"/>
                        <stop offset="100%" stop-color="#3B4047"/>
                    </linearGradient>
                    <clipPath id="roundedHexClip">
                        <path d="M19.5 3.4C21.05 2.51 22.95 2.51 24.5 3.4L36.5 10.33C38.05 11.22 39 12.87 39 14.66V28.52C39 30.31 38.05 31.96 36.5 32.85L24.5 39.78C22.95 40.67 21.05 40.67 19.5 39.78L7.5 32.85C5.95 31.96 5 30.31 5 28.52V14.66C5 12.87 5.95 11.22 7.5 10.33L19.5 3.4Z"/>
                    </clipPath>
                </defs>

                {{-- 4 Kuadran Metalik --}}
                <g clip-path="url(#roundedHexClip)">
                    <rect x="4" y="2" width="18" height="19.6" fill="url(#hexGradTL)"/>
                    <rect x="22" y="2" width="18" height="19.6" fill="url(#hexGradTR)"/>
                    <rect x="4" y="21.6" width="18" height="20" fill="url(#hexGradBL)"/>
                    <rect x="22" y="21.6" width="18" height="20" fill="url(#hexGradBR)"/>
                    <line x1="22" y1="2" x2="22" y2="41" stroke="#1E2125" stroke-width="1.6"/>
                    <line x1="4" y1="21.6" x2="40" y2="21.6" stroke="#1E2125" stroke-width="1.6"/>
                    <path d="M19.5 4.4C21.05 3.51 22.95 3.51 24.5 4.4L35.7 10.87C37.1 11.68 38 13.17 38 14.79V28.39C38 30.01 37.1 31.5 35.7 32.31L24.5 38.78C22.95 39.67 21.05 39.67 19.5 38.78L8.3 32.31C6.9 31.5 6 30.01 6 28.39V14.79C6 13.17 6.9 11.68 8.3 10.87L19.5 4.4Z" stroke="rgba(255,255,255,0.28)" stroke-width="1"/>
                </g>

                <path d="M19.5 3.4C21.05 2.51 22.95 2.51 24.5 3.4L36.5 10.33C38.05 11.22 39 12.87 39 14.66V28.52C39 30.31 38.05 31.96 36.5 32.85L24.5 39.78C22.95 40.67 21.05 40.67 19.5 39.78L7.5 32.85C5.95 31.96 5 30.31 5 28.52V14.66C5 12.87 5.95 11.22 7.5 10.33L19.5 3.4Z" stroke="#1B1E22" stroke-width="1.8"/>
            </svg>
        </a>

        <div class="flex items-center gap-1">
            {{-- Tombol Collapse Sidebar di Desktop --}}
            <button
                type="button"
                data-sidebar-toggle
                class="hidden lg:inline-flex items-center justify-center w-8 h-8 rounded-lg text-[#6F767E] hover:text-[#1A1D1F] hover:bg-black/5 transition-colors cursor-pointer"
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
                class="lg:hidden p-2 rounded-lg text-[#6F767E] hover:text-[#1A1D1F] hover:bg-black/5 transition-colors"
                aria-label="Tutup Sidebar"
            >
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M18 6L6 18M6 6l12 12"/>
                </svg>
            </button>
        </div>
    </div>

    {{-- Daftar Menu Navigasi --}}
    <nav class="flex-1 space-y-1.5" aria-label="Main Menu">
        @foreach ($menuItems as $item)
            @php
                $hasChildren = !empty($item['children']);
                $isParentActive = $hasChildren
                    ? collect($item['children'])->contains(fn ($child) => $child['key'] === $activeKey)
                    : ($item['key'] === $activeKey);
            @endphp

            @if ($hasChildren)
                {{-- Menu dengan Submenu Accordion --}}
                <div class="sidebar-group {{ $isParentActive ? 'is-expanded' : '' }}" data-group="{{ $item['key'] }}">
                    <button
                        type="button"
                        class="sidebar-toggle w-full flex items-center justify-between px-2.5 py-2.5 rounded-xl text-[15px] transition-colors duration-150 focus:outline-none {{ $isParentActive ? 'text-[#1A1D1F] font-semibold' : 'text-[#6F767E] font-medium hover:text-[#1A1D1F]' }}"
                        aria-expanded="{{ $isParentActive ? 'true' : 'false' }}"
                    >
                        <span class="flex items-center gap-3.5">
                            <span class="w-5 h-5 flex items-center justify-center shrink-0">
                                @switch($item['icon'])
                                    @case('product')
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                            <rect x="4" y="7.5" width="16" height="11.5" rx="3.5"/>
                                            <path d="M9.5 4.5H14.5"/>
                                        </svg>
                                        @break

                                    @case('customers')
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                            <circle cx="12" cy="8.25" r="3.25"/>
                                            <path d="M5.5 18.75C5.5 16.2 8.4 14.5 12 14.5C15.6 14.5 18.5 16.2 18.5 18.75C18.5 19.44 17.94 20 17.25 20H6.75C6.06 20 5.5 19.44 5.5 18.75Z"/>
                                        </svg>
                                        @break

                                    @case('income')
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                            <rect x="4" y="5.5" width="16" height="13" rx="3.2"/>
                                            <path d="M7.5 14.2L10.5 11.2L13 13.4L16.5 9.8"/>
                                        </svg>
                                        @break
                                @endswitch
                            </span>
                            <span>{{ $item['label'] }}</span>
                        </span>

                        <svg class="chevron-icon w-4 h-4 shrink-0 {{ $isParentActive ? 'text-[#1A1D1F]' : 'text-[#6F767E]' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M6 9L12 15L18 9"/>
                        </svg>
                    </button>

                    {{-- Panel Submenu dengan Curved Tree Lines --}}
                    <div class="submenu-panel {{ $isParentActive ? 'is-open' : '' }}">
                        <div class="submenu-inner">
                            <ul class="submenu-tree pb-2">
                                @foreach ($item['children'] as $child)
                                    @php
                                        $isChildActive = $child['key'] === $activeKey;
                                        $badgeColorClass = match($child['badge_color'] ?? '') {
                                            'peach' => 'bg-[#FFBC99] text-[#1A1D1F]',
                                            'mint'  => 'bg-[#B5E4CA] text-[#1A1D1F]',
                                            default => 'bg-gray-200 text-[#1A1D1F]',
                                        };
                                    @endphp

                                    <li class="submenu-item">
                                        <a
                                            href="{{ $child['url'] }}"
                                            class="flex items-center justify-between h-[46px] px-4 rounded-xl text-[15px] transition-all duration-150 {{ $isChildActive ? 'bg-white text-[#1A1D1F] font-semibold shadow-active-pill ring-1 ring-black/[0.03]' : 'text-[#6F767E] font-medium hover:text-[#1A1D1F] hover:bg-black/[0.03]' }}"
                                        >
                                            <span>{{ $child['label'] }}</span>

                                            @if (isset($child['badge']))
                                                <span class="inline-flex items-center justify-center min-w-[24px] h-[24px] px-1.5 rounded-[7px] text-[13px] font-semibold leading-none {{ $badgeColorClass }}">
                                                    {{ $child['badge'] }}
                                                </span>
                                            @endif
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            @else
                {{-- Menu Tunggal Tanpa Submenu --}}
                <a
                    href="{{ $item['url'] }}"
                    class="flex items-center gap-3.5 px-2.5 py-2.5 rounded-xl text-[15px] transition-all duration-150 {{ $isParentActive ? 'bg-white text-[#1A1D1F] font-semibold shadow-active-pill ring-1 ring-black/[0.03]' : 'text-[#6F767E] font-medium hover:text-[#1A1D1F] hover:bg-black/[0.03]' }}"
                >
                    <span class="w-5 h-5 flex items-center justify-center shrink-0">
                        @switch($item['icon'])
                            @case('dashboard')
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                                    <circle cx="8" cy="8" r="2.75"/>
                                    <circle cx="16" cy="8" r="2.75"/>
                                    <circle cx="8" cy="16" r="2.75"/>
                                    <circle cx="16" cy="16" r="2.75"/>
                                </svg>
                                @break

                            @case('folders')
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
                                </svg>
                                @break

                            @case('shop')
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M5.5 8.5L15.8 5.8C16.9 5.5 18 6.3 18 7.45V9"/>
                                    <rect x="4" y="8.5" width="16" height="10.5" rx="3"/>
                                </svg>
                                @break

                            @case('promote')
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M12 3.5C13.1 3.5 13.8 4.4 14.8 4.8C15.8 5.2 17 4.9 17.8 5.7C18.6 6.5 18.3 7.7 18.7 8.7C19.1 9.7 20 10.4 20 11.5C20 12.6 19.1 13.3 18.7 14.3C18.3 15.3 18.6 16.5 17.8 17.3C17 18.1 15.8 17.8 14.8 18.2C13.8 18.6 13.1 19.5 12 19.5C10.9 19.5 10.2 18.6 9.2 18.2C8.2 17.8 7 18.1 6.2 17.3C5.4 16.5 5.7 15.3 5.3 14.3C4.9 13.3 4 12.6 4 11.5C4 10.4 4.9 9.7 5.3 8.7C5.7 7.7 5.4 6.5 6.2 5.7C7 4.9 8.2 5.2 9.2 4.8C10.2 4.4 10.9 3.5 12 3.5Z"/>
                                </svg>
                                @break
                        @endswitch
                    </span>
                    <span>{{ $item['label'] }}</span>
                </a>
            @endif
        @endforeach
    </nav>
</aside>