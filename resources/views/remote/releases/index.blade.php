@extends('layouts.app')

@section('title', 'Releases & Updates — Xoose Remote Service Manager')

@php
    $activeMenu = 'remote.releases';
@endphp

@section('content')
<div class="h-full flex flex-col overflow-y-auto px-8 py-8">
    {{-- Header --}}
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-6 border-b border-gray-100">
        <div>
            <div class="flex items-center gap-2 text-sm text-gray-500 mb-1">
                <a href="{{ route('remote.apps.index') }}" class="hover:text-gray-900">Remote Manager</a>
                <span>/</span>
                <span class="text-gray-800 font-semibold">Releases & Updates</span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900">Releases & Signed Updates</h1>
            <p class="text-sm text-gray-500 mt-1">Kelola paket versi aplikasi baru lengkap dengan checksum SHA-256 dan tanda tangan digital HMAC-SHA256.</p>
        </div>
    </div>

    @if (session('success'))
        <div class="mt-6 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="mt-6 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm">
            <ul class="list-disc list-inside space-y-0.5">
                @foreach ($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Form Terbitkan Release Baru --}}
    <div class="mt-6 p-6 rounded-2xl border border-gray-200 bg-[#FAFAFB]">
        <h2 class="text-sm font-bold text-gray-900 mb-3">Terbitkan Paket Release Baru (Auto-Sign HMAC-SHA256)</h2>
        <form method="POST" action="{{ route('remote.releases.store') }}" class="grid grid-cols-1 md:grid-cols-12 gap-3">
            @csrf
            <div class="md:col-span-3">
                <label class="block text-xs font-semibold text-gray-600 mb-1">Aplikasi Target *</label>
                <select name="managed_app_id" required class="w-full px-3 py-2 rounded-xl border border-gray-200 bg-white text-sm">
                    @foreach ($apps as $app)
                        <option value="{{ $app->id }}" @selected((string)$selectedAppId === (string)$app->id)>{{ $app->name }} ({{ $app->slug }})</option>
                    @endforeach
                </select>
            </div>
            <div class="md:col-span-2">
                <label class="block text-xs font-semibold text-gray-600 mb-1">Versi Semantik *</label>
                <input type="text" name="version" value="{{ old('version') }}" required placeholder="1.1.0" class="w-full px-3 py-2 rounded-xl border border-gray-200 bg-white text-sm font-mono">
            </div>
            <div class="md:col-span-4">
                <label class="block text-xs font-semibold text-gray-600 mb-1">Path Artefak Build *</label>
                <input type="text" name="artifact_path" value="{{ old('artifact_path') }}" required placeholder="builds/loveyou/1.1.0/LoveYou.exe" class="w-full px-3 py-2 rounded-xl border border-gray-200 bg-white text-sm font-mono">
            </div>
            <div class="md:col-span-3">
                <label class="block text-xs font-semibold text-gray-600 mb-1">URL Unduhan (opsional)</label>
                <input type="text" name="artifact_url" value="{{ old('artifact_url') }}" placeholder="https://..." class="w-full px-3 py-2 rounded-xl border border-gray-200 bg-white text-sm">
            </div>
            <div class="md:col-span-6">
                <label class="block text-xs font-semibold text-gray-600 mb-1">SHA-256 Checksum (64 hex) *</label>
                <input type="text" name="sha256_checksum" value="{{ old('sha256_checksum') }}" required minlength="64" maxlength="64" placeholder="64 karakter hex SHA-256 paket" class="w-full px-3 py-2 rounded-xl border border-gray-200 bg-white text-xs font-mono">
            </div>
            <div class="md:col-span-4">
                <label class="block text-xs font-semibold text-gray-600 mb-1">Release Notes</label>
                <input type="text" name="release_notes" value="{{ old('release_notes') }}" placeholder="Ringkasan perubahan pada versi ini" class="w-full px-3 py-2 rounded-xl border border-gray-200 bg-white text-sm">
            </div>
            <div class="md:col-span-2 flex items-end">
                <input type="hidden" name="is_active" value="1">
                <button type="submit" class="w-full px-4 py-2 rounded-xl bg-[#1A1D1F] hover:bg-black text-white text-sm font-semibold cursor-pointer">
                    + Publish
                </button>
            </div>
        </form>
    </div>

    {{-- Tabel Daftar Releases --}}
    <div class="mt-6 rounded-2xl border border-gray-200 bg-white overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="border-b border-gray-100 text-[11px] uppercase tracking-wider text-gray-400 bg-gray-50/70">
                    <th class="px-5 py-3.5 font-semibold">Aplikasi & Versi</th>
                    <th class="px-5 py-3.5 font-semibold">Status</th>
                    <th class="px-5 py-3.5 font-semibold">Integritas (SHA-256 & Signature)</th>
                    <th class="px-5 py-3.5 font-semibold">Artefak & Catatan</th>
                    <th class="px-5 py-3.5 font-semibold text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 text-xs">
                @forelse ($releases as $rel)
                    <tr class="hover:bg-gray-50/60">
                        <td class="px-5 py-4">
                            <div class="font-bold text-gray-900">{{ $rel->managedApp?->name ?? '-' }}</div>
                            <div class="font-mono text-sm font-semibold text-sky-700">v{{ $rel->version }}</div>
                        </td>
                        <td class="px-5 py-4">
                            <x-remote-status-badge :status="$rel->is_active ? 'Active' : 'Inactive'" />
                        </td>
                        <td class="px-5 py-4 font-mono text-[11px] text-gray-600">
                            <div title="{{ $rel->sha256_checksum }}">SHA256: {{ substr($rel->sha256_checksum, 0, 20) }}...</div>
                            @if ($rel->signature)
                                <div class="text-emerald-700" title="{{ $rel->signature }}">HMAC: {{ substr($rel->signature, 0, 20) }}... ✓</div>
                            @endif
                        </td>
                        <td class="px-5 py-4 max-w-sm">
                            <div class="font-mono text-gray-700 truncate">{{ $rel->artifact_path }}</div>
                            @if ($rel->release_notes)
                                <div class="text-gray-500 mt-0.5">{{ $rel->release_notes }}</div>
                            @endif
                        </td>
                        <td class="px-5 py-4 text-right">
                            @if (! $rel->is_active)
                                <form method="POST" action="{{ route('remote.releases.activate', $rel) }}">
                                    @csrf
                                    <button type="submit" class="px-3 py-1.5 rounded-lg bg-[#1A1D1F] hover:bg-black text-white font-semibold cursor-pointer">
                                        Aktifkan
                                    </button>
                                </form>
                            @else
                                <span class="text-[11px] text-emerald-600 font-semibold">Versi Aktif</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-5 py-12 text-center text-sm text-gray-400">
                            Belum ada release terdaftar.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
