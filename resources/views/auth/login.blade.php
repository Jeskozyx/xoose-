<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Admin Login — Xoose Remote Service Manager</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-[#FAFAFB] text-[#1A1D1F] font-sans antialiased flex items-center justify-center p-6">
    <div class="w-full max-w-md bg-white border border-gray-200 rounded-2xl shadow-sm p-8">
        <div class="flex items-center gap-3 mb-6">
            <div class="w-11 h-11 rounded-xl bg-[#1A1D1F] text-white flex items-center justify-center font-bold text-lg shadow-sm">
                X
            </div>
            <div>
                <h1 class="text-xl font-bold tracking-tight text-gray-900">Xoose Control Plane</h1>
                <p class="text-xs text-gray-500">Login Admin Pusat (Hanya di Dashboard Web Xoose)</p>
            </div>
        </div>

        @if (session('success'))
            <div class="mb-4 p-3.5 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-4 p-3.5 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm">
                <ul class="list-disc list-inside space-y-0.5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('login.attempt') }}" class="space-y-4">
            @csrf

            <div>
                <label for="email" class="block text-xs font-semibold text-gray-700 mb-1.5">Email Admin</label>
                <input
                    id="email"
                    name="email"
                    type="email"
                    value="{{ old('email') }}"
                    required
                    autofocus
                    autocomplete="email"
                    placeholder="admin@xoose.local"
                    class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-gray-900/10 focus:border-gray-900"
                >
            </div>

            <div>
                <label for="password" class="block text-xs font-semibold text-gray-700 mb-1.5">Kata Sandi</label>
                <input
                    id="password"
                    name="password"
                    type="password"
                    required
                    autocomplete="current-password"
                    placeholder="••••••••"
                    class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-gray-900/10 focus:border-gray-900"
                >
            </div>

            <div class="flex items-center justify-between text-xs text-gray-600 pt-1">
                <label class="inline-flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="remember" value="1" class="rounded border-gray-300 text-gray-900 focus:ring-gray-900">
                    <span>Ingat sesi saya</span>
                </label>
                <a href="{{ route('dashboard') }}" class="text-gray-500 hover:text-gray-900 font-medium">← Kembali ke Dashboard</a>
            </div>

            <button
                type="submit"
                class="w-full py-2.5 px-4 rounded-xl bg-[#1A1D1F] hover:bg-black text-white text-sm font-semibold transition-colors shadow-sm cursor-pointer"
            >
                Masuk ke Remote Service Manager
            </button>
        </form>

        <div class="mt-6 pt-5 border-t border-gray-100 text-[11px] text-gray-400 leading-relaxed">
            Akses kendali perangkat LoveYou hanya tersedia bagi admin terautentikasi di Xoose. Aplikasi Windows LoveYou tidak memiliki login admin dan menggunakan token unik per perangkat yang dapat dicabut sewaktu-waktu.
        </div>
    </div>
</body>
</html>
