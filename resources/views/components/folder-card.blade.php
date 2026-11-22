{{--
    ==========================================================================
    Komponen Satuan Kartu Folder Pastel (Single Folder Card)
    Path: resources/views/components/folder-card.blade.php
    Pemakaian di Blade: <x-folder-card :folder="$folder" />
    ==========================================================================
--}}

@props([
    'folder',
    'theme' => null,
])

@php
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

    $title = !empty($folder['title']) ? $folder['title'] : 'Folder';
    $userKey = strtolower(trim($title));
    $selectedTheme = $theme ?? ($folder['theme'] ?? 'lavender');
    $t = $folderThemes[$selectedTheme] ?? $folderThemes['lavender'];
    $itemsCount = $folder['items'] ?? 0;
    $avatars = !empty($folder['avatars']) && is_array($folder['avatars']) ? $folder['avatars'] : ['alex'];
    $extra = $folder['extra'] ?? 0;
    $folderUrl = route('folders.index', ['folder' => $title]);
@endphp

<a
    href="{{ $folderUrl }}"
    class="folder-card block relative pt-[16px] cursor-pointer select-none text-inherit no-underline"
    data-searchable="folder"
    data-title="{{ $userKey }}"
    data-user="{{ $userKey }}"
    data-url="{{ $folderUrl }}"
>
    {{-- Lapisan Belakang Folder --}}
    <div class="absolute top-0 left-[6px] w-[39%] h-[28px] rounded-t-[13px]" style="background-color: {{ $t['back'] }};"></div>
    <svg class="absolute top-0 left-[calc(39%+2px)] w-[28px] h-[22px]" viewBox="0 0 28 22" fill="none" aria-hidden="true">
        <path d="M0 0C7 0 11 10 20 14H28V22H0V0Z" fill="{{ $t['back'] }}"/>
    </svg>
    <div class="absolute top-[13px] left-[2px] right-[2px] h-[26px] rounded-t-[14px]" style="background-color: {{ $t['back'] }};"></div>

    {{-- Lapisan Depan Folder Pastel --}}
    <div
        class="relative rounded-[18px] rounded-tl-[14px] px-5 pt-4 pb-4 min-h-[126px] flex flex-col justify-between shadow-[0_6px_18px_-8px_rgba(16,24,40,0.09)] border"
        style="background: linear-gradient(180deg, {{ $t['frontTop'] }} 0%, {{ $t['frontBot'] }} 100%); border-color: {{ $t['border'] }};"
    >
        {{-- Tab Depan Folder --}}
        <div
            class="absolute -top-[10px] -left-[1px] w-[36%] h-[12px] rounded-tl-[13px] rounded-tr-[10px] border-t border-l"
            style="background-color: {{ $t['frontTop'] }}; border-color: {{ $t['border'] }};"
        ></div>
        <svg class="absolute -top-[10px] left-[calc(36%-4px)] w-[22px] h-[12px]" viewBox="0 0 22 12" fill="none" aria-hidden="true">
            <path d="M0 0.5C6 0.5 10 10.5 18 10.5H22V12H0V0.5Z" fill="{{ $t['frontTop'] }}"/>
            <path d="M0 0.5C6 0.5 10 10.5 18 10.5H22" stroke="{{ $t['border'] }}" stroke-width="1"/>
        </svg>

        {{-- Tombol ⋮ Pojok Kanan --}}
        <div class="flex justify-end">
            <button
                type="button"
                class="card-action-btn inline-flex items-center justify-center w-[30px] h-[22px] rounded-full bg-white/45 hover:bg-white/85 border border-black/[0.06] text-[#525866] transition-colors"
                data-action-label="Opsi folder {{ $title }}"
                aria-label="Opsi folder {{ $title }}"
                onclick="event.preventDefault(); event.stopPropagation();"
            >
                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor">
                    <circle cx="12" cy="6" r="1.7"/>
                    <circle cx="12" cy="12" r="1.7"/>
                    <circle cx="12" cy="18" r="1.7"/>
                </svg>
            </button>
        </div>

        {{-- Judul Folder --}}
        <h3 class="font-mono text-[14.5px] font-medium text-[#1A1D1F] tracking-[-0.02em] mt-1 mb-4 truncate">
            {{ $title }}
        </h3>

        {{-- Avatar Stack & Jumlah Item --}}
        <div class="flex items-center justify-between gap-2">
            <div class="flex items-center -space-x-2">
                @foreach ($avatars as $avKey)
                    @php $av = $avatarProfiles[$avKey] ?? $avatarProfiles['alex']; @endphp
                    <span class="inline-flex w-[26px] h-[26px] rounded-full ring-2 ring-white/90 overflow-hidden">
                        <svg viewBox="0 0 36 36" class="w-full h-full">
                            <rect width="36" height="36" fill="{{ $av['bg'] }}"/>
                            <circle cx="18" cy="14" r="6.5" fill="{{ $av['skin'] }}"/>
                            <path d="M11.5 13.5C11.5 9.2 14.4 6.5 18 6.5C21.6 6.5 24.5 9.2 24.5 13.5C23 11.2 20.8 10.3 18 10.3C15.2 10.3 13 11.2 11.5 13.5Z" fill="{{ $av['hair'] }}"/>
                            @if (!empty($av['beard']))
                                <path d="M13 16.5C13.8 19.6 15.8 20.8 18 20.8C20.2 20.8 22.2 19.6 23 16.5C21.5 18 19.8 18.4 18 18.4C16.2 18.4 14.5 18 13 16.5Z" fill="{{ $av['hair'] }}" opacity="0.65"/>
                            @endif
                            <path d="M6 36C6.8 28.5 11.6 24.5 18 24.5C24.4 24.5 29.2 28.5 30 36H6Z" fill="{{ $av['shirt'] }}"/>
                        </svg>
                    </span>
                @endforeach

                @if ($extra > 0)
                    <span
                        class="inline-flex items-center justify-center w-[26px] h-[26px] rounded-full ring-2 ring-white/90 text-[11px] font-semibold text-[#5A534C]"
                        style="background-color: {{ $t['extraBg'] }};"
                    >
                        +{{ $extra }}
                    </span>
                @endif
            </div>

            <span class="folder-items-count text-[12.5px] text-[#7A7E87] font-normal transition-colors" data-user="{{ $userKey }}">
                {{ $itemsCount }} items
            </span>
        </div>
    </div>
</a>
