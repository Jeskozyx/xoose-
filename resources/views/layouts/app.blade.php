<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="supabase-url" content="https://wqjfunxfngowxwdmyzyj.supabase.co">
    <meta name="supabase-key" content="{{ config('services.supabase.key', 'sb_publishable_u2lBe4CRF_2XEqbkniBjbw_p0gGEXgZ') }}">

    <title>@yield('title', config('app.name', 'Xoose') . ' — Dashboard')</title>

    @fonts

    <!-- Scripts & Styles via Vite -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @stack('styles')
</head>
<body class="h-full bg-[#FAFAFB] text-[#1A1D1F] font-sans antialiased">
    <div class="flex h-screen overflow-hidden bg-[#FAFAFB]">
        {{-- Sidebar Component --}}
        @php
            $currentActiveMenu = $activeMenu ?? null;
            if (!$currentActiveMenu) {
                if (request()->routeIs('remote.apps.*') || request()->is('remote/apps*')) {
                    $currentActiveMenu = 'remote.apps';
                } elseif (request()->routeIs('remote.devices.*') || request()->routeIs('remote.services.*') || request()->is('remote/devices*')) {
                    $currentActiveMenu = 'remote.devices';
                } elseif (request()->routeIs('remote.configs.*') || request()->is('remote/configs*')) {
                    $currentActiveMenu = 'remote.configs';
                } elseif (request()->routeIs('remote.commands.*') || request()->is('remote/commands*')) {
                    $currentActiveMenu = 'remote.commands';
                } elseif (request()->routeIs('remote.logs.*') || request()->is('remote/logs*')) {
                    $currentActiveMenu = 'remote.logs';
                } elseif (request()->routeIs('remote.releases.*') || request()->is('remote/releases*')) {
                    $currentActiveMenu = 'remote.releases';
                } elseif (request()->routeIs('builder.*') || request()->is('builder*')) {
                    $currentActiveMenu = 'builder';
                } elseif (request()->routeIs('folders.*') || request()->is('folders*')) {
                    $currentActiveMenu = 'folders';
                } elseif (request()->routeIs('dashboard') || request()->is('/') || request()->is('dashboard')) {
                    $currentActiveMenu = 'dashboard';
                } elseif (request()->has('menu')) {
                    $currentActiveMenu = request()->query('menu');
                } else {
                    $currentActiveMenu = 'dashboard';
                }
            }
        @endphp
        <x-sidebar :active-key="$currentActiveMenu" />

        {{-- Main Content --}}
        <main class="flex-1 flex flex-col min-w-0 h-full overflow-hidden bg-white">
            @yield('content')
        </main>
    </div>

    @stack('scripts')
</body>
</html>
