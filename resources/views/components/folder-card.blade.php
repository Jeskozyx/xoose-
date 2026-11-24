{{--
    ==========================================================================
    Komponen Kartu Folder User Supabase
    Path: resources/views/components/folder-card.blade.php
    Pemakaian di Blade: <x-folder-card :folder="$folder" />
    ==========================================================================
--}}

@props([
    'folder',
    'theme' => null,
])

@php
    $accentStyles = [
        'lavender' => 'bg-indigo-50 text-indigo-700 border-indigo-100',
        'sky'      => 'bg-sky-50 text-sky-700 border-sky-100',
        'peach'    => 'bg-amber-50 text-amber-700 border-amber-100',
    ];

    $title = !empty($folder['title']) ? $folder['title'] : 'Folder';
    $userKey = strtolower(trim($title));
    $selectedTheme = $theme ?? ($folder['theme'] ?? 'lavender');
    $accentClass = $accentStyles[$selectedTheme] ?? 'bg-[#F4F5F7] text-[#344054] border-[#E6E8EC]';
    $itemsCount = (int) ($folder['items'] ?? 0);
    $extra = (int) ($folder['extra'] ?? 0);
    $folderDate = $folder['date'] ?? null;
    $folderStatus = $folder['status'] ?? null;
    $folderUrl = route('folders.index', ['folder' => $title]);
@endphp

<a
    href="{{ $folderUrl }}"
    class="folder-card group flex flex-col justify-between rounded-xl border border-[#E6E8EC] bg-white p-4 hover:border-[#C2C9D2] hover:bg-[#FCFCFD] transition-all duration-150 cursor-pointer select-none text-inherit no-underline"
    data-searchable="folder"
    data-title="{{ $userKey }}"
    data-user="{{ $userKey }}"
    data-items="{{ $itemsCount }}"
    data-url="{{ $folderUrl }}"
>
    {{-- Header: Ikon Folder Minimalis + Nama User + Panah --}}
    <div class="flex items-start justify-between gap-3">
        <div class="flex items-center gap-3 min-w-0">
            <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg border shrink-0 {{ $accentClass }}">
                <svg class="w-[17px] h-[17px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
                </svg>
            </span>

            <div class="min-w-0">
                <h3 class="text-[14px] font-semibold text-[#111315] tracking-[-0.01em] truncate group-hover:text-black">
                    {{ $title }}
                </h3>
                <p class="text-[11.5px] text-[#7A818A] truncate mt-0.5">
                    @if ($folderDate)
                        {{ $folderDate }}
                    @elseif ($folderStatus)
                        {{ ucfirst((string) $folderStatus) }}
                    @else
                        user_responses
                    @endif
                </p>
            </div>
        </div>

        <svg class="w-4 h-4 text-[#B0B7C3] group-hover:text-[#111315] group-hover:translate-x-0.5 transition-all shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M9 18l6-6-6-6"/>
        </svg>
    </div>

    {{-- Footer: Counter Screenshot Tabular --}}
    <div class="mt-4 pt-3 border-t border-[#F2F3F5] flex items-center justify-between gap-2">
        <div class="flex items-center gap-1.5 min-w-0">
            <span class="w-1.5 h-1.5 rounded-full {{ $itemsCount > 0 ? 'bg-emerald-500' : 'bg-[#D0D5DD]' }}" aria-hidden="true"></span>
            <span class="text-[11.5px] text-[#667085] truncate">
                {{ $itemsCount > 0 ? 'Tersinkron' : 'Belum ada capture' }}
            </span>
            @if ($extra > 0)
                <span class="inline-flex items-center justify-center px-1.5 py-0.5 rounded bg-[#F2F4F7] text-[10.5px] font-semibold text-[#475467]">
                    +{{ $extra }}
                </span>
            @endif
        </div>

        <span class="folder-items-count font-mono text-[12px] font-medium text-[#344054] tabular-nums shrink-0 transition-colors" data-user="{{ $userKey }}">
            {{ $itemsCount }} items
        </span>
    </div>
</a>
