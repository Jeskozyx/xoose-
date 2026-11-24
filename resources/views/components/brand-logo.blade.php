{{--
    ==========================================================================
    Komponen Brand Logo Xoose (Kinetic Node)
    Path: resources/views/components/brand-logo.blade.php
    ==========================================================================
--}}

@props([
    'dotColor' => '#10B981',
])

<svg
    {{ $attributes->merge(['viewBox' => '0 0 32 32', 'fill' => 'none', 'xmlns' => 'http://www.w3.org/2000/svg', 'aria-hidden' => 'true']) }}
>
    <path
        d="M6.5 6.5L14.5 16L6.5 25.5"
        stroke="currentColor"
        stroke-width="3.6"
        stroke-linecap="round"
        stroke-linejoin="round"
    />
    <path
        d="M25.5 25.5L17.5 16L21.5 11.2"
        stroke="currentColor"
        stroke-width="3.6"
        stroke-linecap="round"
        stroke-linejoin="round"
    />
    <circle cx="25" cy="7" r="2.7" fill="{{ $dotColor }}" />
</svg>
