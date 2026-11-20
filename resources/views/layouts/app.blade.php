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
        <x-sidebar :active-key="$activeMenu ?? 'dashboard'" />

        {{-- Main Content --}}
        <main class="flex-1 flex flex-col min-w-0 h-full overflow-hidden bg-white">
            @yield('content')
        </main>
    </div>

    @stack('scripts')
</body>
</html>
